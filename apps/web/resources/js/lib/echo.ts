import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher, { type ChannelAuthorizationCallback } from 'pusher-js';

/**
 * Payload of App\Events\Shared\ResourcesChanged: table name => changed ids, null when many or unknown.
 * Listeners get null instead after a reconnect, when events may have been missed.
 */
export type ChangedResources = Record<string, Array<number | string> | null>;

type Listener = (resources: ChangedResources | null) => void;

const listeners = new Set<Listener>();
let echo: Echo<'reverb'> | null = null;
let disconnectTimer: ReturnType<typeof setTimeout> | undefined;

/**
 * Joins private-changes while at least one listener is subscribed. Without VITE_REVERB_APP_KEY
 * (Reverb not set up) the app works as before, without live updates.
 */
function connect(): void {
    const key = import.meta.env.VITE_REVERB_APP_KEY;

    if (echo || !key) {
        return;
    }

    const port = Number(import.meta.env.VITE_REVERB_PORT ?? 80);
    echo = new Echo({
        broadcaster: 'reverb',
        Pusher,
        key,
        wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
        wsPort: port,
        wssPort: port,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        // The session cookie authorizes the channel; axios sends the XSRF token.
        authorizer: (channel: { name: string }) => ({
            authorize: (socketId: string, callback: ChannelAuthorizationCallback) => {
                axios
                    .post<Parameters<ChannelAuthorizationCallback>[1]>('/broadcasting/auth', { socket_id: socketId, channel_name: channel.name })
                    .then((response) => callback(null, response.data))
                    .catch((error: Error) => callback(error, null));
            },
        }),
    });

    let connectedBefore = false;
    echo.connector.pusher.connection.bind('connected', () => {
        if (connectedBefore) {
            listeners.forEach((listener) => listener(null));
        }
        connectedBefore = true;
    });

    echo.private('changes').listen('.resources.changed', (event: { resources: ChangedResources }) => {
        listeners.forEach((listener) => listener(event.resources));
    });
}

/**
 * The connection is dropped a few seconds after the last listener leaves (sign-out, pages
 * without live data), and kept when the next page subscribes right away.
 */
export function listenForChanges(listener: Listener): () => void {
    clearTimeout(disconnectTimer);
    listeners.add(listener);
    connect();

    return () => {
        listeners.delete(listener);

        if (listeners.size === 0) {
            disconnectTimer = setTimeout(() => {
                echo?.disconnect();
                echo = null;
            }, 5000);
        }
    };
}