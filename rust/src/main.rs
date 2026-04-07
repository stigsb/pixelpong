mod bitmap;
mod frame;
mod gameloop;
mod server;

use clap::Parser;

#[derive(Parser)]
#[command(name = "pixelpong")]
#[command(about = "PixelPong WebSocket game server")]
struct Args {
    #[arg(short, long, default_value = "4432")]
    port: u16,

    #[arg(short, long, default_value_t = 10.0)]
    fps: f64,

    #[arg(short, long, default_value = "0.0.0.0")]
    bind_addr: String,
}

#[tokio::main]
async fn main() {
    let args = Args::parse();

    // Resolve res/ directory relative to the binary
    let exe_dir = std::env::current_exe()
        .ok()
        .and_then(|p| p.parent().map(|p| p.to_path_buf()))
        .unwrap_or_else(|| std::env::current_dir().unwrap());

    let res_dir = if std::path::Path::new("../res").exists() {
        std::fs::canonicalize("../res").unwrap().to_string_lossy().to_string()
    } else if exe_dir.join("../../res").exists() {
        std::fs::canonicalize(exe_dir.join("../../res")).unwrap().to_string_lossy().to_string()
    } else {
        // Try relative to workspace root
        let manifest_dir = env!("CARGO_MANIFEST_DIR");
        let res_path = std::path::Path::new(manifest_dir).parent().unwrap().join("res");
        res_path.to_string_lossy().to_string()
    };

    println!("Using resources from: {}", res_dir);
    server::game_server::run_server(&args.bind_addr, args.port, args.fps, &res_dir).await;
}
