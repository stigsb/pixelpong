use std::net::SocketAddr;

use futures_util::{SinkExt, StreamExt};
use tokio::io::{AsyncReadExt, AsyncWriteExt};
use tokio::net::{TcpListener, TcpStream};
use tokio::sync::{broadcast, mpsc};
use tokio_tungstenite::tungstenite::Message;

use crate::bitmap::loader::BitmapLoader;
use crate::frame::offscreen::OffscreenFrameBuffer;
use crate::frame::{FrameBuffer, FrameEncoder};
use crate::frame::json_encoder::JsonFrameEncoder;
use crate::gameloop::press_start::PressStartToPlayGameLoop;
use crate::gameloop::{GameLoop, GameLoopTransition};
use crate::server::event::Event;

#[derive(Clone, Debug)]
pub enum ServerMessage {
    Frame(String),
}

#[derive(Debug)]
pub enum ClientMessage {
    Event(Event),
    SetInput(bool),
    SetOutput(bool),
    Restart,
}

pub async fn run_server(bind_addr: &str, port: u16, fps: f64, res_dir: &str) {
    let addr = format!("{}:{}", bind_addr, port);
    let listener = TcpListener::bind(&addr).await.expect("Failed to bind");
    println!("Listening on port {}", port);

    let (frame_tx, _) = broadcast::channel::<String>(16);
    let (event_tx, mut event_rx) = mpsc::channel::<ClientMessage>(256);

    let frame_tx_clone = frame_tx.clone();
    let res_dir = res_dir.to_string();
    let htdocs_path = format!("{}/htdocs", res_dir);

    // Frame loop task
    let _frame_loop = tokio::spawn(async move {
        let mut fb = OffscreenFrameBuffer::new(47, 27);
        let bitmap_path = format!("{}:{}",
            format!("{}/bitmaps/47x27", res_dir),
            format!("{}/sprites", res_dir),
        );
        let mut loader = BitmapLoader::new(&bitmap_path);

        let mut game_loop: Box<dyn GameLoop> = Box::new(PressStartToPlayGameLoop::new());
        game_loop.on_enter(&mut fb, &mut loader);

        let mut encoder = JsonFrameEncoder::new(47, 27);

        let mut interval = tokio::time::interval(std::time::Duration::from_secs_f64(1.0 / fps));

        loop {
            tokio::select! {
                _ = interval.tick() => {
                    game_loop.on_frame_update(&mut fb);
                    let frame = fb.get_and_switch_frame();
                    if let Some(encoded) = encoder.encode_frame(&frame) {
                        let _ = frame_tx_clone.send(encoded);
                    }
                }
                Some(msg) = event_rx.recv() => {
                    match msg {
                        ClientMessage::Event(event) => {
                            if let Some(transition) = game_loop.on_event(&event) {
                                match transition {
                                    GameLoopTransition::SwitchTo(mut new_loop) => {
                                        new_loop.on_enter(&mut fb, &mut loader);
                                        game_loop = new_loop;
                                    }
                                }
                            }
                        }
                        ClientMessage::Restart => {
                            game_loop = Box::new(PressStartToPlayGameLoop::new());
                            game_loop.on_enter(&mut fb, &mut loader);
                            encoder = JsonFrameEncoder::new(47, 27);
                        }
                        _ => {}
                    }
                }
            }
        }
    });

    // Accept connections
    let frame_info_encoder = JsonFrameEncoder::new(47, 27);
    let frame_info = frame_info_encoder.encode_frame_info(47, 27);

    while let Ok((stream, addr)) = listener.accept().await {
        let event_tx = event_tx.clone();
        let frame_rx = frame_tx.subscribe();
        let frame_info = frame_info.clone();
        let htdocs_path = htdocs_path.clone();
        tokio::spawn(handle_connection(stream, addr, event_tx, frame_rx, frame_info, htdocs_path));
    }
}

async fn handle_connection(
    mut stream: TcpStream,
    addr: SocketAddr,
    event_tx: mpsc::Sender<ClientMessage>,
    mut frame_rx: broadcast::Receiver<String>,
    frame_info: String,
    htdocs_path: String,
) {
    // Peek at the incoming data to determine if this is a WebSocket upgrade request
    let mut peek_buf = [0u8; 2048];
    let n = match stream.peek(&mut peek_buf).await {
        Ok(n) => n,
        Err(e) => {
            eprintln!("Failed to peek at stream from {}: {}", addr, e);
            return;
        }
    };

    let peek_str = String::from_utf8_lossy(&peek_buf[..n]);
    let is_websocket = peek_str.to_ascii_lowercase().contains("upgrade: websocket");

    if !is_websocket {
        // Read the full HTTP request (consume the peeked data)
        let mut buf = vec![0u8; 4096];
        let _ = stream.read(&mut buf).await;

        // Serve index.html for any non-WebSocket HTTP request
        let index_path = format!("{}/index.html", htdocs_path);
        let response = match tokio::fs::read(&index_path).await {
            Ok(contents) => {
                format!(
                    "HTTP/1.1 200 OK\r\nContent-Type: text/html; charset=utf-8\r\nContent-Length: {}\r\nConnection: close\r\n\r\n",
                    contents.len()
                ) + &String::from_utf8_lossy(&contents)
            }
            Err(_) => {
                "HTTP/1.1 404 Not Found\r\nContent-Length: 9\r\nConnection: close\r\n\r\nNot Found".to_string()
            }
        };

        let _ = stream.write_all(response.as_bytes()).await;
        return;
    }

    let ws_stream = match tokio_tungstenite::accept_async(stream).await {
        Ok(ws) => ws,
        Err(e) => {
            eprintln!("WebSocket handshake failed for {}: {}", addr, e);
            return;
        }
    };

    println!("New connection from {}", addr);
    let (mut ws_sender, mut ws_receiver) = ws_stream.split();

    // Send frame info
    let _ = ws_sender.send(Message::Text(frame_info.into())).await;

    let mut output_enabled = false;

    loop {
        tokio::select! {
            msg = ws_receiver.next() => {
                match msg {
                    Some(Ok(Message::Text(text))) => {
                        if let Ok(json) = serde_json::from_str::<serde_json::Value>(&text) {
                            if let Some(v) = json.get("input") {
                                let _input_enabled = v.as_bool().unwrap_or(false);
                            }
                            if let Some(v) = json.get("output") {
                                output_enabled = v.as_bool().unwrap_or(false);
                            }
                            if let Some(evt) = json.get("event") {
                                let device = evt.get("device").and_then(|v| v.as_i64()).unwrap_or(0) as i32;
                                let event_type = evt.get("eventType").and_then(|v| v.as_i64()).unwrap_or(0) as i32;
                                let value = evt.get("value").and_then(|v| v.as_i64()).unwrap_or(0) as i32;
                                let _ = event_tx.send(ClientMessage::Event(Event::new(device, event_type, value))).await;
                            }
                            if let (Some(v), Some(d), Some(t)) = (json.get("V"), json.get("D"), json.get("T")) {
                                let device = d.as_i64().unwrap_or(0) as i32;
                                let event_type = t.as_i64().unwrap_or(0) as i32;
                                let value = v.as_i64().unwrap_or(0) as i32;
                                let _ = event_tx.send(ClientMessage::Event(Event::new(device, event_type, value))).await;
                            }
                            if let Some(cmd) = json.get("command").and_then(|v| v.as_str()) {
                                if cmd == "restart" {
                                    let _ = event_tx.send(ClientMessage::Restart).await;
                                }
                            }
                        }
                        println!("incoming message: {}", text);
                    }
                    Some(Ok(Message::Close(_))) | None => {
                        println!("Disconnected: {}", addr);
                        break;
                    }
                    Some(Err(e)) => {
                        eprintln!("Error from {}: {}", addr, e);
                        break;
                    }
                    _ => {}
                }
            }
            Ok(frame_data) = frame_rx.recv() => {
                if output_enabled {
                    if ws_sender.send(Message::Text(frame_data.into())).await.is_err() {
                        break;
                    }
                }
            }
        }
    }
}
