import { invoke } from '@tauri-apps/api/core';

// Typed wrappers for Rust commands. The only module that calls invoke().
export const greet = (name: string) => invoke<string>('greet', { name });
