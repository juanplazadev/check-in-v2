import AppointmentDockPicker from '@/components/dashboard/appointment/AppointmentDockPicker';
import Card from '@/components/dashboard/Card';
import { DetailRow } from '@/components/dashboard/DetailCard';
import type {
    AppointmentDockOption,
    AppointmentRecord,
} from '@/components/dashboard/types';
import { formatDateTime } from '@/lib/dashboard/format';

export default function AppointmentBookingCard({
    appointment,
    dockOptions = null,
}: {
    appointment: AppointmentRecord;
    /** Null when the viewer may not assign docks, or the booking is not live. */
    dockOptions?: AppointmentDockOption[] | null;
}) {
    return (
        <Card id="purchase-orders" title="Booking" className="scroll-mt-24">
            <dl className="divide-y divide-gray-100 px-5 text-sm dark:divide-gray-700/60">
                <DetailRow label="Scheduled for">
                    {formatDateTime(appointment.scheduledFor)}
                </DetailRow>
                <DetailRow label="Dock">
                    <AppointmentDockPicker
                        appointment={appointment}
                        options={dockOptions}
                    />
                </DetailRow>
                <DetailRow label="Booked">
                    {formatDateTime(appointment.createdAt)}
                </DetailRow>
                <DetailRow label="Purchase orders">
                    {appointment.purchaseOrders.length > 0 ? (
                        <span className="flex flex-wrap gap-1.5 sm:justify-end">
                            {appointment.purchaseOrders.map((purchaseOrder) => (
                                <span
                                    key={purchaseOrder}
                                    className="rounded-md border border-gray-200 bg-gray-50 px-2 py-0.5 font-mono text-xs dark:border-gray-700/60 dark:bg-gray-900/40"
                                >
                                    {purchaseOrder}
                                </span>
                            ))}
                        </span>
                    ) : null}
                </DetailRow>
            </dl>
        </Card>
    );
}
