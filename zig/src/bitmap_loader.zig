const std = @import("std");
const Color = @import("color.zig").Color;
const bitmap_mod = @import("bitmap.zig");
const Bitmap = bitmap_mod.Bitmap;
const Sprite = bitmap_mod.Sprite;
const Allocator = std.mem.Allocator;

pub const BitmapLoader = struct {
    allocator: Allocator,
    search_paths: []const []const u8,
    cache: std.StringHashMap(Bitmap),

    pub fn init(allocator: Allocator, search_paths: []const []const u8) BitmapLoader {
        return .{
            .allocator = allocator,
            .search_paths = search_paths,
            .cache = std.StringHashMap(Bitmap).init(allocator),
        };
    }

    pub fn deinit(self: *BitmapLoader) void {
        var it = self.cache.iterator();
        while (it.next()) |entry| {
            self.allocator.free(entry.key_ptr.*);
            self.allocator.free(entry.value_ptr.pixels);
        }
        self.cache.deinit();
    }

    pub fn loadBitmap(self: *BitmapLoader, name: []const u8) !Bitmap {
        if (self.cache.get(name)) |bmp| {
            return bmp;
        }

        const file_path = try self.findBitmapFile(name) orelse
            return error.BitmapNotFound;

        const bmp = try self.loadBitmapFromFile(file_path);
        self.allocator.free(file_path);
        const name_copy = try self.allocator.dupe(u8, name);
        try self.cache.put(name_copy, bmp);
        return bmp;
    }

    pub fn loadSprite(self: *BitmapLoader, name: []const u8) !Sprite {
        const bmp = try self.loadBitmap(name);
        return Sprite{ .bitmap = bmp };
    }

    fn findBitmapFile(self: *BitmapLoader, name: []const u8) !?[]u8 {
        for (self.search_paths) |dir| {
            const path = try std.fmt.allocPrint(self.allocator, "{s}/{s}.txt", .{ dir, name });
            std.fs.cwd().access(path, .{}) catch {
                self.allocator.free(path);
                continue;
            };
            return path;
        }
        return null;
    }

    fn loadBitmapFromFile(self: *BitmapLoader, path: []const u8) !Bitmap {
        const file = try std.fs.cwd().openFile(path, .{});
        defer file.close();

        const content = try file.readToEndAlloc(self.allocator, 1024 * 1024);
        defer self.allocator.free(content);

        // Parse lines to find dimensions
        var lines: std.ArrayList([]const u8) = .empty;
        defer lines.deinit(self.allocator);

        var width: u32 = 0;
        var line_start: usize = 0;
        for (content, 0..) |c, i| {
            if (c == '\n' or i == content.len - 1) {
                var line_end = i;
                if (c != '\n' and i == content.len - 1) {
                    line_end = i + 1;
                }
                var line = content[line_start..line_end];
                // Strip trailing '|' and '\r'
                while (line.len > 0 and (line[line.len - 1] == '|' or line[line.len - 1] == '\r')) {
                    line = line[0 .. line.len - 1];
                }
                if (line.len > width) {
                    width = @intCast(line.len);
                }
                try lines.append(self.allocator, line);
                line_start = i + 1;
            }
        }

        const height: u32 = @intCast(lines.items.len);
        const pixels = try self.allocator.alloc(Color, width * height);
        @memset(pixels, .transparent);

        for (lines.items, 0..) |line, y| {
            const max_x = @min(width, @as(u32, @intCast(line.len)));
            for (0..max_x) |x| {
                if (mapColor(line[x])) |color| {
                    pixels[y * width + x] = color;
                }
            }
        }

        return Bitmap{
            .width = width,
            .height = height,
            .pixels = pixels,
        };
    }
};

fn mapColor(c: u8) ?Color {
    return switch (c) {
        ' ' => .transparent,
        '.', '0' => .black,
        '#', '1' => .white,
        '2' => .red,
        '3' => .cyan,
        '4' => .purple,
        '5' => .green,
        '6' => .blue,
        '7' => .yellow,
        '8' => .orange,
        '9' => .brown,
        'a' => .light_red,
        'b' => .dark_grey,
        'c' => .grey,
        'd' => .light_green,
        'e' => .light_blue,
        'f' => .light_grey,
        else => null,
    };
}
