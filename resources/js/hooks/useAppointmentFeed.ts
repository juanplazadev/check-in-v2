import { usePage } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { useEffect, useRef } from 'react';

import type { AppointmentRecord } from '@/components/dashboard/types';
import type { FeedStatus } from '@/hooks/useFeedConnection';
import { useFeedConnection } from '@/hooks/useFeedConnection';

/**
 * Mirrors App\Dashboard\BroadcastChannels::APPOINTMENTS. Each staff member has their own channel, and
 * the server pushes to it only records at locations they may see.
 */
const channelFor = (userUuid: string): string =>
    `dashboard.appointments.${userUuid}`;

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
    const channel = channelFor(usePage().props.auth.user.uuid);
    const onEventRef = useRef(onEvent);

    useEffect(() => {
        onEventRef.current = onEvent;
    });

    useEcho<FeedPayload>(channel, CREATED, ({ appointment }) =>
        onEventRef.current({ type: 'created', appointment }),
    );
    useEcho<FeedPayload>(channel, UPDATED, ({ appointment }) =>
        onEventRef.current({ type: 'updated', appointment }),
    );

    return useFeedConnection(onReconnect);
}
