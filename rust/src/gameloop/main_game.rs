use crate::bitmap::Bitmap;
use crate::bitmap::loader::BitmapLoader;
use crate::bitmap::sprite::Sprite;
use crate::frame::FrameBuffer;
use crate::gameloop::base::BaseGameLoop;
use crate::gameloop::{GameLoop, GameLoopTransition};
use crate::server::event::{self, Event};

const BALL_SPEED: f64 = 3.0;
const PADDLE_SPEED: f64 = 10.0;
const BALL_SPEEDUP_EVERY_N_SECS: f64 = 10.0;
const BALL_SPEEDUP_FACTOR: f64 = 1.10;
const FRAME_EDGE_SIZE: f64 = 1.0;
const PADDLE_INFLUENCE: f64 = 0.5;

const LEFT: usize = 0;
const RIGHT: usize = 1;
const TOP: usize = 0;
const BOTTOM: usize = 1;
const X: usize = 0;
const Y: usize = 1;

#[derive(Debug, Clone, Copy, PartialEq)]
enum GameState {
    Initializing,
    Waiting,
    Playing,
    GameOver,
}

pub struct MainGameLoop {
    base: BaseGameLoop,
    display_width: usize,
    display_height: usize,
    paddle_indices: [usize; 2],
    ball_index: usize,
    paddle_positions: [f64; 2],
    last_y_axis_update_time: [f64; 2],
    current_y_axis: [i32; 2],
    paddle_pos_x: [f64; 2],
    ball_pos: [f64; 2],
    ball_delta: [f64; 2],
    paddle_min_y: f64,
    paddle_max_y: f64,
    ball_paddle_limit_x: [f64; 2],
    ball_edge_limit_y: [f64; 2],
    paddle_height: f64,
    paddle_width: f64,
    ball_height: f64,
    ball_width: f64,
    game_state: GameState,
    winning_side: Option<usize>,
    initialization_timestamp: Option<f64>,
    start_timestamp: Option<f64>,
    approx_frame_time: f64,
    frame_timestamp: f64,
    last_speedup_timestamp: f64,
    initialized: bool,
}

impl MainGameLoop {
    pub fn new() -> Self {
        Self {
            base: BaseGameLoop::new(),
            display_width: 0,
            display_height: 0,
            paddle_indices: [0, 0],
            ball_index: 0,
            paddle_positions: [0.0, 0.0],
            last_y_axis_update_time: [0.0, 0.0],
            current_y_axis: [event::AXIS_NEUTRAL, event::AXIS_NEUTRAL],
            paddle_pos_x: [1.0, 45.0],
            ball_pos: [0.0, 0.0],
            ball_delta: [0.0, 0.0],
            paddle_min_y: 0.0,
            paddle_max_y: 0.0,
            ball_paddle_limit_x: [0.0, 0.0],
            ball_edge_limit_y: [0.0, 0.0],
            paddle_height: 0.0,
            paddle_width: 0.0,
            ball_height: 0.0,
            ball_width: 0.0,
            game_state: GameState::Initializing,
            winning_side: None,
            initialization_timestamp: None,
            start_timestamp: None,
            approx_frame_time: 0.0,
            frame_timestamp: 0.0,
            last_speedup_timestamp: 0.0,
            initialized: false,
        }
    }

    fn initialize_game(&mut self, fb: &dyn FrameBuffer, loader: &mut BitmapLoader) {
        self.base.background = Some(loader.load_bitmap("main_game").unwrap().clone());
        self.display_width = fb.width();
        self.display_height = fb.height();

        let left_paddle = loader.load_sprite("paddle", 0, 0).unwrap();
        let right_paddle = loader.load_sprite("paddle", 0, 0).unwrap();
        self.paddle_height = left_paddle.bitmap().height() as f64;
        self.paddle_width = left_paddle.bitmap().width() as f64;
        self.paddle_indices[LEFT] = self.base.add_sprite(left_paddle);
        self.paddle_indices[RIGHT] = self.base.add_sprite(right_paddle);

        self.paddle_min_y = FRAME_EDGE_SIZE;
        self.paddle_max_y = self.display_height as f64 - self.paddle_height - FRAME_EDGE_SIZE;

        let ball = loader.load_sprite("ball", 0, 0).unwrap();
        self.ball_height = ball.bitmap().height() as f64;
        self.ball_width = ball.bitmap().width() as f64;
        self.ball_index = self.base.add_sprite(ball);

        self.paddle_pos_x[LEFT] = 1.0;
        self.paddle_pos_x[RIGHT] = self.display_width as f64 - 1.0 - self.paddle_width;

        self.ball_paddle_limit_x[LEFT] = self.paddle_pos_x[LEFT] + self.paddle_width;
        self.ball_paddle_limit_x[RIGHT] = self.paddle_pos_x[RIGHT] - self.paddle_width;

        self.ball_edge_limit_y[TOP] = 1.0;
        self.ball_edge_limit_y[BOTTOM] = self.display_height as f64 - self.ball_height;

        self.frame_timestamp = now();
        self.initialized = true;
    }

    fn reset_game(&mut self) {
        self.last_y_axis_update_time = [0.0, 0.0];
        self.current_y_axis = [event::AXIS_NEUTRAL, event::AXIS_NEUTRAL];
        self.winning_side = None;

        let paddle_middle_y = (self.display_height as f64 / 2.0) - (self.paddle_height / 2.0);
        self.paddle_positions = [paddle_middle_y, paddle_middle_y];

        self.ball_pos[X] = self.ball_paddle_limit_x[LEFT];
        self.ball_pos[Y] = 12.0; // PADDLE_CENTER_Y
        self.ball_delta = [0.0, 0.0];

        self.game_state = GameState::Initializing;
        self.initialization_timestamp = None;
        self.start_timestamp = None;

        self.update_ball_sprite_position();
        self.update_paddle_sprite_positions();
    }

    fn start_game(&mut self) {
        self.ball_delta[X] = PADDLE_SPEED * self.approx_frame_time;
        self.ball_delta[Y] = PADDLE_SPEED * self.approx_frame_time;
        self.game_state = GameState::Playing;
        let now = self.frame_timestamp;
        self.start_timestamp = Some(now);
        self.last_speedup_timestamp = now;
        println!("Starting game!");
    }

    fn update_ball_position(&mut self) {
        self.ball_pos[X] += self.ball_delta[X];
        self.ball_pos[Y] += self.ball_delta[Y];

        if self.ball_pos[Y] <= self.ball_edge_limit_y[TOP] {
            self.bounce_ball_on_edge(TOP);
        } else if self.ball_pos[Y] >= self.ball_edge_limit_y[BOTTOM] {
            self.bounce_ball_on_edge(BOTTOM);
        }

        if self.ball_pos[X] <= self.ball_paddle_limit_x[LEFT] {
            if self.ball_hit_paddle(LEFT) {
                self.bounce_ball_on_paddle(LEFT);
            } else {
                self.player_won(RIGHT);
            }
        } else if self.ball_pos[X] >= self.ball_paddle_limit_x[RIGHT] {
            if self.ball_hit_paddle(RIGHT) {
                self.bounce_ball_on_paddle(RIGHT);
            } else {
                self.player_won(LEFT);
            }
        }

        self.update_ball_sprite_position();
    }

    fn update_ball_sprite_position(&mut self) {
        self.base.sprites[self.ball_index].move_to(
            self.ball_pos[X] as i32,
            self.ball_pos[Y] as i32,
        );
    }

    fn update_paddle_sprite_positions(&mut self) {
        for paddle in [LEFT, RIGHT] {
            self.base.sprites[self.paddle_indices[paddle]].move_to(
                self.paddle_pos_x[paddle] as i32,
                self.paddle_positions[paddle] as i32,
            );
        }
    }

    fn update_paddle_position_for_device(&mut self, device: i32) {
        let paddle = device_to_paddle(device);
        let now_us = self.frame_timestamp;
        let elapsed = now_us - self.last_y_axis_update_time[paddle];
        let new_pos = self.paddle_positions[paddle]
            + (PADDLE_SPEED * elapsed * self.current_y_axis[paddle] as f64);
        let new_pos = new_pos.clamp(self.paddle_min_y, self.paddle_max_y);
        self.paddle_positions[paddle] = new_pos;
        self.last_y_axis_update_time[paddle] = now_us;
    }

    fn player_won(&mut self, side: usize) {
        let side_name = if side == LEFT { "Left" } else { "Right" };
        println!("{} side won!", side_name);
        self.game_state = GameState::GameOver;
        self.winning_side = Some(side);
    }

    fn ball_hit_paddle(&self, paddle: usize) -> bool {
        let ball_y = self.ball_pos[Y];
        let paddle_y_min = self.paddle_positions[paddle] - self.ball_height;
        let paddle_y_max = self.paddle_positions[paddle] + self.paddle_height + self.ball_height;
        ball_y > paddle_y_min && ball_y < paddle_y_max
    }

    fn bounce_ball_on_paddle(&mut self, paddle: usize) {
        let bounce_back = self.ball_paddle_limit_x[paddle] - self.ball_pos[X];
        self.ball_pos[X] = self.ball_paddle_limit_x[paddle] + bounce_back;
        self.ball_delta[X] *= -1.0;

        let paddle_direction = self.current_y_axis[paddle];
        if paddle_direction != event::AXIS_NEUTRAL {
            let influence = self.ball_delta[X].abs() * PADDLE_INFLUENCE;
            self.ball_delta[Y] += influence * paddle_direction as f64;

            let max_y = self.ball_delta[X].abs() * 1.5;
            let min_y = self.ball_delta[X].abs() * 0.35;
            let sign = if self.ball_delta[Y] >= 0.0 { 1.0 } else { -1.0 };
            let abs_y = self.ball_delta[Y].abs();
            self.ball_delta[Y] = sign * abs_y.clamp(min_y, max_y);
        }

        self.maybe_speed_up_ball();
    }

    fn bounce_ball_on_edge(&mut self, edge: usize) {
        let bounce_back = self.ball_edge_limit_y[edge] - self.ball_pos[Y];
        self.ball_pos[Y] = self.ball_edge_limit_y[edge] + bounce_back;
        self.ball_delta[Y] *= -1.0;
    }

    fn maybe_speed_up_ball(&mut self) {
        let time_since_last = self.frame_timestamp - self.last_speedup_timestamp;
        if time_since_last >= BALL_SPEEDUP_EVERY_N_SECS {
            self.ball_delta[X] *= BALL_SPEEDUP_FACTOR;
            self.ball_delta[Y] *= BALL_SPEEDUP_FACTOR;
            self.last_speedup_timestamp = self.frame_timestamp;
            println!("Speeding up ball!");
        }
    }
}

fn device_to_paddle(device: i32) -> usize {
    match device {
        event::DEVICE_JOY_1 => LEFT,
        event::DEVICE_JOY_2 => RIGHT,
        _ => LEFT,
    }
}

fn now() -> f64 {
    std::time::SystemTime::now()
        .duration_since(std::time::UNIX_EPOCH)
        .unwrap()
        .as_secs_f64()
}

impl GameLoop for MainGameLoop {
    fn on_enter(&mut self, fb: &mut dyn FrameBuffer, loader: &mut BitmapLoader) {
        if !self.initialized {
            self.initialize_game(fb, loader);
        }
        self.base.on_enter(fb);
        self.reset_game();
    }

    fn on_frame_update(&mut self, fb: &mut dyn FrameBuffer) {
        self.frame_timestamp = now();
        match self.game_state {
            GameState::Initializing => {
                if self.initialization_timestamp.is_none() {
                    self.initialization_timestamp = Some(self.frame_timestamp);
                } else {
                    self.approx_frame_time = self.frame_timestamp - self.initialization_timestamp.unwrap();
                    println!("approxFrameTime: {}", self.approx_frame_time);
                    self.game_state = GameState::Waiting;
                }
            }
            GameState::Playing => {
                for device in [event::DEVICE_JOY_1, event::DEVICE_JOY_2] {
                    self.update_paddle_position_for_device(device);
                }
                self.update_paddle_sprite_positions();
                self.update_ball_position();
            }
            GameState::Waiting | GameState::GameOver => {}
        }
        self.base.on_frame_update(fb);
    }

    fn on_event(&mut self, event: &Event) -> Option<GameLoopTransition> {
        match self.game_state {
            GameState::Waiting => {
                if event.event_type == event::JOY_BUTTON_1 && event.value == event::BUTTON_NEUTRAL {
                    self.start_game();
                }
            }
            GameState::Playing => {
                if event.event_type == event::JOY_AXIS_Y {
                    if event.value == event::AXIS_NEUTRAL {
                        self.update_paddle_position_for_device(event.device);
                    }
                    let paddle = device_to_paddle(event.device);
                    self.current_y_axis[paddle] = event.value;
                }
            }
            GameState::GameOver => {
                if event.event_type == event::JOY_BUTTON_1 && event.value == event::BUTTON_NEUTRAL {
                    self.reset_game();
                }
            }
            _ => {}
        }
        None
    }
}
