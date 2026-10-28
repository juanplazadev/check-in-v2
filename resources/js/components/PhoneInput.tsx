import * as React from 'react';

import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

/**
 * Format raw input into a US phone number, e.g. "(555) 123-4567". Capped at 10
 * digits. The "+1" country code is shown as a fixed prefix by <PhoneInput> and
 * prepended server-side, so the value stored here is just the formatted digits.
 */
export function formatUsPhone(raw: string): string {
    const digits = raw.replace(/\D/g, '').substring(0, 10);
    const area = digits.substring(0, 3);
    const mid = digits.substring(3, 6);
    const last = digits.substring(6, 10);

    if (digits.length > 6) {
return `(${area}) ${mid}-${last}`;
}

    if (digits.length > 3) {
return `(${area}) ${mid}`;
}

    if (digits.length > 0) {
return `(${area}`;
}

    return '';
}

type PhoneInputProps = Omit<
    React.ComponentProps<'input'>,
    'value' | 'onChange' | 'type'
> & {
    value: string;
    onChange: (value: string) => void;
    invalid?: boolean;
};

/**
 * A US cellphone input with a fixed "+1" prefix and live formatting. `value` is
 * the formatted string; `onChange` receives the newly formatted value.
 */
export function PhoneInput({
    value,
    onChange,
    invalid,
    className,
    ...props
}: PhoneInputProps) {
    return (
        <div className={cn('flex rounded-4xl shadow-xs', className)}>
            <span className="inline-flex items-center rounded-l-4xl border border-r-0 border-input bg-muted px-3 text-sm text-muted-foreground dark:bg-input/30">
                +1
            </span>
            <Input
                type="tel"
                inputMode="tel"
                autoComplete="tel-national"
                maxLength={14}
                value={value}
                aria-invalid={invalid || undefined}
                onChange={(event) => onChange(formatUsPhone(event.target.value))}
                className="rounded-l-none"
                {...props}
            />
        </div>
    );
}
