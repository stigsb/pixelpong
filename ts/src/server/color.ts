export enum Color {
  BLACK = 0,
  WHITE = 1,
  RED = 2,
  CYAN = 3,
  PURPLE = 4,
  GREEN = 5,
  BLUE = 6,
  YELLOW = 7,
  ORANGE = 8,
  BROWN = 9,
  LIGHT_RED = 10,
  DARK_GREY = 11,
  GREY = 12,
  LIGHT_GREEN = 13,
  LIGHT_BLUE = 14,
  LIGHT_GREY = 15,
  TRANSPARENT = -1,
}

const palette: Record<number, string> = {
  [Color.BLACK]: "#000000",
  [Color.WHITE]: "#fcf9fc",
  [Color.RED]: "#933a4c",
  [Color.CYAN]: "#b6fafa",
  [Color.PURPLE]: "#d27ded",
  [Color.GREEN]: "#6acf6f",
  [Color.BLUE]: "#4f44d8",
  [Color.YELLOW]: "#fbfb8b",
  [Color.ORANGE]: "#d89c5b",
  [Color.BROWN]: "#7f5307",
  [Color.LIGHT_RED]: "#ef839f",
  [Color.DARK_GREY]: "#575753",
  [Color.GREY]: "#a3a7a7",
  [Color.LIGHT_GREEN]: "#b7fbbf",
  [Color.LIGHT_BLUE]: "#a397ff",
  [Color.LIGHT_GREY]: "#d0d0d0",
};

export function getPalette(): Readonly<Record<number, string>> {
  return palette;
}
