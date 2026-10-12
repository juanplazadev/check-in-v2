import { useForm } from '@inertiajs/react';
import { StickyNote } from 'lucide-react';
import type { FormEvent } from 'react';

import CheckInNoteController from '@/actions/App/Http/Controllers/Dashboard/CheckInNoteController';
import Card from '@/components/dashboard/Card';
import type { CheckInNote } from '@/components/dashboard/types';
import { Textarea } from '@/components/ui/textarea';
import { formatTimeAgo, initialsOf } from '@/lib/dashboard/format';

/**
 * Internal staff notes on a check-in, newest first. Notes are add-only; each one also lands on
 * the check-in's history timeline, which the redirect back refreshes along with this list.
 */
export default function NotesCard({
    checkInUuid,
    notes,
}: {
    checkInUuid: string;
    notes: CheckInNote[];
}) {
    const form = useForm({ body: '' });

    const addNote = (event: FormEvent) => {
        event.preventDefault();

        if (form.processing || form.data.body.trim() === '') {
            return;
        }

        form.submit(CheckInNoteController(checkInUuid), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <Card id="notes" title="Notes" className="scroll-mt-24">
            <form onSubmit={addNote} className="px-5 pt-4">
                <Textarea
                    rows={3}
                    maxLength={2000}
                    placeholder="Add an internal note for staff. Drivers never see notes."
                    value={form.data.body}
                    onChange={(event) =>
                        form.setData('body', event.target.value)
                    }
                    aria-invalid={form.errors.body !== undefined}
                    onKeyDown={(event) => {
                        if (
                            event.key === 'Enter' &&
                            (event.metaKey || event.ctrlKey)
                        ) {
                            addNote(event);
                        }
                    }}
                />
                {form.errors.body !== undefined && (
                    <p className="mt-1 text-xs text-red-500">
                        {form.errors.body}
                    </p>
                )}
                <div className="mt-2 flex items-center justify-end gap-3">
                    <span className="hidden text-xs text-gray-400 sm:inline dark:text-gray-500">
                        Ctrl + Enter to add
                    </span>
                    <button
                        type="submit"
                        disabled={
                            form.processing || form.data.body.trim() === ''
                        }
                        className="btn-sm bg-gray-900 text-gray-100 hover:bg-gray-800 disabled:opacity-50 dark:bg-gray-100 dark:text-gray-800 dark:hover:bg-white"
                    >
                        Add note
                    </button>
                </div>
            </form>

            {notes.length === 0 ? (
                <div className="flex flex-col items-center gap-2 px-5 py-8 text-center">
                    <StickyNote className="size-6 text-gray-300 dark:text-gray-600" />
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        No notes yet.
                    </p>
                </div>
            ) : (
                <ul className="mt-4 divide-y divide-gray-100 border-t border-gray-100 dark:divide-gray-700/60 dark:border-gray-700/60">
                    {notes.map((note) => (
                        <li key={note.id} className="flex gap-3 px-5 py-4">
                            <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-green/12 text-xs font-semibold text-brand-green">
                                {initialsOf(note.author ?? 'Deleted user')}
                            </span>
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-baseline gap-x-2 text-sm">
                                    <span className="font-semibold text-gray-800 dark:text-gray-100">
                                        {note.author ?? 'Deleted user'}
                                    </span>
                                    <span className="text-xs text-gray-400 dark:text-gray-500">
                                        {formatTimeAgo(note.createdAt)}
                                    </span>
                                </div>
                                <p className="mt-1 text-sm whitespace-pre-line text-gray-600 dark:text-gray-300">
                                    {note.body}
                                </p>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </Card>
    );
}
