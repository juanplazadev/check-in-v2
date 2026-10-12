import type { LucideIcon } from 'lucide-react';

import type { LineSeries } from '@/components/dashboard/charts/LineChart';
import LineChart from '@/components/dashboard/charts/LineChart';

type Tone = 'positive' | 'negative' | 'neutral';

interface StatCardProps {
    title: string;
    label: string;
    value: string | number;
    icon?: LucideIcon;
    /** A short note beside the value, such as "+12%" or "3 failed". */
    delta?: { text: string; tone: Tone };
    sparkline?: LineSeries;
    className?: string;
}

const TONE_CLASSES: Record<Tone, string> = {
    positive: 'bg-green-500/20 text-green-700 dark:text-green-400',
    negative: 'bg-red-500/20 text-red-700 dark:text-red-400',
    neutral: 'bg-gray-500/15 text-gray-600 dark:text-gray-300',
};

/** KPI tile in the shape of Mosaic's DashboardCard01-03. */
export default function StatCard({
    title,
    label,
    value,
    icon: Icon,
    delta,
    sparkline,
    className = 'col-span-full sm:col-span-6 xl:col-span-3',
}: StatCardProps) {
    return (
        <div
            className={`flex flex-col rounded-xl bg-white shadow-xs dark:bg-gray-800 ${className}`}
        >
            <div className={`px-5 pt-5 ${sparkline ? '' : 'pb-5'}`}>
                <header className="mb-2 flex items-start justify-between">
                    <h2 className="mb-2 text-lg font-semibold text-gray-800 dark:text-gray-100">
                        {title}
                    </h2>
                    {Icon && (
                        <span className="flex size-9 items-center justify-center rounded-full bg-brand-green/12 text-brand-green">
                            <Icon className="size-4" />
                        </span>
                    )}
                </header>
                <div className="mb-1 text-xs font-semibold text-gray-400 uppercase dark:text-gray-500">
                    {label}
                </div>
                <div className="flex items-start">
                    <div className="mr-2 text-3xl font-bold text-gray-800 dark:text-gray-100">
                        {value}
                    </div>
                    {delta && (
                        <div
                            className={`rounded-full px-1.5 text-sm font-medium ${TONE_CLASSES[delta.tone]}`}
                        >
                            {delta.text}
                        </div>
                    )}
                </div>
            </div>
            {sparkline && (
                <div className="grow">
                    <LineChart series={[sparkline]} height={96} compact />
                </div>
            )}
        </div>
    );
}
