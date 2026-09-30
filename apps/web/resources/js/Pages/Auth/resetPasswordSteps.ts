import type { Step } from '@/Components/shared/StepIndicator.vue';

/** Shared by ForgotPassword (step 1) and ResetPassword (step 2). */
export const RESET_PASSWORD_STEPS: Step[] = [
    { title: 'Verifikasi', description: 'Kirim link' },
    { title: 'Sandi Baru', description: 'Konfirmasi' },
];
