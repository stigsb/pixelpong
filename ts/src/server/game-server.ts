import { WebSocketServer, WebSocket } from "ws";
import type { FrameBuffer } from "../frame/frame-buffer.js";
import { JsonFrameEncoder } from "../frame/json-frame-encoder.js";
import type { GameLoop } from "../gameloop/game-loop.js";
import { Event, type Device, type EventType, type EventValue } from "./event.js";
import { ActivePlayerConnection } from "./active-player-connection.js";
import type { PlayerConnection } from "./player-connection.js";
import type { Container } from "../container.js";

export class GameServer {
  private gameLoop!: GameLoop;
  private readonly connections = new Map<WebSocket, PlayerConnection>();
  private readonly wss: WebSocketServer;
  private updateTimer: ReturnType<typeof setInterval>;

  constructor(
    private readonly frameBuffer: FrameBuffer,
    private readonly container: Container,
    port: number,
    fps: number,
    initialGameLoop: GameLoop,
  ) {
    this.wss = new WebSocketServer({ port });

    this.wss.on("connection", (ws) => this.onOpen(ws));

    this.updateTimer = setInterval(() => this.onFrameUpdate(), 1000 / fps);

    this.switchToGameLoop(initialGameLoop);

    console.log(`Listening to port ${port}`);
  }

  private onOpen(ws: WebSocket): void {
    const frameEncoder = new JsonFrameEncoder(this.frameBuffer);
    const playerConnection = new ActivePlayerConnection(frameEncoder);
    this.connections.set(ws, playerConnection);

    for (const [conn, pc] of this.connections) {
      conn.send(pc.getFrameEncoder().encodeFrameInfo(this.frameBuffer));
    }

    ws.on("message", (data) => this.onMessage(ws, data.toString()));
    ws.on("close", () => this.onClose(ws));
    ws.on("error", (err) => this.onError(ws, err));
  }

  private onClose(ws: WebSocket): void {
    console.log("Disconnected");
    this.connections.delete(ws);
  }

  private onError(_ws: WebSocket, err: Error): void {
    console.log(`ERROR: ${err.message}`);
  }

  private onMessage(from: WebSocket, rawmsg: string): void {
    let msg: any;
    try {
      msg = JSON.parse(rawmsg);
    } catch {
      console.log(`Invalid JSON from client: ${rawmsg}`);
      return;
    }
    const playerConnection = this.connections.get(from);
    if (!playerConnection) return;

    if (msg.V !== undefined && msg.D !== undefined && msg.T !== undefined) {
      const event = new Event(msg.D as Device, msg.T as EventType, msg.V as EventValue);
      this.onEvent(event);
    }
    if (msg.input !== undefined) {
      playerConnection.setInputEnabled(Boolean(msg.input));
    }
    if (msg.output !== undefined) {
      playerConnection.setOutputEnabled(Boolean(msg.output));
    }
    if (msg.event) {
      const event = new Event(msg.event.device as Device, msg.event.eventType as EventType, msg.event.value as EventValue);
      this.onEvent(event);
    }
    console.log(`incoming message: ${rawmsg}`);
    if (msg.command) {
      switch (msg.command) {
        case "restart":
          console.log("Restarting on request from client!");
          process.exit(0);
      }
    }
  }

  private onFrameUpdate(): void {
    this.gameLoop.onFrameUpdate();
    const frame = this.frameBuffer.getAndSwitchFrame();
    for (const [conn, playerConnection] of this.connections) {
      if (!playerConnection.isOutputEnabled()) continue;
      const encoder = playerConnection.getFrameEncoder();
      const encoded = encoder.encodeFrame(frame);
      if (encoded && conn.readyState === WebSocket.OPEN) {
        conn.send(encoded);
      }
    }
  }

  private onEvent(event: Event): void {
    this.gameLoop.onEvent(event);
  }

  switchToGameLoop(gameLoop: GameLoop): void {
    this.gameLoop = gameLoop;
    gameLoop.onEnter();
  }

  close(): void {
    clearInterval(this.updateTimer);
    this.wss.close();
  }
}
