import { useEcho } from '@laravel/echo-react';
import { useEffect, useRef } from 'react';

import type { CheckInRecord } from '@/components/dashboard/types';
import type { FeedStatus } from '@/hooks/useFeedConnection';
import { useFeedConnection } from '@/hooks/useFeedConnection';

/** Mirrors App\Dashboard\BroadcastChannels::CHECK_INS. */
const CHANNEL = 'dashboard.check-ins';

/** Mirrors broadcastAs() on App\Events\CheckInCreated and CheckInUpdated. */
const CREATED = '.check-in.created';
const UPDATED = '.check-in.updated';

export type CheckInFeedEvent = {
    type: 'created' | 'updated';
    checkIn: CheckInRecord;
};

interface FeedPayload {
    checkIn: CheckInRecord;
}

interface CheckInFeedOptions {
    onEvent: (event: CheckInFeedEvent) => void;
    /**
     * Called when the socket comes back after a drop. Anything pushed while it was down is gone,
     * so pages reload their props here to catch up.
     */
    onReconnect?: () => void;
}

/**
 * Subscribes to the staff check-in feed. Every caller shares the one private channel; Echo only
 * leaves it when the last subscriber unmounts.
 */
export function useCheckInFeed({
    onEvent,
    onReconnect,
}: CheckInFeedOptions): FeedStatus {
    const onEventRef = useRef(onEvent);

    useEffect(() => {
        onEventRef.current = onEvent;
    });

    // One listener per event: Echo does not tell a multi-event callback which one fired.
    useEcho<FeedPayload>(CHANNEL, CREATED, ({ checkIn }) =>
        onEventRef.current({ type: 'created', checkIn }),
    );
    useEcho<FeedPayload>(CHANNEL, UPDATED, ({ checkIn }) =>
        onEventRef.current({ type: 'updated', checkIn }),
    );

    return useFeedConnection(onReconnect);
}
