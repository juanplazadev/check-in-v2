import { Link, usePage } from '@inertiajs/react';
import BuildStamp from '@/components/BuildStamp';
import Logo from '@/components/Logo';
import { contact, schedule } from '@/routes';

interface PublicNavigation {
    logoLabel: string;
    checkIn: string;
    appointment: string;
    services: string;
    company: string;
    termsOfService: string;
    privacyPolicy: string;
    schedule: string;
    contact: string;
    allRightsReserved: string;
    legal: string;
    login: string;
}

interface Translations {
    publicNavigation: PublicNavigation;
}

interface PageProps {
    translations: Translations;
    [key: string]: unknown;
}

export default function Footer({ hideLogin = false }: { hideLogin?: boolean }) {
    const appName = import.meta.env.VITE_APP_NAME;

    const { translations } = usePage<PageProps>().props;

    const pageTranslations: PublicNavigation = translations.publicNavigation;

    return (
        <footer className="w-full border-t border-gray-200/80 bg-white/95 dark:border-gray-700/50 dark:bg-gray-900/95">
            <div className="mx-auto max-w-7xl px-6 py-8">
                <div className="mb-8 grid grid-cols-2 gap-8 sm:grid-cols-3">
                    <div>
                        <p className="mb-3 text-xs font-medium tracking-widest text-gray-400 uppercase">
                            {pageTranslations.services}
                        </p>
                        <div className="flex flex-col gap-2.5">
                            <Link
                                href="#"
                                className="text-sm text-gray-500 transition-colors hover:text-brand-green"
                                prefetch
                            >
                                {translations.publicNavigation.checkIn}
                            </Link>
                            <Link
                                href="#"
                                className="text-sm text-gray-500 transition-colors hover:text-brand-green"
                                prefetch
                            >
                                {translations.publicNavigation.appointment}
                            </Link>
                        </div>
                    </div>

                    <div>
                        <p className="mb-3 text-xs font-medium tracking-widest text-gray-400 uppercase">
                            {pageTranslations.company}
                        </p>
                        <div className="flex flex-col gap-2.5">
                            <Link
                                href={schedule().url}
                                className="text-sm text-gray-500 transition-colors hover:text-brand-green"
                                prefetch
                            >
                                {pageTranslations.schedule}
                            </Link>
                            <Link
                                href={contact().url}
                                className="text-sm text-gray-500 transition-colors hover:text-brand-green"
                                prefetch
                            >
                                {pageTranslations.contact}
                            </Link>
                            {!hideLogin && (
                                <Link
                                    href="/login"
                                    className="text-sm text-gray-500 transition-colors hover:text-brand-green"
                                    prefetch
                                >
                                    {pageTranslations.login}
                                </Link>
                            )}
                        </div>
                    </div>

                    <div>
                        <p className="mb-3 text-xs font-medium tracking-widest text-gray-400 uppercase">
                            {pageTranslations.legal}
                        </p>
                        <div className="flex flex-col gap-2.5">
                            <Link
                                href="/privacy"
                                className="text-sm text-gray-500 transition-colors hover:text-brand-green"
                                prefetch
                            >
                                {pageTranslations.privacyPolicy}
                            </Link>
                            <Link
                                href="/terms"
                                className="text-sm text-gray-500 transition-colors hover:text-brand-green"
                                prefetch
                            >
                                {pageTranslations.termsOfService}
                            </Link>
                        </div>
                    </div>
                </div>

                <div className="flex items-center justify-between border-t border-gray-200/80 pt-4 dark:border-gray-700/50">
                    {/* Logo links home */}
                    <Link href="/" prefetch>
                        <Logo
                            size="sm"
                            label={pageTranslations.logoLabel.replace(
                                ':app',
                                import.meta.env.VITE_APP_NAME,
                            )}
                        />
                    </Link>
                    <div className="flex flex-col items-end gap-1">
                        <span className="text-xs text-gray-400">
                            © {new Date().getFullYear()} {appName}.{' '}
                            {pageTranslations.allRightsReserved}.
                        </span>
                        <BuildStamp />
                    </div>
                </div>
            </div>
        </footer>
    );
}
