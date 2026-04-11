import { resolve, dirname } from "node:path";
import { fileURLToPath } from "node:url";
import { BitmapLoader } from "./bitmap/bitmap-loader.js";
import { Container } from "./container.js";
import { OffscreenFrameBuffer } from "./frame/offscreen-frame-buffer.js";
import { TestImageScreen } from "./gameloop/test-image-screen.js";
import { GameServer } from "./server/game-server.js";

const __dirname = dirname(fileURLToPath(import.meta.url));
const topDir = resolve(__dirname, "../..");

const width = 47;
const height = 27;
const port = parseInt(process.env.PONG_PORT ?? "4452", 10);
const fps = parseFloat(process.env.PONG_FPS ?? "10.0");

// Parse CLI args
const args = process.argv.slice(2);
let argPort = port;
let argFps = fps;
for (let i = 0; i < args.length; i++) {
  if (args[i] === "-p" && args[i + 1]) argPort = parseInt(args[++i], 10);
  if (args[i] === "-f" && args[i + 1]) argFps = parseFloat(args[++i]);
}

const container = new Container();
const frameBuffer = new OffscreenFrameBuffer(width, height);
const bitmapLoader = new BitmapLoader(
  `${topDir}/res/bitmaps/${width}x${height}:${topDir}/res/sprites`,
);

container.set("frameBuffer", frameBuffer);
container.set("bitmapLoader", bitmapLoader);

const testImageScreen = new TestImageScreen(frameBuffer, container);
const gameServer = new GameServer(frameBuffer, container, argPort, argFps, testImageScreen);
container.set("gameServer", gameServer);
