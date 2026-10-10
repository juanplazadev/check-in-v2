import { Head, useForm, usePage } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import type { FormEvent } from 'react';

import Logo from '@/components/Logo';
import ThemeToggle from '@/components/ThemeToggle';
import { Button } from '@/components/ui/button';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';

const appName = (import.meta.env.VITE_APP_NAME as string) ?? '';

interface ConfirmPasswordTranslations {
    eyebrow: string;
    title: string;
    subtitle: string;
    passwordLabel: string;
    passwordPlaceholder: string;
    confirm: string;
    tagline: string;
    taglineSub: string;
}

interface PageProps {
    translations: { confirmPassword: ConfirmPasswordTranslations };
    [key: string]: unknown;
}

export default function ConfirmPassword() {
    const { translations } = usePage<PageProps>().props;
    const t = translations.confirmPassword;

    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        // Fortify's own POST endpoint. On success it redirects to the intended
        // URL the password.confirm middleware stashed, which is the page the
        // driver was trying to reach.
        post('/user/confirm-password', {
            onError: () => reset('password'),
        });
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

                        <p className="mt-10 flex items-center gap-1.5 text-xs font-bold tracking-widest text-brand-green uppercase">
                            <ShieldCheck className="h-3.5 w-3.5" />
                            {t.eyebrow}
                        </p>
                        <h1 className="mt-2 text-3xl font-bold tracking-tight text-brand-grey dark:text-gray-50">
                            {t.title}
                        </h1>
                        <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            {t.subtitle}
                        </p>

                        <form onSubmit={submit} className="mt-8 space-y-5">
                            <Field
                                data-invalid={!!errors.password || undefined}
                            >
                                <FieldLabel htmlFor="password">
                                    {t.passwordLabel}
                                </FieldLabel>
                                <Input
                                    id="password"
                                    name="password"
                                    type="password"
                                    autoComplete="current-password"
                                    autoFocus
                                    value={data.password}
                                    aria-invalid={
                                        !!errors.password || undefined
                                    }
                                    placeholder={t.passwordPlaceholder}
                                    onChange={(e) =>
                                        setData('password', e.target.value)
                                    }
                                />
                                <FieldError>{errors.password}</FieldError>
                            </Field>

                            <Button
                                type="submit"
                                disabled={processing || data.password === ''}
                                className="h-11 w-full cursor-pointer rounded-4xl bg-brand-green text-sm font-semibold text-white shadow-sm shadow-brand-green/30 transition-colors hover:bg-brand-green/90 focus-visible:ring-brand-green/50"
                            >
                                {t.confirm}
                            </Button>
                        </form>
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
