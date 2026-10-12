import { Eye, EyeOff, Lock, ShieldCheck, User } from 'lucide-react';
import { useState } from 'react';

import { countryName } from '@/lib/countryFlag';
import { stateAbbr } from '@/lib/usStates';

interface LicenseCardProps {
    name: string;
    number: string | null;
    /** Stored state name (e.g. "Arizona"). */
    state: string | null;
    /** Stored ISO-2 country code (e.g. "MX"), or null. */
    country?: string | null;
    /** Expiration as YYYY-MM-DD, or null. */
    expirationDate: string | null;
    /** Localized label for the "Encrypted" security chip. */
    secureLabel: string;
    /** Localized prompt shown when no license is on file yet. */
    emptyLabel: string;
    className?: string;
}

// A handful of rich, dark gradients. Every state maps deterministically to one,
// so a given state always renders the same premium-looking card.
const THEMES: readonly string[] = [
    'from-slate-800 via-slate-900 to-black',
    'from-emerald-800 via-emerald-950 to-slate-950',
    'from-indigo-800 via-blue-950 to-slate-950',
    'from-rose-800 via-red-950 to-slate-950',
    'from-amber-700 via-orange-950 to-slate-950',
    'from-violet-800 via-purple-950 to-slate-950',
    'from-cyan-800 via-teal-950 to-slate-950',
    'from-fuchsia-800 via-pink-950 to-slate-950',
];

function themeFor(abbr: string | null): string {
    if (!abbr) {
        return THEMES[0];
    }

    const hash = abbr
        .split('')
        .reduce((acc, char) => acc + char.charCodeAt(0), 0);

    return THEMES[hash % THEMES.length];
}

function initialsOf(name: string): string {
    return name
        .trim()
        .split(/\s+/)
        .map((part) => part[0] ?? '')
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

function maskExceptLast4(value: string): string {
    if (value.length <= 4) {
        return value;
    }

    const visible = value.slice(-4);
    return '•'.repeat(value.length - 4) + visible;
}

function group(value: string): string {
    return (value.match(/.{1,4}/g) ?? []).join(' ');
}

function formatExp(date: string | null): string {
    if (!date) {
        return '--/--';
    }

    const [year, month] = date.split('-');
    return `${month}/${year.slice(-2)}`;
}

export default function LicenseCard({
    name,
    number,
    state,
    country = null,
    expirationDate,
    secureLabel,
    emptyLabel,
    className = '',
}: LicenseCardProps) {
    const [revealed, setRevealed] = useState(false);

    const abbr = stateAbbr(state);
    const gradient = themeFor(abbr);
    const hasNumber = Boolean(number && number.trim().length > 0);
    const initials = initialsOf(name);

    const shownNumber = hasNumber
        ? group(
              revealed
                  ? number!.toUpperCase()
                  : maskExceptLast4(number!.toUpperCase()),
          )
        : '•••• •••• ••••';

    return (
        <div
            className={`relative aspect-7/4 w-full overflow-hidden rounded-2xl bg-gradient-to-tr ${gradient} p-5 shadow-lg ring-1 ring-white/10 ${className}`}
        >
            {/* Faint state-abbreviation watermark */}
            <span
                aria-hidden
                className="pointer-events-none absolute -right-2 bottom-0 text-[7rem] leading-none font-black tracking-tighter text-white/5 select-none"
            >
                {abbr ?? country ?? 'US'}
            </span>

            {/* Sheen */}
            <div
                aria-hidden
                className="pointer-events-none absolute -top-16 -left-10 h-48 w-48 rounded-full bg-white/10 blur-2xl"
            />

            <div className="relative flex h-full flex-col">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div>
                        <p className="text-[0.65rem] font-semibold tracking-[0.2em] text-white/60 uppercase">
                            {state ??
                                (country
                                    ? countryName(country)
                                    : 'United States')}
                        </p>
                        <p className="mt-0.5 text-sm font-bold tracking-wide text-white">
                            Driver License
                        </p>
                    </div>
                    <span className="inline-flex items-center gap-1 rounded-full bg-white/10 px-2 py-1 text-[0.6rem] font-medium text-white/80 backdrop-blur-sm">
                        <ShieldCheck className="h-3 w-3" />
                        {secureLabel}
                    </span>
                </div>

                {/* Body: ID photo + details, centered together */}
                <div className="mt-3 flex min-h-0 flex-1 items-center gap-4">
                    {/* Portrait */}
                    <div className="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-gradient-to-b from-white/20 to-white/5 ring-1 ring-white/15">
                        {initials ? (
                            <span className="text-xl font-bold text-white/85">
                                {initials}
                            </span>
                        ) : (
                            <User className="h-7 w-7 text-white/45" />
                        )}
                    </div>

                    {/* Details */}
                    <div className="flex min-w-0 flex-1 flex-col gap-3.5">
                        <div className="flex items-start justify-between gap-2">
                            <div className="min-w-0">
                                <p className="text-[0.55rem] tracking-widest text-white/50 uppercase">
                                    License No.
                                </p>
                                <p className="truncate font-mono text-base font-semibold tracking-[0.1em] text-white/90 drop-shadow-sm sm:text-lg">
                                    {shownNumber}
                                </p>
                            </div>
                            {hasNumber && (
                                <button
                                    type="button"
                                    onClick={() => setRevealed((v) => !v)}
                                    aria-label={
                                        revealed
                                            ? 'Hide number'
                                            : 'Reveal number'
                                    }
                                    aria-pressed={revealed}
                                    className="-mt-1 inline-flex h-7 w-7 shrink-0 cursor-pointer items-center justify-center rounded-full text-white/70 transition-colors hover:bg-white/10 hover:text-white"
                                >
                                    {revealed ? (
                                        <EyeOff className="h-4 w-4" />
                                    ) : (
                                        <Eye className="h-4 w-4" />
                                    )}
                                </button>
                            )}
                        </div>

                        <div className="flex items-end justify-between gap-2">
                            <div className="min-w-0">
                                <p className="text-[0.55rem] tracking-widest text-white/50 uppercase">
                                    Licensee
                                </p>
                                <p className="truncate text-sm font-semibold tracking-wide text-white uppercase">
                                    {name || '—'}
                                </p>
                            </div>
                            <div className="shrink-0 text-right">
                                <p className="text-[0.55rem] tracking-widest text-white/50 uppercase">
                                    Exp
                                </p>
                                <p className="text-sm font-semibold tracking-wide text-white">
                                    {formatExp(expirationDate)}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Not-on-file overlay hint */}
            {!hasNumber && (
                <div className="absolute inset-x-0 bottom-0 flex items-center justify-center gap-1.5 bg-black/30 py-1.5 text-[0.65rem] font-medium text-white/80 backdrop-blur-sm">
                    <Lock className="h-3 w-3" />
                    {emptyLabel}
                </div>
            )}
        </div>
    );
}
