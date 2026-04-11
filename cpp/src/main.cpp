#include <cstdio>
#include <cstdlib>
#include <filesystem>
#include <string>
#include <vector>

#include "bitmap/bitmap_loader.hpp"
#include "bitmap/font_loader.hpp"
#include "frame/frame_buffer.hpp"
#include "server/game_server.hpp"

namespace fs = std::filesystem;

static void usage(const char* prog) {
    std::printf("Usage: %s [options]\n", prog);
    std::printf("  -p PORT    Server port (default: 4432)\n");
    std::printf("  -f FPS     Frames per second (default: 10)\n");
    std::printf("  -b ADDR    Bind address (default: 0.0.0.0)\n");
    std::printf("  -r PATH    Resource directory (default: ../res)\n");
    std::printf("  -h         Show this help\n");
}

int main(int argc, char* argv[]) {
    int port = 4432;
    double fps = 10.0;
    std::string bind_addr = "0.0.0.0";
    std::string res_dir = "../res";

    for (int i = 1; i < argc; ++i) {
        std::string arg = argv[i];
        if (arg == "-p" && i + 1 < argc) {
            port = std::atoi(argv[++i]);
        } else if (arg == "-f" && i + 1 < argc) {
            fps = std::atof(argv[++i]);
        } else if (arg == "-b" && i + 1 < argc) {
            bind_addr = argv[++i];
        } else if (arg == "-r" && i + 1 < argc) {
            res_dir = argv[++i];
        } else if (arg == "-h") {
            usage(argv[0]);
            return 0;
        }
    }

    fs::path res_path(res_dir);
    if (!fs::exists(res_path)) {
        std::fprintf(stderr, "Resource directory not found: %s\n", res_dir.c_str());
        return 1;
    }

    constexpr int width = 47;
    constexpr int height = 27;

    pixelpong::FrameBuffer frame_buffer(width, height);

    std::vector<fs::path> bitmap_paths = {
        res_path / "bitmaps" / (std::to_string(width) + "x" + std::to_string(height)),
        res_path / "sprites",
    };
    pixelpong::BitmapLoader bitmap_loader(std::move(bitmap_paths));
    pixelpong::FontLoader font_loader(res_path / "fonts");
    pixelpong::GameContext ctx{frame_buffer, bitmap_loader, font_loader};

    std::printf("Pixelpong C++ server starting...\n");
    pixelpong::GameServer server(port, bind_addr, fps, ctx, res_dir);
    server.run();

    return 0;
}
