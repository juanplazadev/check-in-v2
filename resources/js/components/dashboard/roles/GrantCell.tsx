import { Check, Minus, Plus } from 'lucide-react';

/**
 * One role's hold on one permission. Editable cells toggle on click and show what saving would
 * do: a filled green plus for a pending grant, a red minus for a pending revoke.
 */
export default function GrantCell({
    held,
    pending,
    editable,
    assignable,
    permissionLabel,
    roleLabel,
    onToggle,
}: {
    /** Granted as stored in the database. */
    held: boolean;
    /** Granted once the pending changes are saved. */
    pending: boolean;
    editable: boolean;
    /** False for a permission this role can never hold, such as the driver account on staff. */
    assignable: boolean;
    permissionLabel: string;
    roleLabel: string;
    onToggle: () => void;
}) {
    if (!editable || !assignable) {
        return pending ? (
            <Check
                className={`mx-auto size-4 ${editable ? 'text-brand-green' : 'text-gray-400 dark:text-gray-500'}`}
                aria-label="Granted"
            />
        ) : (
            <Minus
                className="mx-auto size-4 text-gray-200 dark:text-gray-700"
                aria-label={
                    assignable ? 'Not granted' : 'Not available to this role'
                }
            />
        );
    }

    const willGrant = pending && !held;
    const willRevoke = !pending && held;

    const classes = willGrant
        ? 'bg-emerald-500 text-white shadow-sm shadow-emerald-500/30 hover:bg-emerald-600'
        : willRevoke
          ? 'bg-rose-500/10 text-rose-600 ring-1 ring-rose-500/40 ring-inset hover:bg-rose-500/15 dark:text-rose-400'
          : pending
            ? 'bg-brand-green/12 text-brand-green hover:bg-brand-green/20'
            : 'text-gray-300 hover:bg-gray-100 hover:text-gray-400 dark:text-gray-600 dark:hover:bg-gray-700/60 dark:hover:text-gray-400';

    const state = willGrant
        ? 'will be granted'
        : willRevoke
          ? 'will be revoked'
          : pending
            ? 'granted'
            : 'not granted';

    return (
        <button
            type="button"
            onClick={onToggle}
            aria-pressed={pending}
            aria-label={`${permissionLabel} for ${roleLabel}: ${state}`}
            title={`${pending ? 'Revoke from' : 'Grant to'} ${roleLabel}`}
            className={`group mx-auto flex size-8 cursor-pointer items-center justify-center rounded-lg transition focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:outline-none ${classes}`}
        >
            {willGrant ? (
                <Plus className="size-4" strokeWidth={3} />
            ) : willRevoke ? (
                <Minus className="size-4" strokeWidth={3} />
            ) : pending ? (
                <Check className="size-4" strokeWidth={2.5} />
            ) : (
                <>
                    <span className="size-1.5 rounded-full bg-current group-hover:hidden" />
                    <Plus className="hidden size-4 group-hover:block" />
                </>
            )}
        </button>
    );
}
