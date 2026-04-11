const std = @import("std");
const Color = @import("color.zig").Color;
const Allocator = std.mem.Allocator;

pub const Bitmap = struct {
    width: u32,
    height: u32,
    pixels: []const Color,

    pub fn getPixel(self: Bitmap, x: u32, y: u32) Color {
        return self.pixels[y * self.width + x];
    }
};

pub const OwnedBitmap = struct {
    bitmap: Bitmap,
    allocator: Allocator,

    pub fn deinit(self: *OwnedBitmap) void {
        self.allocator.free(self.bitmap.pixels);
    }
};

pub const Sprite = struct {
    bitmap: Bitmap,
    x: i32 = 0,
    y: i32 = 0,
    visible: bool = true,

    pub fn moveTo(self: *Sprite, x: i32, y: i32) void {
        self.x = x;
        self.y = y;
    }

    pub fn setVisible(self: *Sprite, visible: bool) void {
        self.visible = visible;
    }
};
