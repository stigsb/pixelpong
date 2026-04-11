import { test, expect } from "@playwright/test";

// The "PRESS START" bitmap has lit pixels in rows 3-13 of the 47x27 grid.
// The "TO PLAY" bitmap has lit pixels in rows 15-25.
// The animation alternates on a ~2-second cycle per phase (4s total).
//
// We sample canvas pixels at known bitmap positions to detect which
// phase is showing. A non-black pixel means that text is visible.

// A '#' pixel from press_start.txt: row 3, col 11 (the 'P' in PRESS)
const PRESS_START_SAMPLE = { gx: 11, gy: 3 };
// A '#' pixel from to_play.txt: row 21, col 14 (the 'P' in Play)
const TO_PLAY_SAMPLE = { gx: 14, gy: 21 };

/** Read the RGBA value of a game-grid pixel from the canvas. */
async function getPixelColor(
  page: import("@playwright/test").Page,
  gx: number,
  gy: number
): Promise<[number, number, number, number]> {
  return page.evaluate(
    ({ x, y }) => {
      const canvas = document.querySelector("canvas")!;
      const ctx = canvas.getContext("2d")!;
      const pw = (window as any).pixelSizeX || 20;
      const ph = (window as any).pixelSizeY || 18;
      const cx = x * pw + Math.floor(pw / 2);
      const cy = y * ph + Math.floor(ph / 2);
      const d = ctx.getImageData(cx, cy, 1, 1).data;
      return [d[0], d[1], d[2], d[3]] as [number, number, number, number];
    },
    { x: gx, y: gy }
  );
}

function isBlack(rgba: [number, number, number, number]): boolean {
  return rgba[0] === 0 && rgba[1] === 0 && rgba[2] === 0;
}

test("PRESS START TO PLAY animation runs and alternates", async ({ page }) => {
  const port = process.env.PONG_PORT ?? "4472";

  // Override the WebSocket constructor so the client connects to the
  // correct port regardless of the hardcoded wsUri in index.html.
  await page.addInitScript(`
    Object.defineProperty(window, '__pongPort', { value: '${port}' });
  `);
  await page.addInitScript(() => {
    const OrigWS = window.WebSocket;
    (window as any).WebSocket = function (_url: string, ...args: any[]) {
      return new OrigWS(
        `ws://localhost:${(window as any).__pongPort}/`,
        ...args
      );
    } as any;
    (window as any).WebSocket.prototype = OrigWS.prototype;
  });

  await page.goto("/");

  // Wait for the WebSocket frameInfo message to resize the canvas
  // beyond its initial 470px width.
  await expect
    .poll(
      async () =>
        page.evaluate(() => {
          const c = document.querySelector("canvas");
          return c ? parseInt(c.getAttribute("width") || "0") : 0;
        }),
      { timeout: 10_000, message: "waiting for frameInfo to resize canvas" }
    )
    .toBeGreaterThan(470);

  // Wait until at least one sample point shows a non-black pixel,
  // confirming that frame data is being rendered.
  await expect
    .poll(
      async () => {
        const ps = await getPixelColor(
          page,
          PRESS_START_SAMPLE.gx,
          PRESS_START_SAMPLE.gy
        );
        const tp = await getPixelColor(
          page,
          TO_PLAY_SAMPLE.gx,
          TO_PLAY_SAMPLE.gy
        );
        return !isBlack(ps) || !isBlack(tp);
      },
      { timeout: 10_000, message: "waiting for first animation frame" }
    )
    .toBeTruthy();

  // Observe the canvas over ~5 seconds. The animation should show both
  // "PRESS START" (top text lit, sample row 3) and "TO PLAY" (bottom
  // text lit, sample row 21) at least once during this window.
  let sawPressStart = false;
  let sawToPlay = false;

  const start = Date.now();
  while (Date.now() - start < 5000) {
    const ps = await getPixelColor(
      page,
      PRESS_START_SAMPLE.gx,
      PRESS_START_SAMPLE.gy
    );
    const tp = await getPixelColor(
      page,
      TO_PLAY_SAMPLE.gx,
      TO_PLAY_SAMPLE.gy
    );

    if (!isBlack(ps)) sawPressStart = true;
    if (!isBlack(tp)) sawToPlay = true;

    if (sawPressStart && sawToPlay) break;

    await page.waitForTimeout(200);
  }

  expect(sawPressStart, '"PRESS START" text was never visible').toBe(true);
  expect(sawToPlay, '"TO PLAY" text was never visible').toBe(true);
});
