import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';

import { show } from '@/actions/App/Http/Controllers/Dashboard/LocationWizardController';
import type {
    LocationWizardStep,
    LocationWizardStepValue,
} from '@/components/dashboard/types';

/**
 * The wizard's numbered steps on a line, ported from Mosaic's OnboardingProgress. Saved steps link
 * back for editing; later ones are plain numbers, since the server sends an early visit back.
 */
export default function WizardProgress({
    steps,
    current,
}: {
    steps: LocationWizardStep[];
    current: LocationWizardStepValue;
}) {
    const currentIndex = steps.findIndex((step) => step.value === current);

    return (
        <nav aria-label="Add location steps" className="pb-8">
            <div className="relative">
                <div
                    aria-hidden
                    className="absolute top-3 left-0 -mt-px h-0.5 w-full bg-gray-200 dark:bg-gray-700/60"
                />
                <ol className="relative flex w-full justify-between">
                    {steps.map((step, index) => {
                        const isCurrent = index === currentIndex;
                        const isReached =
                            index <= currentIndex || step.completed;
                        const dot = `flex size-6 items-center justify-center rounded-full text-xs font-semibold ${
                            isReached
                                ? 'bg-brand-green text-white'
                                : 'bg-white text-gray-500 ring-1 ring-gray-200 dark:bg-gray-900 dark:text-gray-400 dark:ring-gray-700/60'
                        }`;
                        const content =
                            step.completed && !isCurrent ? (
                                <Check className="size-3.5" aria-hidden />
                            ) : (
                                index + 1
                            );

                        return (
                            <li
                                key={step.value}
                                className="flex flex-col items-center gap-2"
                                aria-current={isCurrent ? 'step' : undefined}
                            >
                                {step.completed && !isCurrent ? (
                                    <Link
                                        href={show.url(step.value)}
                                        className={dot}
                                        aria-label={`${step.label} (saved)`}
                                    >
                                        {content}
                                    </Link>
                                ) : (
                                    <span className={dot}>{content}</span>
                                )}
                                <span
                                    className={`text-xs font-medium ${
                                        isCurrent
                                            ? 'text-gray-800 dark:text-gray-100'
                                            : 'hidden text-gray-500 sm:block dark:text-gray-400'
                                    }`}
                                >
                                    {step.label}
                                </span>
                            </li>
                        );
                    })}
                </ol>
            </div>
        </nav>
    );
}
