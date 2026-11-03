import type { ConstraintStatus } from '@/types/scheduling';

export const CONSTRAINT_STATUS: Record<ConstraintStatus, { label: string; tone: 'neutral' | 'warning' | 'success' }> = {
  draft: { label: 'Draf', tone: 'neutral' },
  submitted: { label: 'Menunggu peninjauan', tone: 'warning' },
  accepted: { label: 'Diterima', tone: 'success' },
};
