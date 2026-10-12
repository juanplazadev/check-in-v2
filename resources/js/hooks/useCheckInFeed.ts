import { usePage } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { useEffect, useRef } from 'react';

import type { CheckInRecord } from '@/components/dashboard/types';
import type { FeedStatus } from '@/hooks/useFeedConnection';
import { useFeedConnection } from '@/hooks/useFeedConnection';

/**
 * Mirrors App\Dashboard\BroadcastChannels::CHECK_INS. Each staff member has their own channel, and
 * the server pushes to it only records at locations they may see.
 */
const channelFor = (userUuid: string): string =>
    `dashboard.check-ins.${userUuid}`;

/** Mirrors broadcastAs() on App\Events\CheckInCreated, CheckInUpdated and CheckInDeleted. */
const CREATED = '.check-in.created';
const UPDATED = '.check-in.updated';
const DELETED = '.check-in.deleted';

/** A deleted check-in no longer exists, so its event carries only the uuid. */
export type CheckInFeedEvent =
    | { type: 'created' | 'updated'; checkIn: CheckInRecord }
    | { type: 'deleted'; uuid: string };

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
    const channel = channelFor(usePage().props.auth.user.uuid);
    const onEventRef = useRef(onEvent);

    useEffect(() => {
        onEventRef.current = onEvent;
    });

    // One listener per event: Echo does not tell a multi-event callback which one fired.
    useEcho<FeedPayload>(channel, CREATED, ({ checkIn }) =>
        onEventRef.current({ type: 'created', checkIn }),
    );
    useEcho<FeedPayload>(channel, UPDATED, ({ checkIn }) =>
        onEventRef.current({ type: 'updated', checkIn }),
    );
    useEcho<{ uuid: string }>(channel, DELETED, ({ uuid }) =>
        onEventRef.current({ type: 'deleted', uuid }),
    );

    return useFeedConnection(onReconnect);
}
