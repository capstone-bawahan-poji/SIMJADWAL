import { computed, readonly, ref } from 'vue';
import * as auth from '@/lib/api/auth';
import { resetDataset } from '@/composables/useDataset';
import { resetGaPresets } from '@/composables/useGaPresets';
import { resetGaRunner } from '@/composables/useGaRunner';
import { resetRunSync } from '@/composables/useRunSync';
import { startChanges, stopChanges } from '@/lib/echo';
import type { AuthUser, Role } from '@/types';

// POS 040/IT10.1/OT.07/2026: each study program runs its own GA; faculties hand out room slots on the web.
const DESKTOP_ROLES: Role[] = ['superadmin', 'admin_prodi', 'admin_tpb'];
const canUseDesktop = (u: AuthUser) => u.role !== null && DESKTOP_ROLES.includes(u.role);

const LEGACY_TOKEN_KEY = 'token';

const user = ref<AuthUser | null>(null);
let restored = false;

function signedIn(me: AuthUser) {
  user.value = me;
  startChanges();
}

function forget() {
  user.value = null;
  stopChanges();
  resetRunSync();
  resetDataset();
  resetGaPresets();
  resetGaRunner();
}

function readLegacyToken(): string | null {
  try { return localStorage.getItem(LEGACY_TOKEN_KEY); } catch { return null; }
}

function removeLegacyToken() {
  try { localStorage.removeItem(LEGACY_TOKEN_KEY); } catch { /* storage unavailable */ }
}

export function useAuth() {
  async function login(email: string, password: string) {
    const me = await auth.login(email, password);
    if (!canUseDesktop(me)) {
      await logout();
      throw new Error(me.role === 'admin_fakultas'
        ? 'Penjadwalan GA kini dijalankan oleh admin program studi. Admin fakultas membagi slot ruangan dan meninjau jadwal melalui aplikasi web.'
        : 'Akun ini tidak memiliki akses ke aplikasi desktop.');
    }
    signedIn(me);
  }

  async function logout() {
    try { await auth.logout(); } catch {

    }
    forget();
  }

  async function restore() {
    if (restored) return;
    restored = true;

    const legacy = readLegacyToken();
    try {
      const me = legacy ? await auth.importToken(legacy) : await auth.me();
      if (legacy) removeLegacyToken();
      if (me && canUseDesktop(me)) signedIn(me);
      else if (me) await logout();
    } catch {

    }
  }

  return {
    user: readonly(user),
    isLoggedIn: computed(() => user.value !== null),
    login, logout, restore, forget,
  };
}