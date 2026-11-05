import { invoke } from '@tauri-apps/api/core';

export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public code: string,
    public errors: Record<string, unknown> = {},
  ) {
    super(message);
  }
}

let unauthenticated: () => void = () => {};

export function onUnauthenticated(handler: () => void) {
  unauthenticated = handler;
}

export async function call<T>(command: string, args?: Record<string, unknown>): Promise<T> {
  try {
    return await invoke<T>(command, args);
  } catch (e) {
    const error = toApiError(e);
    if (error.code === 'UNAUTHENTICATED') unauthenticated();
    throw error;
  }
}

function toApiError(e: unknown): ApiError {
  if (e && typeof e === 'object' && 'code' in e && 'message' in e) {
    const body = e as { status?: number; code: string; message: string; errors?: Record<string, unknown> };
    return new ApiError(body.message, body.status ?? 0, body.code, body.errors ?? {});
  }
  return new ApiError(String(e), 0, 'APP_ERROR');
}