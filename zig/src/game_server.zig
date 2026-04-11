const std = @import("std");
const net = std.net;
const posix = std.posix;
const Allocator = std.mem.Allocator;

const Color = @import("color.zig").Color;
const FrameBuffer = @import("frame_buffer.zig").FrameBuffer;
const JsonFrameEncoder = @import("frame_encoder.zig").JsonFrameEncoder;
const game_loop_mod = @import("game_loop.zig");
const MainGameState = game_loop_mod.MainGameState;
const PressStartState = game_loop_mod.PressStartState;
const GameLoop = game_loop_mod.GameLoop;
const Event = @import("event.zig").Event;
const ws = @import("websocket.zig");

const Connection = struct {
    stream: net.Stream,
    encoder: JsonFrameEncoder,
    input_enabled: bool = true,
    output_enabled: bool = true,
    handshake_done: bool = false,
    recv_buf: [4096]u8 = undefined,
    recv_len: usize = 0,
};

pub const GameServer = struct {
    allocator: Allocator,
    frame_buffer: *FrameBuffer,
    game_loop: GameLoop,
    connections: std.ArrayList(*Connection),
    listener: net.Server,
    fps: f64,
    htdocs_path: []const u8,

    pub fn init(
        allocator: Allocator,
        frame_buffer: *FrameBuffer,
        game_loop: GameLoop,
        bind_addr: []const u8,
        port: u16,
        fps: f64,
        htdocs_path: []const u8,
    ) !GameServer {
        const address = try net.Address.parseIp(bind_addr, port);
        const listener = try address.listen(.{
            .reuse_address = true,
        });

        // Set listener to non-blocking
        setNonBlocking(listener.stream) catch {};

        std.debug.print("Pixelpong server listening on {s}:{d} at {d:.0} FPS\n", .{ bind_addr, port, fps });

        return .{
            .allocator = allocator,
            .frame_buffer = frame_buffer,
            .game_loop = game_loop,
            .connections = .empty,
            .listener = listener,
            .fps = fps,
            .htdocs_path = htdocs_path,
        };
    }

    pub fn deinit(self: *GameServer) void {
        for (self.connections.items) |conn| {
            conn.stream.close();
            conn.encoder.deinit();
            self.allocator.destroy(conn);
        }
        self.connections.deinit(self.allocator);
        self.listener.deinit();
    }

    pub fn run(self: *GameServer) !void {
        const frame_interval_ns: u64 = @intFromFloat(1_000_000_000.0 / self.fps);

        while (true) {
            const frame_start = std.time.nanoTimestamp();

            self.acceptNewConnections();
            self.readAllConnections();

            try self.game_loop.onFrameUpdate(self.frame_buffer);

            const frame = self.frame_buffer.getAndSwitchFrame();
            try self.broadcastFrame(frame);

            const elapsed: u64 = @intCast(std.time.nanoTimestamp() - frame_start);
            if (elapsed < frame_interval_ns) {
                std.Thread.sleep(frame_interval_ns - elapsed);
            }
        }
    }

    fn acceptNewConnections(self: *GameServer) void {
        while (true) {
            const conn_result = self.listener.accept() catch break;
            setNonBlocking(conn_result.stream) catch {
                conn_result.stream.close();
                continue;
            };

            const conn = self.allocator.create(Connection) catch continue;
            conn.* = .{
                .stream = conn_result.stream,
                .encoder = JsonFrameEncoder.init(self.allocator, self.frame_buffer.width, self.frame_buffer.height) catch {
                    conn_result.stream.close();
                    self.allocator.destroy(conn);
                    continue;
                },
            };

            self.connections.append(self.allocator, conn) catch {
                conn.stream.close();
                conn.encoder.deinit();
                self.allocator.destroy(conn);
                continue;
            };

            std.debug.print("New connection\n", .{});
        }
    }

    fn setNonBlocking(stream: net.Stream) !void {
        const F = posix.F;
        const fl_flags = try posix.fcntl(stream.handle, F.GETFL, 0);
        _ = try posix.fcntl(stream.handle, F.SETFL, fl_flags | (1 << @bitOffsetOf(posix.O, "NONBLOCK")));
    }

    fn readAllConnections(self: *GameServer) void {
        var i: usize = 0;
        while (i < self.connections.items.len) {
            const conn = self.connections.items[i];
            if (!self.readConnection(conn)) {
                std.debug.print("Disconnected\n", .{});
                conn.stream.close();
                conn.encoder.deinit();
                self.allocator.destroy(conn);
                _ = self.connections.swapRemove(i);
                continue;
            }
            i += 1;
        }
    }

    fn readConnection(self: *GameServer, conn: *Connection) bool {
        const bytes_read = conn.stream.read(conn.recv_buf[conn.recv_len..]) catch |err| {
            if (err == error.WouldBlock) return true;
            return false;
        };
        if (bytes_read == 0 and conn.handshake_done) return false;
        if (bytes_read == 0) return true;
        conn.recv_len += bytes_read;

        if (!conn.handshake_done) {
            if (std.mem.indexOf(u8, conn.recv_buf[0..conn.recv_len], "\r\n\r\n")) |_| {
                const request = conn.recv_buf[0..conn.recv_len];

                // Check if this is a WebSocket upgrade request
                if (std.mem.indexOf(u8, request, "Upgrade: websocket") != null or
                    std.mem.indexOf(u8, request, "Upgrade: Websocket") != null or
                    std.mem.indexOf(u8, request, "Upgrade: WebSocket") != null)
                {
                    var resp_buf: [512]u8 = undefined;
                    const resp = ws.Handshake.respond(request, &resp_buf) catch return false;
                    _ = conn.stream.write(resp) catch return false;
                    conn.handshake_done = true;
                    conn.recv_len = 0;

                    const info = conn.encoder.encodeFrameInfo() catch return true;
                    defer self.allocator.free(info);
                    self.sendWebSocketText(conn, info);
                } else {
                    // Serve static HTML file for regular HTTP requests
                    self.serveHttpFile(conn);
                    return false; // Close connection after serving
                }
            }
            return true;
        }

        // Parse WebSocket frames
        while (conn.recv_len > 0) {
            const result = ws.Frame.readTextFrame(conn.recv_buf[0..conn.recv_len]) orelse break;
            self.handleMessage(result.payload);

            const remaining = conn.recv_len - result.consumed;
            if (remaining > 0) {
                std.mem.copyForwards(u8, conn.recv_buf[0..remaining], conn.recv_buf[result.consumed..conn.recv_len]);
            }
            conn.recv_len = remaining;
        }

        return true;
    }

    fn serveHttpFile(self: *GameServer, conn: *Connection) void {
        const index_path = std.fmt.allocPrint(self.allocator, "{s}/index.html", .{self.htdocs_path}) catch return;
        defer self.allocator.free(index_path);

        const file = std.fs.cwd().openFile(index_path, .{}) catch {
            const not_found = "HTTP/1.1 404 Not Found\r\nContent-Length: 9\r\n\r\nNot Found";
            _ = conn.stream.write(not_found) catch {};
            return;
        };
        defer file.close();

        const stat = file.stat() catch return;
        const body = file.readToEndAlloc(self.allocator, 1024 * 1024) catch return;
        defer self.allocator.free(body);

        var header_buf: [256]u8 = undefined;
        const header = std.fmt.bufPrint(&header_buf, "HTTP/1.1 200 OK\r\nContent-Type: text/html; charset=utf-8\r\nContent-Length: {d}\r\nConnection: close\r\n\r\n", .{stat.size}) catch return;
        _ = conn.stream.write(header) catch return;
        _ = conn.stream.write(body) catch return;
    }

    fn handleMessage(self: *GameServer, payload: []const u8) void {
        const parsed = std.json.parseFromSlice(std.json.Value, self.allocator, payload, .{}) catch return;
        defer parsed.deinit();
        const root = parsed.value;

        if (root != .object) return;
        const obj = root.object;

        // Short form: {V: value, D: device, T: eventType}
        if (obj.get("V")) |v_val| {
            if (obj.get("D")) |d_val| {
                if (obj.get("T")) |t_val| {
                    const device_int = switch (d_val) {
                        .integer => |n| @as(u8, @intCast(n)),
                        else => return,
                    };
                    const event_type_int = switch (t_val) {
                        .integer => |n| @as(u8, @intCast(n)),
                        else => return,
                    };
                    const value_int = switch (v_val) {
                        .integer => |n| @as(i8, @intCast(n)),
                        else => return,
                    };
                    const ev = Event{
                        .device = @enumFromInt(device_int),
                        .event_type = @enumFromInt(event_type_int),
                        .value = value_int,
                    };
                    self.game_loop.onEvent(ev);
                    return;
                }
            }
        }

        // Full form: {event: {device, eventType, value}}
        if (obj.get("event")) |ev_val| {
            if (ev_val == .object) {
                const ev_obj = ev_val.object;
                const device_int: u8 = switch (ev_obj.get("device") orelse return) {
                    .integer => |n| @intCast(n),
                    else => return,
                };
                const event_type_int: u8 = switch (ev_obj.get("eventType") orelse return) {
                    .integer => |n| @intCast(n),
                    else => return,
                };
                const value_int: i8 = switch (ev_obj.get("value") orelse return) {
                    .integer => |n| @intCast(n),
                    else => return,
                };
                const ev = Event{
                    .device = @enumFromInt(device_int),
                    .event_type = @enumFromInt(event_type_int),
                    .value = value_int,
                };
                self.game_loop.onEvent(ev);
            }
        }
    }

    fn broadcastFrame(self: *GameServer, frame: []const Color) !void {
        for (self.connections.items) |conn| {
            if (!conn.handshake_done or !conn.output_enabled) continue;

            const encoded = try conn.encoder.encodeFrame(frame) orelse continue;
            defer self.allocator.free(encoded);
            self.sendWebSocketText(conn, encoded);
        }
    }

    fn sendWebSocketText(_: *GameServer, conn: *Connection, payload: []const u8) void {
        var header_buf: [14]u8 = undefined;
        var header_len: usize = 0;
        header_buf[0] = 0x81; // FIN + text opcode

        if (payload.len < 126) {
            header_buf[1] = @intCast(payload.len);
            header_len = 2;
        } else if (payload.len < 65536) {
            header_buf[1] = 126;
            header_buf[2] = @intCast((payload.len >> 8) & 0xFF);
            header_buf[3] = @intCast(payload.len & 0xFF);
            header_len = 4;
        } else {
            header_buf[1] = 127;
            for (0..8) |i| {
                header_buf[2 + i] = @intCast((payload.len >> @intCast(56 - i * 8)) & 0xFF);
            }
            header_len = 10;
        }

        _ = conn.stream.write(header_buf[0..header_len]) catch return;
        _ = conn.stream.write(payload) catch return;
    }
};
