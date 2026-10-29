import Echo from 'laravel-echo';
import Pusher, { type ChannelAuthorizationCallback } from 'pusher-js';
import { call } from '@/lib/api/client';

export type ChangedResources = Record<string, Array<number | string> | null>;

interface Handler {
  tables: string[];
  callback: () => void;
  timer?: ReturnType<typeof setTimeout>;
}

const handlers = new Set<Handler>();
let echo: Echo<'reverb'> | null = null;

export function onChanges(tables: string[], callback: () => void): void {
  handlers.add({ tables, callback });
}

function dispatch(resources: ChangedResources | null) {
  for (const handler of handlers) {
    if (resources !== null && !handler.tables.some((table) => table in resources)) continue;
    clearTimeout(handler.timer);
    handler.timer = setTimeout(handler.callback, 300);
  }
}

export function startChanges(): void {
  const key = import.meta.env.VITE_REVERB_APP_KEY;
  if (echo || !key) return;

  const port = Number(import.meta.env.VITE_REVERB_PORT ?? 8080);
  echo = new Echo({
    broadcaster: 'reverb',
    Pusher,
    key,
    wsHost: import.meta.env.VITE_REVERB_HOST ?? 'localhost',
    wsPort: port,
    wssPort: port,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    authorizer: (channel: { name: string }) => ({
      authorize: (socketId: string, callback: ChannelAuthorizationCallback) => {
        call<Parameters<ChannelAuthorizationCallback>[1]>('broadcast_auth', { socketId, channelName: channel.name })
          .then((data) => callback(null, data))
          .catch((error: Error) => callback(error, null));
      },
    }),
  });

  let connectedBefore = false;
  echo.connector.pusher.connection.bind('connected', () => {
    if (connectedBefore) dispatch(null);
    connectedBefore = true;
  });

  echo.private('changes').listen('.resources.changed', (event: { resources: ChangedResources }) => dispatch(event.resources));
}

export function stopChanges(): void {
  echo?.disconnect();
  echo = null;
}