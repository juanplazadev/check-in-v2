import type { FormDataType } from '@inertiajs/core';
import { useForm } from '@inertiajs/react';
import type { InertiaFormProps } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useEffect } from 'react';

import { SectionGroup } from '@/components/dashboard/location/SectionHeader';
import type { RouteDefinition } from '@/wayfinder';

const SECONDARY_BUTTON =
    'btn-sm border-gray-200 bg-white text-gray-600 hover:border-gray-300 disabled:opacity-50 dark:border-gray-700/60 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-gray-600';

/**
 * A section of a location's settings pages that admins can edit in place: an Edit button beside
 * the title swaps the read-only view for a form of the same fields, saved with Save or dropped with
 * Cancel or Esc. Only the section's own fields are sent, since the endpoint takes each one alone.
 */
export default function EditableSection<TData extends FormDataType<TData>>({
    title,
    action,
    initialData,
    canEdit,
    editing,
    locked,
    onEditingChange,
    view,
    form: renderForm,
}: {
    title: string;
    /** The Wayfinder update action the section's fields are sent to. */
    action: RouteDefinition<'patch'>;
    /** The section's fields as they are now; the form starts from them each time it opens. */
    initialData: TData;
    canEdit: boolean;
    editing: boolean;
    /** Another section is being edited, so this one cannot open. */
    locked: boolean;
    onEditingChange: (editing: boolean) => void;
    view: ReactNode;
    form: (form: InertiaFormProps<TData>) => ReactNode;
}) {
    const form = useForm<TData>(initialData);

    const open = () => {
        // Start from the location as it is now, which may have changed since the last edit.
        form.setDefaults(initialData);
        form.reset();
        form.clearErrors();
        onEditingChange(true);
    };

    const cancel = () => {
        form.reset();
        form.clearErrors();
        onEditingChange(false);
    };

    useEffect(() => {
        if (!editing) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape' && !form.processing) {
                cancel();
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        form.submit(action, {
            preserveScroll: true,
            onSuccess: () => onEditingChange(false),
        });
    };

    const editButton =
        canEdit && !editing ? (
            <button
                type="button"
                onClick={open}
                disabled={locked}
                aria-label={`Edit ${title.toLowerCase()}`}
                className="inline-flex cursor-pointer items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent dark:text-gray-400 dark:hover:bg-gray-700/60 dark:hover:text-gray-200"
            >
                <Pencil className="size-3" />
                Edit
            </button>
        ) : undefined;

    return (
        <SectionGroup title={title} action={editButton}>
            {editing ? (
                <form onSubmit={submit} className="space-y-4">
                    {renderForm(form)}
                    <div className="flex justify-end gap-2 pt-1">
                        <button
                            type="button"
                            onClick={cancel}
                            disabled={form.processing}
                            className={SECONDARY_BUTTON}
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={form.processing || !form.isDirty}
                            className="btn-sm bg-gray-900 text-gray-100 hover:bg-gray-800 disabled:opacity-50 dark:bg-gray-100 dark:text-gray-800 dark:hover:bg-white"
                        >
                            {form.processing ? 'Saving...' : 'Save'}
                        </button>
                    </div>
                </form>
            ) : (
                view
            )}
        </SectionGroup>
    );
}
