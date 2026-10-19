const BASE = import.meta.env.VITE_API_URL;

export function getToken() { return localStorage.getItem('token'); }
export function setToken(t: string | null) {
  t ? localStorage.setItem('token', t) : localStorage.removeItem('token');
}

export class ApiError extends Error {
  constructor(message: string, public status: number, public code: string | null) {
    super(message);
  }
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
  if (!res.ok) throw new ApiError(body.message ?? body.error?.message ?? `HTTP ${res.status}`, res.status, body.code ?? null);
  return body.data ?? body;
}
