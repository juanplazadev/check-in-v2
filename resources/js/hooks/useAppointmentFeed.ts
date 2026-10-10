import { useEcho } from '@laravel/echo-react';
import { useEffect, useRef } from 'react';

import type { AppointmentRecord } from '@/components/dashboard/types';
import type { FeedStatus } from '@/hooks/useFeedConnection';
import { useFeedConnection } from '@/hooks/useFeedConnection';

/** Mirrors App\Dashboard\BroadcastChannels::APPOINTMENTS. */
const CHANNEL = 'dashboard.appointments';

/** Mirrors broadcastAs() on App\Events\AppointmentCreated and AppointmentUpdated. */
const CREATED = '.appointment.created';
const UPDATED = '.appointment.updated';

export type AppointmentFeedEvent = {
    type: 'created' | 'updated';
    appointment: AppointmentRecord;
};

interface FeedPayload {
    appointment: AppointmentRecord;
}

interface AppointmentFeedOptions {
    onEvent: (event: AppointmentFeedEvent) => void;
    /** Called when the socket comes back after a drop; see useCheckInFeed. */
    onReconnect?: () => void;
}

/**
 * Subscribes to the staff appointment feed: new bookings, and status or time changes to existing
 * ones. Only the live queue's arriving rail listens; bookings raise no alert of their own.
 */
export function useAppointmentFeed({
    onEvent,
    onReconnect,
}: AppointmentFeedOptions): FeedStatus {
    const onEventRef = useRef(onEvent);

    useEffect(() => {
        onEventRef.current = onEvent;
    });

    useEcho<FeedPayload>(CHANNEL, CREATED, ({ appointment }) =>
        onEventRef.current({ type: 'created', appointment }),
    );
    useEcho<FeedPayload>(CHANNEL, UPDATED, ({ appointment }) =>
        onEventRef.current({ type: 'updated', appointment }),
    );

    return useFeedConnection(onReconnect);
}
