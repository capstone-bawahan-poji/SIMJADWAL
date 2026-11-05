import { call } from '@/lib/api/client';
import type { AuthUser } from '@/types';

export const login = (email: string, password: string) => call<AuthUser>('auth_login', { email, password });
export const logout = () => call<void>('auth_logout');
export const me = () => call<AuthUser | null>('auth_me');
export const importToken = (token: string) => call<AuthUser | null>('auth_import_token', { token });