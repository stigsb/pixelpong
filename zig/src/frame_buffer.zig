const std = @import("std");
const Color = @import("color.zig").Color;
const bitmap_mod = @import("bitmap.zig");
const Bitmap = bitmap_mod.Bitmap;
const Sprite = bitmap_mod.Sprite;
const Allocator = std.mem.Allocator;

const BitmapRenderCmd = struct {
    bitmap: Bitmap,
    x: i32,
    y: i32,
};

pub const FrameBuffer = struct {
    width: u32,
    height: u32,
    size: u32,
    blank_frame: []Color,
    current_frame: []Color,
    rendered_frame: []Color,
    bitmaps_to_render: std.ArrayList(BitmapRenderCmd),
    allocator: Allocator,

    pub fn init(allocator: Allocator, width: u32, height: u32) !FrameBuffer {
        const size = width * height;
        const blank = try allocator.alloc(Color, size);
        @memset(blank, .black);
        const current = try allocator.alloc(Color, size);
        @memset(current, .black);
        const rendered = try allocator.alloc(Color, size);
        @memset(rendered, .black);

        return .{
            .width = width,
            .height = height,
            .size = size,
            .blank_frame = blank,
            .current_frame = current,
            .rendered_frame = rendered,
            .bitmaps_to_render = .empty,
            .allocator = allocator,
        };
    }

    pub fn deinit(self: *FrameBuffer) void {
        self.allocator.free(self.blank_frame);
        self.allocator.free(self.current_frame);
        self.allocator.free(self.rendered_frame);
        self.bitmaps_to_render.deinit(self.allocator);
    }

    pub fn getPixel(self: *const FrameBuffer, x: u32, y: u32) Color {
        return self.current_frame[y * self.width + x];
    }

    pub fn setPixel(self: *FrameBuffer, x: u32, y: u32, color: Color) void {
        self.current_frame[y * self.width + x] = color;
    }

    pub fn setBackgroundFrame(self: *FrameBuffer, frame: []const Color) void {
        @memcpy(self.blank_frame, frame[0..self.size]);
    }

    pub fn drawBitmapAt(self: *FrameBuffer, bmp: Bitmap, x: i32, y: i32) !void {
        try self.bitmaps_to_render.append(self.allocator, .{ .bitmap = bmp, .x = x, .y = y });
    }

    pub fn drawSpriteIfVisible(self: *FrameBuffer, sprite: *const Sprite) !void {
        if (sprite.visible) {
            try self.drawBitmapAt(sprite.bitmap, sprite.x, sprite.y);
        }
    }

    /// Render queued bitmaps, snapshot frame to rendered_frame, then reset.
    pub fn getAndSwitchFrame(self: *FrameBuffer) []const Color {
        self.renderBitmaps();
        @memcpy(self.rendered_frame, self.current_frame);
        self.newFrame();
        return self.rendered_frame;
    }

    fn renderBitmaps(self: *FrameBuffer) void {
        for (self.bitmaps_to_render.items) |cmd| {
            const w: i32 = @intCast(cmd.bitmap.width);
            const h: i32 = @intCast(cmd.bitmap.height);
            var bx: i32 = 0;
            while (bx < w) : (bx += 1) {
                const xx = cmd.x + bx;
                if (xx < 0 or xx >= @as(i32, @intCast(self.width))) continue;
                var by: i32 = 0;
                while (by < h) : (by += 1) {
                    const yy = cmd.y + by;
                    if (yy < 0 or yy >= @as(i32, @intCast(self.height))) continue;
                    const pixel = cmd.bitmap.pixels[@intCast(by * w + bx)];
                    if (!pixel.isTransparent()) {
                        self.current_frame[@intCast(@as(i32, @intCast(self.width)) * yy + xx)] = pixel;
                    }
                }
            }
        }
    }

    fn newFrame(self: *FrameBuffer) void {
        @memcpy(self.current_frame, self.blank_frame);
        self.bitmaps_to_render.clearRetainingCapacity();
    }
};
