import { Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight } from 'lucide-react';
import type { ReactNode } from 'react';

import { show } from '@/actions/App/Http/Controllers/Dashboard/LocationWizardController';
import { SECONDARY_BUTTON } from '@/components/dashboard/RecordActionDialogs';
import type { LocationWizardStepValue } from '@/components/dashboard/types';

/** The brand button, as on the check-in and appointment toolbars. */
export const PRIMARY_BUTTON =
    'btn gap-2 bg-brand-green text-white hover:bg-brand-green/90 disabled:opacity-50';

/** The step's action bar: Back on the left, any extra actions and the submit button on the right. */
export default function WizardFooter({
    previous,
    processing,
    disabled = false,
    label = 'Next',
    processingLabel = 'Saving…',
    icon = <ArrowRight className="size-4" aria-hidden />,
    type = 'submit',
    onClick,
    extra,
}: {
    /** The step the Back button returns to; none on the first step. */
    previous?: LocationWizardStepValue;
    processing: boolean;
    disabled?: boolean;
    label?: string;
    processingLabel?: string;
    /** Shown after the label. */
    icon?: ReactNode;
    /** A button outside a form (Review) uses onClick instead of submitting. */
    type?: 'submit' | 'button';
    onClick?: () => void;
    /** Extra actions shown before the submit button, such as Skip. */
    extra?: ReactNode;
}) {
    return (
        <div className="mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-5 dark:border-gray-700/60">
            {previous ? (
                <Link href={show.url(previous)} className={SECONDARY_BUTTON}>
                    <ArrowLeft className="size-4" aria-hidden />
                    Back
                </Link>
            ) : (
                <span />
            )}
            <div className="flex flex-wrap items-center gap-3">
                {extra}
                <button
                    type={type}
                    onClick={onClick}
                    disabled={processing || disabled}
                    className={PRIMARY_BUTTON}
                >
                    {processing ? processingLabel : label}
                    {!processing && icon}
                </button>
            </div>
        </div>
    );
}
