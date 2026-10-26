import { Check, X } from 'lucide-react';
import { Fragment } from 'react';

import { formatDateTime } from '@/lib/dashboard/format';

export type StepTone = 'done' | 'current' | 'upcoming' | 'failed' | 'cancelled';

export interface ProgressStep {
    label: string;
    tone: StepTone;
    timestamp: string | null;
    /** Shown in place of the timestamp when there is none yet. */
    note?: string;
}

const DOT_CLASSES: Record<StepTone, string> = {
    done: 'border-brand-green bg-brand-green text-white',
    current:
        'border-amber-500 bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400',
    upcoming:
        'border-gray-200 bg-white text-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-600',
    failed: 'border-rose-500 bg-rose-500 text-white',
    cancelled:
        'border-gray-400 bg-gray-400 text-white dark:border-gray-500 dark:bg-gray-500',
};

/** A record's lifecycle as a row of three steps, as the detail page heroes show it. */
export default function ProgressTrack({ steps }: { steps: ProgressStep[] }) {
    // The dots and connectors are one full-width track; the labels sit below it in three columns
    // aligned start, center and end, so each label lines up under its dot.
    return (
        <div>
            <div className="flex items-center" aria-hidden="true">
                {steps.map((step, index) => (
                    <Fragment key={step.label}>
                        <StepDot tone={step.tone} />
                        {index < steps.length - 1 && (
                            <span
                                className={`mx-1 h-0.5 flex-1 rounded-full ${step.tone === 'done' ? 'bg-brand-green' : 'bg-gray-200 dark:bg-gray-700'}`}
                            />
                        )}
                    </Fragment>
                ))}
            </div>

            <ol className="mt-2 grid grid-cols-3 gap-2">
                {steps.map((step, index) => (
                    <li
                        key={step.label}
                        className={
                            index === 0
                                ? 'text-left'
                                : index === steps.length - 1
                                  ? 'text-right'
                                  : 'text-center'
                        }
                    >
                        <div className="text-sm font-semibold text-gray-800 dark:text-gray-100">
                            {step.label}
                        </div>
                        <div className="text-xs text-gray-500 dark:text-gray-400">
                            {step.timestamp !== null
                                ? formatDateTime(step.timestamp)
                                : (step.note ?? 'Not yet')}
                        </div>
                    </li>
                ))}
            </ol>
        </div>
    );
}

function StepDot({ tone }: { tone: StepTone }) {
    return (
        <span
            className={`flex size-6 shrink-0 items-center justify-center rounded-full border-2 ${DOT_CLASSES[tone]}`}
        >
            {tone === 'done' && <Check className="size-3.5" strokeWidth={3} />}
            {(tone === 'failed' || tone === 'cancelled') && (
                <X className="size-3.5" strokeWidth={3} />
            )}
            {tone === 'current' && (
                <span className="size-2 animate-pulse rounded-full bg-amber-500" />
            )}
        </span>
    );
}
