import type { FormDataType } from '@inertiajs/core';
import type { InertiaFormProps } from '@inertiajs/react';
import type { ComponentProps } from 'react';

import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';

/** A labeled text input bound to one field of a location form. */
export default function TextField<TData extends FormDataType<TData>>({
    form,
    name,
    label,
    className,
    ...props
}: {
    form: InertiaFormProps<TData>;
    name: keyof TData & string;
    label: string;
    className?: string;
} & Omit<ComponentProps<typeof Input>, 'form' | 'name'>) {
    const error = (form.errors as Record<string, string | undefined>)[name];

    return (
        <Field className={className} data-invalid={!!error || undefined}>
            <FieldLabel htmlFor={`location_${name}`}>{label}</FieldLabel>
            <Input
                id={`location_${name}`}
                value={String(form.data[name] ?? '')}
                aria-invalid={!!error || undefined}
                onChange={(event) =>
                    form.setData({ ...form.data, [name]: event.target.value })
                }
                {...props}
            />
            <FieldError>{error}</FieldError>
        </Field>
    );
}
