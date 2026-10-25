import type { InertiaFormProps } from '@inertiajs/react';
import { PlusIcon, Trash2Icon } from 'lucide-react';

import { Field, FieldError } from '@/components/ui/field';
import { Input } from '@/components/ui/input';

export interface EmailsData {
    email_addresses: string[];
}

/** Matches ValidatesNotificationEmails::MAX_EMAIL_ADDRESSES. */
const MAX_EMAIL_ADDRESSES = 10;

/** A location's notification addresses as a list of inputs, one per address. */
export default function EmailAddressInputs({
    form,
    empty,
}: {
    form: InertiaFormProps<EmailsData>;
    /** Shown when the list is cleared, saying who will not be emailed. */
    empty: string;
}) {
    const errors = form.errors as Record<string, string | undefined>;
    const emails = form.data.email_addresses;

    const setEmails = (next: string[]) => form.setData('email_addresses', next);

    return (
        <div className="space-y-3">
            {emails.length === 0 && (
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    {empty}
                </p>
            )}

            {emails.map((email, index) => {
                const error = errors[`email_addresses.${index}`];

                return (
                    <Field key={index} data-invalid={!!error || undefined}>
                        <div className="flex items-center gap-2">
                            <Input
                                type="email"
                                inputMode="email"
                                autoComplete="off"
                                aria-label={`Email address ${index + 1}`}
                                aria-invalid={!!error || undefined}
                                value={email}
                                autoFocus={
                                    index === emails.length - 1 && email === ''
                                }
                                onChange={(event) =>
                                    setEmails(
                                        emails.map((current, i) =>
                                            i === index
                                                ? event.target.value
                                                : current,
                                        ),
                                    )
                                }
                            />
                            <button
                                type="button"
                                onClick={() =>
                                    setEmails(
                                        emails.filter((_, i) => i !== index),
                                    )
                                }
                                aria-label={`Remove ${email || `email address ${index + 1}`}`}
                                className="shrink-0 cursor-pointer rounded-md p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700/60 dark:hover:text-gray-200"
                            >
                                <Trash2Icon className="size-4" />
                            </button>
                        </div>
                        <FieldError>{error}</FieldError>
                    </Field>
                );
            })}

            <FieldError>{errors.email_addresses}</FieldError>

            {emails.length < MAX_EMAIL_ADDRESSES && (
                <button
                    type="button"
                    onClick={() => setEmails([...emails, ''])}
                    className="inline-flex cursor-pointer items-center gap-1 text-sm font-medium text-brand-green hover:underline"
                >
                    <PlusIcon className="size-4" />
                    Add email
                </button>
            )}
        </div>
    );
}
