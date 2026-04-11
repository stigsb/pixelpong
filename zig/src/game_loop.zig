const std = @import("std");
const Event = @import("event.zig").Event;
const FrameBuffer = @import("frame_buffer.zig").FrameBuffer;
const bitmap_mod = @import("bitmap.zig");
const Sprite = bitmap_mod.Sprite;
const BitmapLoader = @import("bitmap_loader.zig").BitmapLoader;
const Color = @import("color.zig").Color;

/// Polymorphic game loop interface using tagged union.
pub const GameLoop = union(enum) {
    main_game: *MainGameState,
    press_start: *PressStartState,

    pub fn onEnter(self: GameLoop, fb: *FrameBuffer) void {
        switch (self) {
            inline else => |state| state.onEnter(fb),
        }
    }

    pub fn onFrameUpdate(self: GameLoop, fb: *FrameBuffer) !void {
        switch (self) {
            inline else => |state| try state.onFrameUpdate(fb),
        }
    }

    pub fn onEvent(self: GameLoop, ev: Event) void {
        switch (self) {
            inline else => |state| state.onEvent(ev),
        }
    }
};

pub const PressStartState = struct {
    press_start_frame: ?[]const Color,
    to_play_frame: ?[]const Color,
    enter_time: f64,
    previous_elapsed: u64,
    main_game_state: *MainGameState,

    pub fn init(loader: *BitmapLoader, fb: *FrameBuffer, main_game_state: *MainGameState) !PressStartState {
        const press_start_bmp = try loader.loadBitmap("press_start");
        const to_play_bmp = try loader.loadBitmap("to_play");
        _ = fb;
        return PressStartState{
            .press_start_frame = press_start_bmp.pixels,
            .to_play_frame = to_play_bmp.pixels,
            .enter_time = 0,
            .previous_elapsed = 0,
            .main_game_state = main_game_state,
        };
    }

    pub fn onEnter(self: *PressStartState, fb: *FrameBuffer) void {
        if (self.press_start_frame) |frame| {
            fb.setBackgroundFrame(frame);
        }
        self.enter_time = currentTime();
        self.previous_elapsed = 0;
    }

    pub fn onFrameUpdate(self: *PressStartState, fb: *FrameBuffer) !void {
        const elapsed: u64 = @intFromFloat(currentTime() - self.enter_time);
        if (elapsed > self.previous_elapsed) {
            switch (elapsed % 4) {
                0 => {
                    if (self.press_start_frame) |frame| {
                        fb.setBackgroundFrame(frame);
                    }
                },
                2 => {
                    if (self.to_play_frame) |frame| {
                        fb.setBackgroundFrame(frame);
                    }
                },
                else => {},
            }
        }
        self.previous_elapsed = elapsed;
    }

    pub fn onEvent(self: *PressStartState, ev: Event) void {
        const EvType = @import("event.zig").EventType;
        if (ev.event_type == EvType.joy_button_1 and ev.value == 0) {
            _ = self;
            std.debug.print("Button pressed - would switch to main game\n", .{});
        }
    }

    fn currentTime() f64 {
        const ns = std.time.nanoTimestamp();
        return @as(f64, @floatFromInt(ns)) / 1_000_000_000.0;
    }
};

const LEFT: usize = 0;
const RIGHT: usize = 1;
const X: usize = 0;
const Y: usize = 1;
const TOP: usize = 0;
const BOTTOM: usize = 1;

const GameState = enum {
    initializing,
    waiting,
    playing,
    game_over,
};

pub const MainGameState = struct {
    // Display
    display_width: u32,
    display_height: u32,

    // Sprites
    paddles: [2]Sprite,
    ball: Sprite,
    sprites_background: ?[]const Color,

    // Paddle physics
    paddle_positions: [2]f64,
    current_y_axis: [2]i8,
    last_y_axis_update_time: [2]f64,
    paddle_min_y: f64,
    paddle_max_y: f64,
    paddle_height: f64,
    paddle_width: f64,

    // Ball physics
    ball_pos: [2]f64,
    ball_delta: [2]f64,
    ball_height: f64,
    ball_width: f64,
    ball_paddle_limit_x: [2]f64,
    ball_edge_limit_y: [2]f64,

    // Timing
    game_state: GameState,
    initialization_timestamp: ?f64,
    start_timestamp: ?f64,
    frame_timestamp: f64,
    approx_frame_time: f64,
    last_speedup_timestamp: f64,

    // Game result
    winning_side: ?usize,

    // Constants
    const BALL_SPEED: f64 = 3.0;
    const PADDLE_SPEED: f64 = 10.0;
    const PADDLE_CENTER_Y: f64 = 12.0;
    const PADDLE_DISTANCE_TO_SIDES: f64 = 1.0;
    const BALL_SPEEDUP_EVERY_N_SECS: f64 = 10.0;
    const BALL_SPEEDUP_FACTOR: f64 = 1.10;
    const FRAME_EDGE_SIZE: f64 = 1.0;
    const PADDLE_INFLUENCE: f64 = 0.5;
    const PADDLE_MAX_ANGLE_RATIO: f64 = 1.5;

    pub fn init(loader: *BitmapLoader, fb: *FrameBuffer) !MainGameState {
        const bg_bitmap = try loader.loadBitmap("main_game");
        const paddle_bitmap = try loader.loadBitmap("paddle");
        const ball_bitmap = try loader.loadBitmap("ball");

        const dw = fb.width;
        const dh = fb.height;
        const ph: f64 = @floatFromInt(paddle_bitmap.height);
        const pw: f64 = @floatFromInt(paddle_bitmap.width);
        const bh: f64 = @floatFromInt(ball_bitmap.height);
        const bw: f64 = @floatFromInt(ball_bitmap.width);
        const fdw: f64 = @floatFromInt(dw);
        const fdh: f64 = @floatFromInt(dh);

        const paddle_pos_x_right = fdw - 1.0 - pw;
        const paddle_pos_x_left = 1.0;

        var state = MainGameState{
            .display_width = dw,
            .display_height = dh,
            .paddles = .{
                Sprite{ .bitmap = paddle_bitmap, .x = @intFromFloat(paddle_pos_x_left), .y = 0 },
                Sprite{ .bitmap = paddle_bitmap, .x = @intFromFloat(paddle_pos_x_right), .y = 0 },
            },
            .ball = Sprite{ .bitmap = ball_bitmap },
            .sprites_background = bg_bitmap.pixels,
            .paddle_positions = .{ 0, 0 },
            .current_y_axis = .{ 0, 0 },
            .last_y_axis_update_time = .{ 0, 0 },
            .paddle_min_y = FRAME_EDGE_SIZE,
            .paddle_max_y = fdh - ph - FRAME_EDGE_SIZE,
            .paddle_height = ph,
            .paddle_width = pw,
            .ball_pos = .{ 0, 0 },
            .ball_delta = .{ 0, 0 },
            .ball_height = bh,
            .ball_width = bw,
            .ball_paddle_limit_x = .{
                paddle_pos_x_left + pw,
                paddle_pos_x_right - bw,
            },
            .ball_edge_limit_y = .{
                1.0, // TOP
                fdh - bh, // BOTTOM
            },
            .game_state = .initializing,
            .initialization_timestamp = null,
            .start_timestamp = null,
            .frame_timestamp = currentTime(),
            .approx_frame_time = 0,
            .last_speedup_timestamp = 0,
            .winning_side = null,
        };
        state.resetGame();
        return state;
    }

    pub fn onEnter(self: *MainGameState, fb: *FrameBuffer) void {
        if (self.sprites_background) |bg| {
            fb.setBackgroundFrame(bg);
        }
        self.resetGame();
    }

    pub fn onEvent(self: *MainGameState, ev: Event) void {
        const EvType = @import("event.zig").EventType;
        const Device = @import("event.zig").Device;

        const paddle_idx: ?usize = switch (ev.device) {
            .joy_1 => LEFT,
            .joy_2 => RIGHT,
            else => null,
        };

        switch (self.game_state) {
            .waiting => {
                if (ev.event_type == EvType.joy_button_1 and ev.value == 0) {
                    self.startGame();
                }
            },
            .playing => {
                if (ev.event_type == EvType.joy_axis_y) {
                    if (paddle_idx) |pi| {
                        if (ev.value == 0) {
                            self.updatePaddlePosition(pi);
                        }
                        self.current_y_axis[pi] = ev.value;
                    }
                }
            },
            .game_over => {
                if (ev.event_type == EvType.joy_button_1 and ev.value == 0) {
                    self.resetGame();
                }
            },
            .initializing => {},
        }
        _ = Device;
    }

    pub fn onFrameUpdate(self: *MainGameState, fb: *FrameBuffer) !void {
        self.frame_timestamp = currentTime();
        switch (self.game_state) {
            .initializing => {
                if (self.initialization_timestamp) |ts| {
                    self.approx_frame_time = self.frame_timestamp - ts;
                    std.debug.print("approxFrameTime: {d:.6}\n", .{self.approx_frame_time});
                    self.game_state = .waiting;
                } else {
                    self.initialization_timestamp = self.frame_timestamp;
                }
            },
            .playing => {
                // Update paddles
                self.updatePaddlePosition(LEFT);
                self.updatePaddlePosition(RIGHT);
                self.updatePaddleSpritePositions();
                self.updateBallPosition();
            },
            .waiting, .game_over => {},
        }

        // Render all visible sprites
        try fb.drawSpriteIfVisible(&self.paddles[LEFT]);
        try fb.drawSpriteIfVisible(&self.paddles[RIGHT]);
        try fb.drawSpriteIfVisible(&self.ball);
    }

    fn resetGame(self: *MainGameState) void {
        const fdh: f64 = @floatFromInt(self.display_height);
        const paddle_middle_y = (fdh / 2.0) - (self.paddle_height / 2.0);
        self.paddle_positions = .{ paddle_middle_y, paddle_middle_y };
        self.current_y_axis = .{ 0, 0 };
        self.last_y_axis_update_time = .{ 0, 0 };
        self.winning_side = null;
        self.ball_pos = .{ self.ball_paddle_limit_x[LEFT], PADDLE_CENTER_Y };
        self.ball_delta = .{ 0, 0 };
        self.game_state = .initializing;
        self.initialization_timestamp = null;
        self.start_timestamp = null;
        self.updateBallSpritePosition();
        self.updatePaddleSpritePositions();
    }

    fn startGame(self: *MainGameState) void {
        self.ball_delta = .{
            PADDLE_SPEED * self.approx_frame_time,
            PADDLE_SPEED * self.approx_frame_time,
        };
        self.game_state = .playing;
        self.start_timestamp = self.frame_timestamp;
        self.last_speedup_timestamp = self.frame_timestamp;
        std.debug.print("Starting game!\n", .{});
    }

    fn updateBallPosition(self: *MainGameState) void {
        self.ball_pos[X] += self.ball_delta[X];
        self.ball_pos[Y] += self.ball_delta[Y];

        // Edge collisions
        if (self.ball_pos[Y] <= self.ball_edge_limit_y[TOP]) {
            self.bounceBallOnEdge(TOP);
        } else if (self.ball_pos[Y] >= self.ball_edge_limit_y[BOTTOM]) {
            self.bounceBallOnEdge(BOTTOM);
        }

        // Paddle collisions
        if (self.ball_pos[X] <= self.ball_paddle_limit_x[LEFT]) {
            if (self.ballHitPaddle(LEFT)) {
                self.bounceBallOnPaddle(LEFT);
            } else {
                self.playerWon(RIGHT);
            }
        } else if (self.ball_pos[X] >= self.ball_paddle_limit_x[RIGHT]) {
            if (self.ballHitPaddle(RIGHT)) {
                self.bounceBallOnPaddle(RIGHT);
            } else {
                self.playerWon(LEFT);
            }
        }

        self.updateBallSpritePosition();
    }

    fn updateBallSpritePosition(self: *MainGameState) void {
        self.ball.moveTo(@intFromFloat(self.ball_pos[X]), @intFromFloat(self.ball_pos[Y]));
    }

    fn updatePaddleSpritePositions(self: *MainGameState) void {
        self.paddles[LEFT].y = @intFromFloat(self.paddle_positions[LEFT]);
        self.paddles[RIGHT].y = @intFromFloat(self.paddle_positions[RIGHT]);
    }

    fn updatePaddlePosition(self: *MainGameState, paddle: usize) void {
        const now = self.frame_timestamp;
        const elapsed = now - self.last_y_axis_update_time[paddle];
        const axis: f64 = @floatFromInt(self.current_y_axis[paddle]);
        var new_pos = self.paddle_positions[paddle] + (PADDLE_SPEED * elapsed * axis);
        new_pos = @max(self.paddle_min_y, @min(new_pos, self.paddle_max_y));
        self.paddle_positions[paddle] = new_pos;
        self.last_y_axis_update_time[paddle] = now;
    }

    fn ballHitPaddle(self: *const MainGameState, paddle: usize) bool {
        const ball_y = self.ball_pos[Y];
        const paddle_y_min = self.paddle_positions[paddle] - self.ball_height;
        const paddle_y_max = self.paddle_positions[paddle] + self.paddle_height + self.ball_height;
        return (ball_y > paddle_y_min and ball_y < paddle_y_max);
    }

    fn bounceBallOnPaddle(self: *MainGameState, paddle: usize) void {
        const bounce_back = self.ball_paddle_limit_x[paddle] - self.ball_pos[X];
        self.ball_pos[X] = self.ball_paddle_limit_x[paddle] + bounce_back;
        self.ball_delta[X] *= -1.0;

        // Paddle movement influences ball angle
        const paddle_direction = self.current_y_axis[paddle];
        if (paddle_direction != 0) {
            const influence = @abs(self.ball_delta[X]) * PADDLE_INFLUENCE;
            const dir_f: f64 = @floatFromInt(paddle_direction);
            self.ball_delta[Y] += influence * dir_f;

            // Cap Y speed to prevent near-vertical trajectories
            const max_y = @abs(self.ball_delta[X]) * PADDLE_MAX_ANGLE_RATIO;
            const abs_y = @abs(self.ball_delta[Y]);
            if (abs_y > max_y) {
                const sign: f64 = if (self.ball_delta[Y] >= 0) 1.0 else -1.0;
                self.ball_delta[Y] = sign * max_y;
            }
        }

        self.maybeSpeedUpBall();
    }

    fn bounceBallOnEdge(self: *MainGameState, edge: usize) void {
        const bounce_back = self.ball_edge_limit_y[edge] - self.ball_pos[Y];
        self.ball_pos[Y] = self.ball_edge_limit_y[edge] + bounce_back;
        self.ball_delta[Y] *= -1.0;
    }

    fn playerWon(self: *MainGameState, side: usize) void {
        const side_name = if (side == LEFT) "Left" else "Right";
        std.debug.print("{s} side won!\n", .{side_name});
        self.game_state = .game_over;
        self.winning_side = side;
    }

    fn maybeSpeedUpBall(self: *MainGameState) void {
        const time_since_last = self.frame_timestamp - self.last_speedup_timestamp;
        if (time_since_last >= BALL_SPEEDUP_EVERY_N_SECS) {
            self.ball_delta[X] *= BALL_SPEEDUP_FACTOR;
            self.ball_delta[Y] *= BALL_SPEEDUP_FACTOR;
            self.last_speedup_timestamp = self.frame_timestamp;
            std.debug.print("Speeding up ball!\n", .{});
        }
    }

    fn currentTime() f64 {
        const ns = std.time.nanoTimestamp();
        return @as(f64, @floatFromInt(ns)) / 1_000_000_000.0;
    }
};
