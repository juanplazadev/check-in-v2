import type { LocationHoursDay } from '@/components/dashboard/types';
import { formatClockTime } from '@/lib/dashboard/format';
import { DAY_NAMES, summarizeWeek, todayAt } from '@/lib/dashboard/hours';

/**
 * A location's weekly hours for check-ins or appointments, Monday first. Today's row is
 * highlighted, in the location's timezone, so staff in another zone still see the dock's day.
 */
export default function WeeklyHoursTable({
    hours,
    timezone,
}: {
    hours: LocationHoursDay[];
    timezone: string;
}) {
    const today = todayAt(timezone);

    return (
        <>
            <p className="mb-4 text-sm font-medium text-gray-800 dark:text-gray-100">
                {summarizeWeek(hours).join(' · ')}
            </p>
            <div className="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700/60">
                <table className="w-full text-sm">
                    <thead className="bg-gray-50 text-xs font-semibold text-gray-500 uppercase dark:bg-gray-900/20 dark:text-gray-400">
                        <tr>
                            <th scope="col" className="px-4 py-2.5 text-left">
                                Day
                            </th>
                            <th scope="col" className="px-4 py-2.5 text-left">
                                Opens
                            </th>
                            <th scope="col" className="px-4 py-2.5 text-left">
                                Closes
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-gray-700/60">
                        {hours.map((day) => {
                            const name = DAY_NAMES[day.dayOfWeek];
                            const isToday = day.dayOfWeek === today;
                            const isOpen =
                                day.openTime !== null && day.closeTime !== null;

                            return (
                                <tr
                                    key={day.dayOfWeek}
                                    className={
                                        isToday
                                            ? 'bg-brand-green/[0.06] dark:bg-brand-green/[0.12]'
                                            : undefined
                                    }
                                >
                                    <th
                                        scope="row"
                                        className="px-4 py-3 text-left font-medium text-gray-800 dark:text-gray-100"
                                    >
                                        {name}
                                        {isToday && (
                                            <span className="ml-2 rounded-full bg-brand-green/12 px-2 py-0.5 text-[11px] font-semibold text-brand-green">
                                                Today
                                            </span>
                                        )}
                                    </th>
                                    {isOpen ? (
                                        <>
                                            <td className="px-4 py-3 tabular-nums">
                                                {formatClockTime(day.openTime!)}
                                            </td>
                                            <td className="px-4 py-3 tabular-nums">
                                                {formatClockTime(
                                                    day.closeTime!,
                                                )}
                                            </td>
                                        </>
                                    ) : (
                                        <td
                                            colSpan={2}
                                            className="px-4 py-3 text-gray-400 dark:text-gray-500"
                                        >
                                            Closed
                                        </td>
                                    )}
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </>
    );
}
