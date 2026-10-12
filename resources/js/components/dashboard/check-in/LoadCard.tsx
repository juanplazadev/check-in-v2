import { MessageSquareQuote } from 'lucide-react';

import Card from '@/components/dashboard/Card';
import { DetailRow } from '@/components/dashboard/DetailCard';
import RegionLabel from '@/components/dashboard/RegionLabel';
import type { CheckInRecord } from '@/components/dashboard/types';

export default function LoadCard({ checkIn }: { checkIn: CheckInRecord }) {
    const { destination } = checkIn;

    return (
        <Card id="purchase-orders" title="Load" className="scroll-mt-24">
            <dl className="divide-y divide-gray-100 px-5 text-sm dark:divide-gray-700/60">
                <DetailRow label="Customer">{checkIn.customer}</DetailRow>
                <DetailRow label="Destination">
                    <RegionLabel
                        prefix={destination.city}
                        state={destination.state}
                        country={destination.country}
                    />
                </DetailRow>
                <DetailRow label="Purchase orders">
                    {checkIn.purchaseOrders.length > 0 ? (
                        <span className="flex flex-wrap gap-1.5 sm:justify-end">
                            {checkIn.purchaseOrders.map((purchaseOrder) => (
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

            <div className="px-5 pt-2 pb-5">
                <div className="mb-2 flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <MessageSquareQuote className="size-4" />
                    Loading instructions
                </div>
                {checkIn.loadingInstructions !== null &&
                checkIn.loadingInstructions.trim() !== '' ? (
                    <blockquote className="rounded-r-lg border-l-2 border-brand-green bg-gray-50 px-4 py-3 text-sm whitespace-pre-line text-gray-700 dark:bg-gray-900/40 dark:text-gray-200">
                        {checkIn.loadingInstructions}
                    </blockquote>
                ) : (
                    <p className="text-sm text-gray-400 dark:text-gray-500">
                        No instructions from the driver.
                    </p>
                )}
            </div>
        </Card>
    );
}
