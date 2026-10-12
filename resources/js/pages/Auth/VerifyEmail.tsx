import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { MailCheck } from 'lucide-react';
import type { FormEvent } from 'react';

import Logo from '@/components/Logo';
import ThemeToggle from '@/components/ThemeToggle';
import { Button } from '@/components/ui/button';
import { logout } from '@/routes';
import { profile as accountProfile } from '@/routes/account';
import { send as resendVerification } from '@/routes/verification';

const appName = (import.meta.env.VITE_APP_NAME as string) ?? '';

interface VerifyEmailTranslations {
    eyebrow: string;
    title: string;
    subtitle: string;
    resend: string;
    sent: string;
    spamHint: string;
    wrongEmailPrompt: string;
    wrongEmailLink: string;
    logout: string;
    tagline: string;
    taglineSub: string;
}

interface PageProps {
    auth: { user: { email: string } };
    translations: { verifyEmailPage: VerifyEmailTranslations };
    status?: string | null;
    [key: string]: unknown;
}

export default function VerifyEmail() {
    const { auth, translations, status } = usePage<PageProps>().props;
    const t = translations.verifyEmailPage;

    const { post, processing } = useForm({});

    const linkSent = status === 'verification-link-sent';

    const resend = (event: FormEvent) => {
        event.preventDefault();
        post(resendVerification().url);
    };

    return (
        <>
            <Head title={t.title} />

            <div className="flex min-h-dvh bg-white dark:bg-gray-900">
                {/* ── Form side ──────────────────────────────────────────── */}
                <div className="relative flex w-full flex-col justify-center px-6 py-12 sm:px-12 lg:w-1/2 lg:px-16 xl:px-28">
                    {/* Decorative glow */}
                    <div
                        aria-hidden
                        className="pointer-events-none absolute -top-24 -left-24 h-96 w-96 rounded-full bg-brand-green/10 blur-3xl dark:bg-brand-green/5"
                    />

                    <div className="absolute top-5 right-5">
                        <ThemeToggle />
                    </div>

                    <div className="relative mx-auto w-full max-w-sm">
                        <Logo size="md" />

                        <p className="mt-10 text-xs font-bold tracking-widest text-brand-green uppercase">
                            {t.eyebrow}
                        </p>
                        <h1 className="mt-2 text-3xl font-bold tracking-tight text-brand-grey dark:text-gray-50">
                            {t.title}
                        </h1>
                        {/* The address is spelled out rather than summarized: a
                            typo at registration is the usual reason a driver is
                            stuck here, and seeing it is how they notice. */}
                        <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            {t.subtitle.replace(':email', auth.user.email)}
                        </p>

                        {linkSent && (
                            <div
                                role="status"
                                className="mt-6 flex items-start gap-2 rounded-2xl border border-brand-green/30 bg-brand-green/10 px-4 py-3 text-sm font-medium text-brand-green"
                            >
                                <MailCheck className="mt-0.5 h-4 w-4 shrink-0" />
                                <span>{t.sent}</span>
                            </div>
                        )}

                        <form onSubmit={resend} className="mt-8">
                            <Button
                                type="submit"
                                disabled={processing}
                                className="h-11 w-full cursor-pointer rounded-4xl bg-brand-green text-sm font-semibold text-white shadow-sm shadow-brand-green/30 transition-colors hover:bg-brand-green/90 focus-visible:ring-brand-green/50"
                            >
                                {t.resend}
                            </Button>
                        </form>

                        <p className="mt-4 text-center text-xs text-gray-400 dark:text-gray-500">
                            {t.spamHint}
                        </p>

                        {/* The way out of an unverifiable account. Changing the
                            email re-sends the link, so this stays reachable
                            while the rest of the account is gated. */}
                        <p className="mt-8 text-center text-sm text-gray-500 dark:text-gray-400">
                            {t.wrongEmailPrompt}{' '}
                            <Link
                                href={accountProfile().url}
                                className="font-medium text-brand-green transition-opacity hover:opacity-80"
                            >
                                {t.wrongEmailLink}
                            </Link>
                        </p>

                        <p className="mt-2 text-center text-sm">
                            <button
                                type="button"
                                onClick={() => router.post(logout().url)}
                                className="cursor-pointer font-medium text-gray-500 transition-opacity hover:opacity-80 dark:text-gray-400"
                            >
                                {t.logout}
                            </button>
                        </p>
                    </div>

                    <div className="relative mx-auto mt-12 w-full max-w-sm">
                        <p className="text-xs text-gray-400 dark:text-gray-500">
                            © {new Date().getFullYear()} {appName}
                        </p>
                    </div>
                </div>

                {/* ── Visual side ────────────────────────────────────────── */}
                <div className="relative hidden overflow-hidden bg-side-image bg-cover bg-center lg:block lg:w-1/2">
                    <div className="absolute inset-0 bg-gradient-to-tr from-brand-green/95 via-brand-green/55 to-brand-grey/50" />

                    {/* Soft glow accents */}
                    <div
                        aria-hidden
                        className="pointer-events-none absolute -top-20 -right-20 h-80 w-80 rounded-full bg-white/15 blur-3xl"
                    />

                    <div className="relative flex h-full flex-col justify-end p-12 xl:p-16">
                        <span className="text-xs font-bold tracking-[0.2em] text-white/80 uppercase">
                            {appName}
                        </span>
                        <h2 className="mt-4 max-w-md text-4xl leading-tight font-bold text-white xl:text-5xl">
                            {t.tagline}
                        </h2>
                        <p className="mt-4 max-w-sm text-sm leading-relaxed text-white/85">
                            {t.taglineSub}
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}
