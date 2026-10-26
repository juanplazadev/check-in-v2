import ProgressTrack from '@/components/dashboard/ProgressTrack';
import type { ProgressStep } from '@/components/dashboard/ProgressTrack';
import type { CheckInRecord } from '@/components/dashboard/types';

/** The check-in's lifecycle as three steps: submitted, completed (or cancelled), ERP synced. */
function stepsFor(checkIn: CheckInRecord): ProgressStep[] {
    const cancelled = checkIn.status === 'cancelled';
    const completed = checkIn.status === 'completed';

    const outcome: ProgressStep = cancelled
        ? {
              label: 'Canceled',
              tone: 'cancelled',
              timestamp: checkIn.cancelledAt,
          }
        : {
              label: 'Completed',
              tone: completed ? 'done' : 'current',
              timestamp: checkIn.completedAt,
              note: completed ? undefined : 'Waiting on shipping',
          };

    const erp: ProgressStep = {
        label: 'ERP synced',
        timestamp: checkIn.erpSyncedAt,
        tone:
            checkIn.erpStatus === 'synced'
                ? 'done'
                : checkIn.erpStatus === 'failed'
                  ? 'failed'
                  : completed
                    ? 'current'
                    : 'upcoming',
        note: checkIn.erpStatus === 'failed' ? 'Sync failed' : undefined,
    };

    return [
        { label: 'Submitted', tone: 'done', timestamp: checkIn.createdAt },
        outcome,
        erp,
    ];
}

export default function CheckInProgress({
    checkIn,
}: {
    checkIn: CheckInRecord;
}) {
    return <ProgressTrack steps={stepsFor(checkIn)} />;
}
