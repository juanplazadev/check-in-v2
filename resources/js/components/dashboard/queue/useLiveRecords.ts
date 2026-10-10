import { useState } from 'react';

/** How long an arrival keeps its NEW tag. */
const FRESH_MS = 60_000;
/** Matches the exit transition on cards and rail rows. */
export const LEAVE_MS = 400;

interface LiveRecord {
    uuid: string;
}

interface LiveRecords<T> {
    records: T[];
    fresh: Set<string>;
    leaving: Set<string>;
    /**
     * Applies one feed event: upserts the record while `belongs` says it still belongs on the
     * screen, otherwise animates it out and drops it.
     */
    apply: (type: 'created' | 'updated', record: T, belongs: boolean) => void;
}

/**
 * A list seeded from props and kept current by a websocket feed. A reload (the reconnect
 * catch-up) replaces the props, and the list starts over from them.
 */
export function useLiveRecords<T extends LiveRecord>(
    seed: T[],
): LiveRecords<T> {
    const [records, setRecords] = useState(seed);
    const [seeded, setSeeded] = useState(seed);
    const [fresh, setFresh] = useState<Set<string>>(() => new Set());
    const [leaving, setLeaving] = useState<Set<string>>(() => new Set());

    if (seed !== seeded) {
        setSeeded(seed);
        setRecords(seed);
    }

    const markFor = (
        setter: typeof setFresh,
        uuid: string,
        ms: number,
        then?: () => void,
    ) => {
        setter((previous) => new Set(previous).add(uuid));
        window.setTimeout(() => {
            setter((previous) => {
                const next = new Set(previous);
                next.delete(uuid);

                return next;
            });
            then?.();
        }, ms);
    };

    const apply = (
        type: 'created' | 'updated',
        record: T,
        belongs: boolean,
    ) => {
        if (!belongs) {
            markFor(setLeaving, record.uuid, LEAVE_MS, () =>
                setRecords((previous) =>
                    previous.filter((item) => item.uuid !== record.uuid),
                ),
            );

            return;
        }

        setRecords((previous) =>
            previous.some((item) => item.uuid === record.uuid)
                ? previous.map((item) =>
                      item.uuid === record.uuid ? record : item,
                  )
                : [...previous, record],
        );

        if (type === 'created') {
            markFor(setFresh, record.uuid, FRESH_MS);
        }
    };

    return { records, fresh, leaving, apply };
}
