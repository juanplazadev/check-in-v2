import { Head, usePage } from '@inertiajs/react';
import { CheckCircle2, Clock, Mail, Phone } from 'lucide-react';

import RegisterCta from '@/components/RegisterCta';
import PublicLayout from '@/layouts/PublicLayout';

// ── Types ────────────────────────────────────────────────────────────────────

interface AppointmentData {
    uuid: string;
    referenceNumber: string;
    scheduledDate: string;
    /** Start to end, like "9:00 AM - 10:30 AM". */
    scheduledTime: string;
    loadType: string | null;
    driversName: string;
    locationName: string;
    locationAddress: string;
    purchaseOrders: string[];
}

interface Contact {
    phone: string;
    email: string;
}

interface AppointmentConfirmationTranslations {
    pageTitle: string;
    newAppointment: string;
    confirmed: string;
    subheading: string;
    reference: string;
    bookingSummary: string;
    locationLabel: string;
    dateLabel: string;
    timeLabel: string;
    loadTypeLabel: string;
    poLabel: string;
    driverLabel: string;
    arrivalReminder: string;
    needHelp: string;
}

interface Translations {
    appointmentConfirmation: AppointmentConfirmationTranslations;
}

interface PageProps {
    appointment: AppointmentData;
    contact: Contact;
    translations: Translations;
    [key: string]: unknown;
}

// ── Summary row ──────────────────────────────────────────────────────────────

function SummaryRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex justify-between gap-4 px-4 py-3">
            <dt className="text-xs font-medium text-gray-500 dark:text-gray-400">
                {label}
            </dt>
            <dd className="text-right text-sm text-gray-900 dark:text-gray-100">
                {value}
            </dd>
        </div>
    );
}

// ── Page ─────────────────────────────────────────────────────────────────────

export default function AppointmentConfirmation() {
    const { appointment, contact, translations } = usePage<PageProps>().props;
    const t = translations.appointmentConfirmation;

    return (
        <PublicLayout>
            <Head title={t.pageTitle} />

            <div className="px-4 py-6 sm:py-10">
                <div className="mx-auto max-w-md space-y-4 lg:max-w-xl">
                    {/* Page title */}
                    <div className="mb-6">
                        <p className="mb-1 text-[10px] font-bold tracking-widest text-brand-green uppercase">
                            {t.newAppointment}
                        </p>
                        <h1 className="text-2xl font-bold text-brand-grey dark:text-gray-100">
                            {t.pageTitle}
                        </h1>
                    </div>

                    {/* Main card */}
                    <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700/60 dark:bg-gray-800">
                        {/* Green accent bar */}
                        <div className="h-1 w-full bg-brand-green" />

                        {/* Success state */}
                        <div className="flex flex-col items-center px-5 pt-8 pb-6 text-center">
                            <div className="flex h-14 w-14 items-center justify-center rounded-full bg-brand-green/10">
                                <CheckCircle2 className="h-8 w-8 text-brand-green" />
                            </div>
                            <h2 className="mt-4 text-xl font-bold text-gray-900 dark:text-gray-100">
                                {t.confirmed}
                            </h2>
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {t.subheading}
                            </p>
                            <span className="mt-3 inline-flex items-center rounded-full bg-gray-100 px-3 py-1 font-mono text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                {t.reference}: #{appointment.referenceNumber}
                            </span>
                        </div>

                        {/* Dashed divider */}
                        <div className="mx-5 border-t border-dashed border-gray-200 dark:border-gray-700" />

                        {/* Booking summary */}
                        <div className="px-5 pt-5 pb-5">
                            <p className="mb-3 text-xs font-bold tracking-widest text-brand-green uppercase">
                                {t.bookingSummary}
                            </p>
                            <div className="border-gray-150/50 overflow-hidden rounded-lg border bg-gray-100/50 dark:border-gray-900/50 dark:bg-gray-950/30">
                                <dl className="divide-y divide-gray-200 dark:divide-gray-700/60">
                                    <SummaryRow
                                        label={t.locationLabel}
                                        value={appointment.locationName}
                                    />
                                    <SummaryRow
                                        label={t.dateLabel}
                                        value={appointment.scheduledDate}
                                    />
                                    <SummaryRow
                                        label={t.timeLabel}
                                        value={appointment.scheduledTime}
                                    />
                                    {appointment.loadType && (
                                        <SummaryRow
                                            label={t.loadTypeLabel}
                                            value={appointment.loadType}
                                        />
                                    )}
                                    <SummaryRow
                                        label={t.poLabel}
                                        value={appointment.purchaseOrders.join(
                                            ', ',
                                        )}
                                    />
                                    <SummaryRow
                                        label={t.driverLabel}
                                        value={appointment.driversName}
                                    />
                                </dl>
                            </div>
                        </div>

                        {/* Dashed divider */}
                        <div className="mx-5 border-t border-dashed border-gray-200 dark:border-gray-700" />

                        {/* Arrival reminder */}
                        <div className="flex gap-3 px-5 py-4">
                            <Clock className="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                {t.arrivalReminder}
                            </p>
                        </div>

                        {/* Contact footer */}
                        <div className="border-t border-gray-100 bg-gray-50 px-5 py-4 dark:border-gray-700/60 dark:bg-gray-800/60">
                            <p className="mb-2 text-xs font-semibold text-gray-700 dark:text-gray-300">
                                {t.needHelp}
                            </p>
                            <div className="flex flex-wrap gap-x-5 gap-y-1.5">
                                <a
                                    href={`tel:${contact.phone.replace(/\D/g, '')}`}
                                    className="flex items-center gap-1.5 text-sm text-brand-green hover:underline"
                                >
                                    <Phone className="h-3.5 w-3.5" />
                                    {contact.phone}
                                </a>
                                <a
                                    href={`mailto:${contact.email}`}
                                    className="flex items-center gap-1.5 text-sm text-brand-green hover:underline"
                                >
                                    <Mail className="h-3.5 w-3.5" />
                                    {contact.email}
                                </a>
                            </div>
                        </div>
                    </div>

                    <RegisterCta />
                </div>
            </div>
        </PublicLayout>
    );
}
