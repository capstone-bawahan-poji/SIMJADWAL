import { computed, readonly, ref } from 'vue';
import { api, getToken, setToken } from '@/lib/http';
import type { AuthToken, AuthUser } from '@/types';

const user = ref<AuthUser | null>(null);
let restored = false;

export function useAuth() {
  async function login(email: string, password: string) {
    const res = await api<AuthToken>('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password, device_name: 'simjadwal-desktop' }),
    });
    setToken(res.token);
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
    try { user.value = await api<AuthUser>('/auth/me'); } catch { setToken(null); }
  }

  return {
    user: readonly(user),
    isLoggedIn: computed(() => user.value !== null),
    login, logout, restore,
  };
}
