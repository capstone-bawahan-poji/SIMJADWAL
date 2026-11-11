type ProgramTone = { dot: string; cell: string; card: string };

const PROGRAM_TONES: Record<string, ProgramTone> = {
    AK: { dot: 'bg-rose-500', cell: 'border border-rose-300 bg-rose-200 text-rose-950', card: 'border-rose-300 bg-rose-50' },
    BD: { dot: 'bg-orange-500', cell: 'border border-orange-300 bg-orange-200 text-orange-950', card: 'border-orange-300 bg-orange-50' },
    FI: { dot: 'bg-amber-500', cell: 'border border-amber-300 bg-amber-200 text-amber-950', card: 'border-amber-300 bg-amber-50' },
    IF: { dot: 'bg-lime-600', cell: 'border border-lime-300 bg-lime-200 text-lime-950', card: 'border-lime-300 bg-lime-50' },
    MA: { dot: 'bg-emerald-500', cell: 'border border-emerald-300 bg-emerald-200 text-emerald-950', card: 'border-emerald-300 bg-emerald-50' },
    SI: { dot: 'bg-cyan-500', cell: 'border border-cyan-300 bg-cyan-200 text-cyan-950', card: 'border-cyan-300 bg-cyan-50' },
    ST: { dot: 'bg-violet-500', cell: 'border border-violet-300 bg-violet-200 text-violet-950', card: 'border-violet-300 bg-violet-50' },
    TB: { dot: 'bg-fuchsia-500', cell: 'border border-fuchsia-300 bg-fuchsia-200 text-fuchsia-950', card: 'border-fuchsia-300 bg-fuchsia-50' },
    TE: { dot: 'bg-sky-500', cell: 'border border-sky-300 bg-sky-200 text-sky-950', card: 'border-sky-300 bg-sky-50' },
};
const FALLBACK_TONE: ProgramTone = { dot: 'bg-gray-400', cell: 'border border-gray-300 bg-gray-200 text-gray-900', card: 'border-gray-300 bg-gray-50' };

export function programTone(code?: string | null): ProgramTone {
    return PROGRAM_TONES[code ?? ''] ?? FALLBACK_TONE;
}
