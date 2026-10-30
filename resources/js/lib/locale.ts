import type { Locale } from 'react-day-picker/locale';
import { enUS, es, fr } from 'react-day-picker/locale';

/** The app locales in config('app.locales'), as day-picker / date-fns locales. */
const DATE_LOCALES: Record<string, Locale> = { en: enUS, es, fr };

export function dateLocale(code: string | undefined): Locale {
    return (code && DATE_LOCALES[code]) || enUS;
}

/** "9:00 AM" in English, "9:00" in Spanish, "09:00" in French. */
export function formatTimeOfDay(value: string, code = 'en'): string {
    return new Intl.DateTimeFormat(code, {
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(`2000-01-01T${value}`));
}

/** A "Y-m-d" date: "November 2, 2026", "2 de noviembre de 2026". */
export function formatLongDate(value: string, code = 'en'): string {
    return new Intl.DateTimeFormat(code, { dateStyle: 'long' }).format(
        new Date(`${value}T00:00:00`),
    );
}
