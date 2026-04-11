import { defineConfig } from "@playwright/test";

export default defineConfig({
  testDir: "./tests",
  timeout: 30_000,
  use: {
    baseURL: `http://localhost:${process.env.PONG_PORT ?? 4472}`,
  },
});
