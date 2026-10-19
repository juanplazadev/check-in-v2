import { Phone } from 'lucide-react';

import Card from '@/components/dashboard/Card';
import type { AppointmentRecord } from '@/components/dashboard/types';
import { formatPhone, initialsOf } from '@/lib/dashboard/format';

/** Who booked the slot. A booking carries only the driver's name and cellphone. */
export default function AppointmentDriverCard({
    appointment,
}: {
    appointment: AppointmentRecord;
}) {
    const { driver } = appointment;

    return (
        <Card title="Driver">
            <div className="flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
                <span className="flex size-14 shrink-0 items-center justify-center rounded-full bg-gray-100 text-lg font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-200">
                    {initialsOf(driver.name)}
                </span>
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-lg font-semibold text-gray-800 dark:text-gray-100">
                            {driver.name}
                        </span>
                        <span
                            className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${driver.hasAccount ? 'bg-brand-green/12 text-brand-green' : 'bg-gray-400/15 text-gray-500 dark:text-gray-400'}`}
                        >
                            {driver.hasAccount ? 'Registered driver' : 'Guest'}
                        </span>
                    </div>
                    <div className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                        <a
                            href={`tel:${driver.cellphone}`}
                            className="inline-flex items-center gap-1.5 text-gray-600 hover:text-brand-green dark:text-gray-300"
                        >
                            <Phone className="size-3.5 text-gray-400" />
                            {formatPhone(driver.cellphone)}
                        </a>
                    </div>
                </div>
            </div>
        </Card>
    );
}
