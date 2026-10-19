const BASE = import.meta.env.VITE_API_URL;

export function getToken() { return localStorage.getItem('token'); }
export function setToken(t: string | null) {
  t ? localStorage.setItem('token', t) : localStorage.removeItem('token');
}

export async function api<T>(path: string, init: RequestInit = {}): Promise<T> {
  const res = await fetch(`${BASE}${path}`, {
    ...init,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(getToken() ? { Authorization: `Bearer ${getToken()}` } : {}),
      ...init.headers,
    },
  });
  if (res.status === 204) return undefined as T;
  const body = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(body.message ?? body.error?.message ?? `HTTP ${res.status}`);
  return body.data ?? body;
}