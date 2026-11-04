/** "2026/2027-GANJIL" becomes "2026/2027 Ganjil". */
export function periodLabel(period: string): string {
  const [years, term] = period.split('-');
  return term ? `${years} ${term.charAt(0)}${term.slice(1).toLowerCase()}` : period;
}

export function dateTime(value: string | number | null): string {
  if (value === null) return '-';
  return new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
}

export function runLabel(run: { operator: string; run_index: number }): string {
  return `${run.operator} run ${run.run_index + 1}`;
}

export function classLetter(n: number): string {
  return String.fromCharCode(64 + n);
}
