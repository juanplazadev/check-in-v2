import { useConnectionStatus } from '@laravel/echo-react';
import { useEffect, useRef } from 'react';

export type FeedStatus = 'live' | 'connecting' | 'offline';

/**
 * The socket's state for a live feed, calling `onReconnect` when it comes back after a drop.
 * Anything pushed while it was down is gone, so callers reload their props there to catch up.
 */
export function useFeedConnection(onReconnect?: () => void): FeedStatus {
    const onReconnectRef = useRef(onReconnect);

    useEffect(() => {
        onReconnectRef.current = onReconnect;
    });

    const connection = useConnectionStatus();
    const hasConnected = useRef(false);
    const wasDown = useRef(false);

    useEffect(() => {
        if (connection === 'connected') {
            if (hasConnected.current && wasDown.current) {
                onReconnectRef.current?.();
            }

            hasConnected.current = true;
            wasDown.current = false;
        } else if (hasConnected.current) {
            wasDown.current = true;
        }
    }, [connection]);

    if (connection === 'connected') {
        return 'live';
    }

    return connection === 'connecting' || connection === 'reconnecting'
        ? 'connecting'
        : 'offline';
}
