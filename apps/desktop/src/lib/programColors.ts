/**
 * Identity colors for study programs. Twelve tints, none red (red means a hard constraint).
 * From the 13th program the colors repeat; the program code still tells them apart.
 */
export interface ProgramColor {
  bg: string;
  border: string;
  ink: string;
}

export const PROGRAM_PALETTE: ProgramColor[] = [
  { bg: '#e6f4f1', border: '#a9d8cf', ink: '#0b5a50' }, // teal
  { bg: '#f0ecfa', border: '#cfc3ef', ink: '#4a2f94' }, // violet
  { bg: '#fdeee0', border: '#f3c79c', ink: '#8a3f08' }, // orange
  { bg: '#eef4e1', border: '#c6dca4', ink: '#3b5d12' }, // olive
  { bg: '#e6f3fb', border: '#a9d4ef', ink: '#075985' }, // sky
  { bg: '#fdf6d8', border: '#e8d27a', ink: '#713f12' }, // yellow
  { bg: '#fbeaf7', border: '#ecb8e2', ink: '#86198f' }, // magenta
  { bg: '#f4ece6', border: '#d8c0ad', ink: '#6b3e1f' }, // brown
  { bg: '#e3f6f8', border: '#9fdbe3', ink: '#155e6b' }, // cyan
  { bg: '#e9f7e4', border: '#b5e0a6', ink: '#276221' }, // lime
  { bg: '#eeeefb', border: '#c5c6ee', ink: '#3f3f9a' }, // periwinkle
  { bg: '#eff1ee', border: '#c9cfc4', ink: '#41493b' }, // stone
];

const NEUTRAL: ProgramColor = { bg: '#f5f6f8', border: '#e4e7ec', ink: '#475467' };

/** Colors follow the dataset order of study programs, so each program keeps its color on every page. */
export function programColorMap(programs: { id: number }[]): Map<number, ProgramColor> {
  return new Map(programs.map((p, i) => [p.id, PROGRAM_PALETTE[i % PROGRAM_PALETTE.length]]));
}

export function colorOf(map: Map<number, ProgramColor>, programId: number | undefined): ProgramColor {
  return (programId !== undefined && map.get(programId)) || NEUTRAL;
}
