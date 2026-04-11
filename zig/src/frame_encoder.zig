const std = @import("std");
const Color = @import("color.zig").Color;
const FrameBuffer = @import("frame_buffer.zig").FrameBuffer;
const Allocator = std.mem.Allocator;

pub const JsonFrameEncoder = struct {
    width: u32,
    height: u32,
    size: u32,
    previous_frame: []Color,
    allocator: Allocator,

    pub fn init(allocator: Allocator, fb_width: u32, fb_height: u32) !JsonFrameEncoder {
        const size = fb_width * fb_height;
        const prev = try allocator.alloc(Color, size);
        @memset(prev, .black);
        return .{
            .width = fb_width,
            .height = fb_height,
            .size = size,
            .previous_frame = prev,
            .allocator = allocator,
        };
    }

    pub fn deinit(self: *JsonFrameEncoder) void {
        self.allocator.free(self.previous_frame);
    }

    /// Encode a frame as JSON. Returns null if nothing changed.
    pub fn encodeFrame(self: *JsonFrameEncoder, frame: []const Color) !?[]u8 {
        var diff_count: u32 = 0;
        for (0..self.size) |i| {
            if (frame[i] != self.previous_frame[i]) {
                diff_count += 1;
            }
        }

        if (diff_count == 0) return null;

        var buf: std.ArrayList(u8) = .empty;
        const writer = buf.writer(self.allocator);

        if (diff_count < self.size / 3) {
            // Send delta: only changed pixels
            try writer.writeAll("{\"frameDelta\":{");
            var first = true;
            for (0..self.size) |i| {
                if (frame[i] != self.previous_frame[i]) {
                    if (!first) try writer.writeByte(',');
                    first = false;
                    try std.fmt.format(writer, "\"{d}\":{d}", .{ i, @intFromEnum(frame[i]) });
                }
            }
            try writer.writeAll("}}");
        } else {
            // Send full frame: all non-black pixels
            try writer.writeAll("{\"frame\":{");
            var first = true;
            for (0..self.size) |i| {
                if (frame[i] != .black) {
                    if (!first) try writer.writeByte(',');
                    first = false;
                    try std.fmt.format(writer, "\"{d}\":{d}", .{ i, @intFromEnum(frame[i]) });
                }
            }
            try writer.writeAll("}}");
        }

        @memcpy(self.previous_frame, frame);
        return try buf.toOwnedSlice(self.allocator);
    }

    pub fn encodeFrameInfo(self: *const JsonFrameEncoder) ![]u8 {
        var buf: std.ArrayList(u8) = .empty;
        const writer = buf.writer(self.allocator);

        try writer.writeAll("{\"frameInfo\":{");
        try std.fmt.format(writer, "\"width\":{d},\"height\":{d}", .{ self.width, self.height });
        try writer.writeAll(",\"palette\":{");
        for (0..16) |i| {
            if (i > 0) try writer.writeByte(',');
            try std.fmt.format(writer, "\"{d}\":\"{s}\"", .{ i, Color.palette[i] });
        }
        try writer.writeAll("}}}");

        return try buf.toOwnedSlice(self.allocator);
    }
};
