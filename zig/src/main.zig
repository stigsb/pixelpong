const std = @import("std");
const FrameBuffer = @import("frame_buffer.zig").FrameBuffer;
const BitmapLoader = @import("bitmap_loader.zig").BitmapLoader;
const game_loop_mod = @import("game_loop.zig");
const MainGameState = game_loop_mod.MainGameState;
const PressStartState = game_loop_mod.PressStartState;
const GameLoop = game_loop_mod.GameLoop;
const GameServer = @import("game_server.zig").GameServer;

pub fn main() !void {
    var gpa = std.heap.GeneralPurposeAllocator(.{}){};
    defer _ = gpa.deinit();
    const allocator = gpa.allocator();

    // Parse command line args
    var args = try std.process.argsWithAllocator(allocator);
    defer args.deinit();
    _ = args.next(); // skip program name

    var port: u16 = 4472;
    var fps: f64 = 10.0;
    var bind_addr: []const u8 = "0.0.0.0";

    while (args.next()) |arg| {
        if (std.mem.eql(u8, arg, "-p")) {
            if (args.next()) |p| {
                port = std.fmt.parseInt(u16, p, 10) catch 4432;
            }
        } else if (std.mem.eql(u8, arg, "-f")) {
            if (args.next()) |f| {
                fps = std.fmt.parseFloat(f64, f) catch 10.0;
            }
        } else if (std.mem.eql(u8, arg, "-b")) {
            if (args.next()) |b| {
                bind_addr = b;
            }
        }
    }

    // Environment variable overrides
    if (std.process.getEnvVarOwned(allocator, "PONG_PORT")) |val| {
        port = std.fmt.parseInt(u16, val, 10) catch port;
        allocator.free(val);
    } else |_| {}

    if (std.process.getEnvVarOwned(allocator, "PONG_FPS")) |val| {
        fps = std.fmt.parseFloat(f64, val) catch fps;
        allocator.free(val);
    } else |_| {}

    if (std.process.getEnvVarOwned(allocator, "PONG_BIND_ADDR")) |val| {
        bind_addr = val;
        // Note: leaks val, but it lives for the program's lifetime
    } else |_| {}

    const width: u32 = blk: {
        if (std.process.getEnvVarOwned(allocator, "PONG_WIDTH")) |val| {
            const w = std.fmt.parseInt(u32, val, 10) catch 47;
            allocator.free(val);
            break :blk w;
        } else |_| {
            break :blk 47;
        }
    };

    const height: u32 = blk: {
        if (std.process.getEnvVarOwned(allocator, "PONG_HEIGHT")) |val| {
            const h = std.fmt.parseInt(u32, val, 10) catch 27;
            allocator.free(val);
            break :blk h;
        } else |_| {
            break :blk 27;
        }
    };

    // Determine resource paths relative to executable or project root
    const res_base = resolveResPath(allocator) catch "../res";

    const bitmap_path = std.fmt.allocPrint(allocator, "{s}/bitmaps/{d}x{d}", .{ res_base, width, height }) catch unreachable;
    defer allocator.free(bitmap_path);
    const sprites_path = std.fmt.allocPrint(allocator, "{s}/sprites", .{res_base}) catch unreachable;
    defer allocator.free(sprites_path);

    const search_paths = [_][]const u8{ bitmap_path, sprites_path };

    // Initialize subsystems
    var fb = try FrameBuffer.init(allocator, width, height);
    defer fb.deinit();

    var loader = BitmapLoader.init(allocator, &search_paths);
    defer loader.deinit();

    const htdocs_path = std.fmt.allocPrint(allocator, "{s}/htdocs", .{res_base}) catch unreachable;
    defer allocator.free(htdocs_path);

    var game_state = try MainGameState.init(&loader, &fb);
    var press_start = try PressStartState.init(&loader, &fb, &game_state);
    var active_loop = GameLoop{ .press_start = &press_start };

    // Enter game
    active_loop.onEnter(&fb);

    // Start server
    var server = try GameServer.init(allocator, &fb, active_loop, bind_addr, port, fps, htdocs_path);
    defer server.deinit();

    try server.run();
}

fn resolveResPath(_: std.mem.Allocator) ![]const u8 {
    // Try ../res relative to CWD
    std.fs.cwd().access("../res/sprites", .{}) catch {
        // Try ./res
        std.fs.cwd().access("res/sprites", .{}) catch {
            return error.ResourcePathNotFound;
        };
        return "res";
    };
    return "../res";
}

// Re-export all modules for testing
pub const color = @import("color.zig");
pub const bitmap = @import("bitmap.zig");
pub const bitmap_loader = @import("bitmap_loader.zig");
pub const frame_buffer = @import("frame_buffer.zig");
pub const frame_encoder = @import("frame_encoder.zig");
pub const event = @import("event.zig");
pub const game_loop = @import("game_loop.zig");
pub const game_server = @import("game_server.zig");
pub const websocket = @import("websocket.zig");

test "color palette has 16 entries" {
    try std.testing.expectEqual(@as(usize, 16), color.Color.palette.len);
}

test "frame buffer init and pixel ops" {
    const allocator = std.testing.allocator;
    var fb = try frame_buffer.FrameBuffer.init(allocator, 47, 27);
    defer fb.deinit();

    try std.testing.expectEqual(color.Color.black, fb.getPixel(0, 0));
    fb.setPixel(5, 3, .white);
    try std.testing.expectEqual(color.Color.white, fb.getPixel(5, 3));
}

test "frame buffer getAndSwitchFrame resets to background" {
    const allocator = std.testing.allocator;
    var fb = try frame_buffer.FrameBuffer.init(allocator, 4, 2);
    defer fb.deinit();

    fb.setPixel(1, 0, .red);
    _ = fb.getAndSwitchFrame();
    // After switch, should be back to background (black)
    try std.testing.expectEqual(color.Color.black, fb.getPixel(1, 0));
}
