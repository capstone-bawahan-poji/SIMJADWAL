import { computed, readonly, ref } from 'vue';
import { api, getToken, setToken } from '@/lib/http';
import type { AuthToken, AuthUser, Role } from '@/types';

const DESKTOP_ROLES: Role[] = ['superadmin', 'admin_fakultas'];
const canUseDesktop = (u: AuthUser) => u.role !== null && DESKTOP_ROLES.includes(u.role);

const user = ref<AuthUser | null>(null);
let restored = false;

export function useAuth() {
  async function login(email: string, password: string) {
    const res = await api<AuthToken>('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password, device_name: 'simjadwal-desktop' }),
    });
    setToken(res.token);
    if (!canUseDesktop(res.user)) {
      await logout();
      throw new Error('Akun ini tidak dapat memakai aplikasi desktop.');
    }
    user.value = res.user;
  }

  async function logout() {
    try { await api('/auth/logout', { method: 'POST' }); } catch {
      
    }
    setToken(null);
    user.value = null;
  }

  async function restore() {
    if (restored) return;
    restored = true;
    if (!getToken()) return;
    try {
      const me = await api<AuthUser>('/auth/me');
      if (canUseDesktop(me)) user.value = me;
      else setToken(null);
    } catch { setToken(null); }
  }

  return {
    user: readonly(user),
    isLoggedIn: computed(() => user.value !== null),
    login, logout, restore,
  };
}
