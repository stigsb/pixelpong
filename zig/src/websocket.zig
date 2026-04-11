const std = @import("std");
const Allocator = std.mem.Allocator;

/// Minimal WebSocket server implementation (RFC 6455).
pub const Handshake = struct {
    /// Parse an HTTP upgrade request and produce the 101 response.
    pub fn respond(request: []const u8, buf: []u8) ![]u8 {
        const key = extractSecWebSocketKey(request) orelse return error.InvalidHandshake;

        // Compute Sec-WebSocket-Accept = base64(sha1(key ++ GUID))
        const guid = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";
        var hasher = std.crypto.hash.Sha1.init(.{});
        hasher.update(key);
        hasher.update(guid);
        const digest = hasher.finalResult();

        const accept = std.base64.standard.Encoder.encode(
            buf[0..28],
            &digest,
        );
        _ = accept;

        var fbs = std.io.fixedBufferStream(buf);
        const w = fbs.writer();
        try w.writeAll("HTTP/1.1 101 Switching Protocols\r\n");
        try w.writeAll("Upgrade: websocket\r\n");
        try w.writeAll("Connection: Upgrade\r\n");
        try w.writeAll("Sec-WebSocket-Accept: ");
        // Write the base64 encoded accept key
        const accept_key = encodeAcceptKey(&digest);
        try w.writeAll(&accept_key);
        try w.writeAll("\r\n\r\n");
        return fbs.getWritten();
    }

    fn encodeAcceptKey(digest: *const [20]u8) [28]u8 {
        var out: [28]u8 = undefined;
        _ = std.base64.standard.Encoder.encode(&out, digest);
        return out;
    }

    fn extractSecWebSocketKey(request: []const u8) ?[]const u8 {
        const needle = "Sec-WebSocket-Key: ";
        var i: usize = 0;
        while (i + needle.len < request.len) : (i += 1) {
            if (std.mem.eql(u8, request[i .. i + needle.len], needle)) {
                const start = i + needle.len;
                var end = start;
                while (end < request.len and request[end] != '\r' and request[end] != '\n') {
                    end += 1;
                }
                return request[start..end];
            }
        }
        return null;
    }
};

pub const Frame = struct {
    /// Decode a WebSocket frame from raw bytes.
    /// Returns the payload and number of bytes consumed, or null if incomplete.
    pub fn decode(data: []const u8) ?struct { payload: []const u8, consumed: usize, decoded: []u8 } {
        if (data.len < 2) return null;

        const byte1 = data[1];
        const masked = (byte1 & 0x80) != 0;
        var payload_len: u64 = byte1 & 0x7F;
        var offset: usize = 2;

        if (payload_len == 126) {
            if (data.len < 4) return null;
            payload_len = (@as(u64, data[2]) << 8) | data[3];
            offset = 4;
        } else if (payload_len == 127) {
            if (data.len < 10) return null;
            payload_len = 0;
            for (0..8) |i| {
                payload_len = (payload_len << 8) | data[2 + i];
            }
            offset = 10;
        }

        if (masked) {
            if (data.len < offset + 4) return null;
            offset += 4;
        }

        if (data.len < offset + payload_len) return null;
        const plen: usize = @intCast(payload_len);

        return .{
            .payload = data[offset .. offset + plen],
            .consumed = offset + plen,
            .decoded = @constCast(data[offset .. offset + plen]),
        };
    }

    /// Unmask payload in-place.
    pub fn unmask(data: []u8, mask_key: [4]u8) void {
        for (data, 0..) |*b, i| {
            b.* ^= mask_key[i % 4];
        }
    }

    /// Read a complete WebSocket text frame from raw data.
    /// Returns unmasked payload and bytes consumed, or null if not enough data.
    pub fn readTextFrame(data: []const u8) ?struct { payload: []u8, consumed: usize } {
        if (data.len < 2) return null;

        const opcode = data[0] & 0x0F;
        // opcode 1 = text, 8 = close, 9 = ping, 10 = pong
        if (opcode == 8) return null; // close frame

        const byte1 = data[1];
        const masked = (byte1 & 0x80) != 0;
        var payload_len: u64 = byte1 & 0x7F;
        var offset: usize = 2;

        if (payload_len == 126) {
            if (data.len < 4) return null;
            payload_len = (@as(u64, data[2]) << 8) | data[3];
            offset = 4;
        } else if (payload_len == 127) {
            if (data.len < 10) return null;
            payload_len = 0;
            for (0..8) |i| {
                payload_len = (payload_len << 8) | data[2 + i];
            }
            offset = 10;
        }

        var mask_key: [4]u8 = .{ 0, 0, 0, 0 };
        if (masked) {
            if (data.len < offset + 4) return null;
            @memcpy(&mask_key, data[offset .. offset + 4]);
            offset += 4;
        }

        const plen: usize = @intCast(payload_len);
        if (data.len < offset + plen) return null;

        // Unmask in place (we cast away const — caller owns the buffer)
        const payload: []u8 = @constCast(data[offset .. offset + plen]);
        if (masked) {
            unmask(payload, mask_key);
        }

        return .{
            .payload = payload,
            .consumed = offset + plen,
        };
    }

    /// Encode a text frame (server to client, unmasked).
    pub fn encodeText(payload: []const u8, buf: []u8) []u8 {
        var offset: usize = 0;
        buf[0] = 0x81; // FIN + text opcode
        offset = 1;

        if (payload.len < 126) {
            buf[1] = @intCast(payload.len);
            offset = 2;
        } else if (payload.len < 65536) {
            buf[1] = 126;
            buf[2] = @intCast((payload.len >> 8) & 0xFF);
            buf[3] = @intCast(payload.len & 0xFF);
            offset = 4;
        } else {
            buf[1] = 127;
            const len = payload.len;
            for (0..8) |i| {
                buf[2 + i] = @intCast((len >> @intCast(56 - i * 8)) & 0xFF);
            }
            offset = 10;
        }

        @memcpy(buf[offset .. offset + payload.len], payload);
        return buf[0 .. offset + payload.len];
    }
};
