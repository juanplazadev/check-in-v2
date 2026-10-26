import { Deferred, Head, router } from '@inertiajs/react';
import {
    CalendarClock,
    CalendarDays,
    CheckCircle2,
    ChevronDown,
    MapPin,
    Timer,
    Truck,
    UserX,
} from 'lucide-react';
import { useEffect, useMemo } from 'react';

import Card from '@/components/dashboard/Card';
import BarChart from '@/components/dashboard/charts/BarChart';
import {
    CHART_SERIES,
    withOpacity,
} from '@/components/dashboard/charts/chartjs-config';
import DoughnutChart from '@/components/dashboard/charts/DoughnutChart';
import type { DoughnutSlice } from '@/components/dashboard/charts/DoughnutChart';
import type { TimeUnit } from '@/components/dashboard/charts/LineChart';
import LineChart from '@/components/dashboard/charts/LineChart';
import PageHeader from '@/components/dashboard/PageHeader';
import { MENU_CLASSES } from '@/components/dashboard/RecordActionDialogs';
import StatCard from '@/components/dashboard/StatCard';
import type {
    AppointmentStatus,
    CheckInStatus,
} from '@/components/dashboard/types';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import DashboardLayout from '@/layouts/DashboardLayout';
import { formatLabel } from '@/lib/dashboard/format';
import dashboardRoutes from '@/routes/dashboard';

/** Mirrors App\Enums\AnalyticsPeriod. */
type AnalyticsPeriod = 'today' | '7d' | '30d' | '90d' | '12m';

interface PeriodOption {
    value: AnalyticsPeriod;
    label: string;
    previousLabel: string;
}

interface Comparison<T> {
    value: T;
    previous: T;
}

interface RankingRow {
    id: number;
    name: string;
    abbreviation: string;
    checkIns: number;
    completionRate: number | null;
    medianTurnaround: number | null;
    appointments: number;
    noShowRate: number | null;
}

/** Mirrors DashboardAnalytics::execute(). */
interface AnalyticsReport {
    summary: {
        checkIns: Comparison<number>;
        completionRate: Comparison<number | null>;
        medianTurnaround: Comparison<number | null>;
        appointments: Comparison<number>;
        noShowRate: Comparison<number | null>;
    };
    volume: {
        bucket: TimeUnit;
        labels: string[];
        checkIns: number[];
        appointments: number[];
    };
    statuses: {
        checkIns: Record<CheckInStatus | 'archived', number>;
        appointments: Record<AppointmentStatus | 'archived', number>;
    };
    ranking: RankingRow[];
    timeOfDay: { hourly: number[]; weekdays: number[] };
}

interface AnalyticsProps {
    filters: {
        period: AnalyticsPeriod;
        locations: number[];
        timezone: string;
    };
    periods: PeriodOption[];
    locations: { id: number; name: string }[];
    report?: AnalyticsReport;
}

type Tone = 'positive' | 'negative' | 'neutral';

const ARCHIVED_COLOR = withOpacity(CHART_SERIES.gray, 0.4);

const CHECK_IN_STATUS_COLORS: Record<CheckInStatus | 'archived', string> = {
    pending: CHART_SERIES.amber,
    completed: CHART_SERIES.green,
    cancelled: CHART_SERIES.rose,
    archived: ARCHIVED_COLOR,
};

const APPOINTMENT_STATUS_COLORS: Record<
    AppointmentStatus | 'archived',
    string
> = {
    scheduled: CHART_SERIES.sky,
    'checked-in': CHART_SERIES.amber,
    completed: CHART_SERIES.green,
    'no-show': CHART_SERIES.rose,
    cancelled: CHART_SERIES.gray,
    archived: ARCHIVED_COLOR,
};

const HOUR_LABELS = Array.from(
    { length: 24 },
    (_, hour) => `${hour % 12 || 12}${hour < 12 ? 'a' : 'p'}`,
);

const WEEKDAY_LABELS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

const SELECT_BUTTON =
    'btn justify-between gap-2 border-gray-200 bg-white text-gray-600 hover:border-gray-300 hover:text-gray-800 dark:border-gray-700/60 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-gray-600 dark:hover:text-gray-100';

function browserTimezone(): string {
    return Intl.DateTimeFormat().resolvedOptions().timeZone;
}

/**
 * Reloads only the filters and the report, so the cards keep their last numbers (dimmed)
 * until the new ones arrive instead of dropping back to the skeleton.
 */
function visit(period: AnalyticsPeriod, locations: number[]) {
    router.get(
        dashboardRoutes.analytics.url(),
        {
            period,
            ...(locations.length > 0 && { locations: locations.map(String) }),
            tz: browserTimezone(),
        },
        {
            only: ['filters', 'report'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function formatPercent(value: number | null): string {
    return value === null ? '-' : `${value}%`;
}

function formatDuration(minutes: number | null): string {
    if (minutes === null) {
        return '-';
    }

    if (minutes < 60) {
        return `${minutes}m`;
    }

    if (minutes < 1440) {
        return `${Math.floor(minutes / 60)}h ${minutes % 60}m`;
    }

    return `${Math.floor(minutes / 1440)}d ${Math.floor((minutes % 1440) / 60)}h`;
}

/**
 * The change against the previous period: a percentage for counts and durations, points for
 * rates. `higherIsBetter` picks the tone; none when there is nothing to compare against.
 */
function deltaOf(
    { value, previous }: Comparison<number | null>,
    kind: 'count' | 'rate',
    higherIsBetter: boolean,
    previousLabel: string,
): { text: string; tone: Tone } | undefined {
    if (value === null || previous === null) {
        return undefined;
    }

    const change =
        kind === 'rate'
            ? Math.round((value - previous) * 10) / 10
            : previous === 0
              ? null
              : Math.round(((value - previous) / previous) * 100);

    if (change === null) {
        return undefined;
    }

    const sign = change > 0 ? '+' : '';
    const unit = kind === 'rate' ? ' pts' : '%';
    const tone: Tone =
        change === 0
            ? 'neutral'
            : change > 0 === higherIsBetter
              ? 'positive'
              : 'negative';

    return { text: `${sign}${change}${unit} vs ${previousLabel}`, tone };
}

function slicesOf<K extends string>(
    counts: Record<K, number>,
    colors: Record<K, string>,
): DoughnutSlice[] {
    return (Object.keys(colors) as K[]).map((status) => ({
        label: formatLabel(status),
        value: counts[status] ?? 0,
        color: colors[status],
    }));
}

function PeriodSelect({
    periods,
    value,
    onChange,
}: {
    periods: PeriodOption[];
    value: AnalyticsPeriod;
    onChange: (period: AnalyticsPeriod) => void;
}) {
    const selected = periods.find((period) => period.value === value);

    return (
        <DropdownMenu modal={false}>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className={`${SELECT_BUTTON} min-w-44`}
                    aria-label="Select period"
                >
                    <span className="flex items-center">
                        <CalendarDays className="mr-2 size-4 shrink-0 text-gray-400 dark:text-gray-500" />
                        {selected?.label}
                    </span>
                    <ChevronDown className="size-3.5 shrink-0 text-gray-400 dark:text-gray-500" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className={`w-44 ${MENU_CLASSES}`}>
                {periods.map((period) => (
                    <DropdownMenuItem
                        key={period.value}
                        className={`rounded-md ${period.value === value ? 'font-semibold text-brand-green' : ''}`}
                        onSelect={() => onChange(period.value)}
                    >
                        {period.label}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/** A checkbox list in the shape of the data table's location filter, applied as it is toggled. */
function LocationSelect({
    locations,
    value,
    onChange,
}: {
    locations: { id: number; name: string }[];
    value: number[];
    onChange: (locations: number[]) => void;
}) {
    const summary =
        value.length === 0
            ? 'All locations'
            : value.length === 1
              ? (locations.find((location) => location.id === value[0])?.name ??
                '1 location')
              : `${value.length} locations`;

    const toggle = (id: number, checked: boolean) =>
        onChange(
            checked ? [...value, id] : value.filter((item) => item !== id),
        );

    return (
        <Popover>
            <PopoverTrigger asChild>
                <button type="button" className={`${SELECT_BUTTON} min-w-44`}>
                    <span className="flex items-center truncate">
                        <MapPin className="mr-2 size-4 shrink-0 text-gray-400 dark:text-gray-500" />
                        <span className="truncate">{summary}</span>
                    </span>
                    <ChevronDown className="size-3.5 shrink-0 text-gray-400 dark:text-gray-500" />
                </button>
            </PopoverTrigger>
            {/* Portalled out of the `.dashboard` wrapper, so it re-applies the scope itself. */}
            <PopoverContent
                align="end"
                className="dashboard w-64 gap-0 overflow-hidden rounded-lg border border-gray-200 bg-white p-0 pt-1.5 shadow-lg ring-0 dark:border-gray-700/60 dark:bg-gray-800"
            >
                <div className="px-3 pt-1.5 pb-2 text-xs font-semibold text-gray-400 uppercase dark:text-gray-500">
                    Locations
                </div>
                <ul className="mb-3 max-h-72 overflow-y-auto">
                    {locations.map((location) => (
                        <li key={location.id} className="px-3 py-1">
                            <label className="flex items-center">
                                <input
                                    type="checkbox"
                                    className="form-checkbox"
                                    checked={value.includes(location.id)}
                                    onChange={(event) =>
                                        toggle(
                                            location.id,
                                            event.target.checked,
                                        )
                                    }
                                />
                                <span className="ml-2 text-sm font-medium text-gray-600 dark:text-gray-300">
                                    {location.name}
                                </span>
                            </label>
                        </li>
                    ))}
                </ul>
                {value.length > 0 && (
                    <div className="border-t border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700/60 dark:bg-gray-700/20">
                        <button
                            type="button"
                            className="btn-xs border-gray-200 bg-white text-red-500 hover:border-gray-300 dark:border-gray-700/60 dark:bg-gray-800"
                            onClick={() => onChange([])}
                        >
                            Clear
                        </button>
                    </div>
                )}
            </PopoverContent>
        </Popover>
    );
}

function SkeletonCard({
    className,
    height,
}: {
    className: string;
    height: number;
}) {
    return (
        <div
            className={`animate-pulse rounded-xl bg-white p-5 shadow-xs dark:bg-gray-800 ${className}`}
        >
            <div className="mb-4 h-4 w-1/3 rounded bg-gray-200 dark:bg-gray-700" />
            <div
                className="rounded-lg bg-gray-100 dark:bg-gray-700/50"
                style={{ height }}
            />
        </div>
    );
}

function ReportSkeleton() {
    return (
        <div className="grid grid-cols-12 gap-6" aria-busy="true">
            <div className="col-span-full grid grid-cols-2 gap-6 lg:grid-cols-3 xl:grid-cols-5">
                {Array.from({ length: 5 }, (_, index) => (
                    <SkeletonCard key={index} className="" height={56} />
                ))}
            </div>
            <SkeletonCard
                className="col-span-full xl:col-span-8"
                height={248}
            />
            <SkeletonCard
                className="col-span-full sm:col-span-6 xl:col-span-4"
                height={248}
            />
            <SkeletonCard
                className="col-span-full sm:col-span-6 xl:col-span-4"
                height={248}
            />
            <SkeletonCard
                className="col-span-full xl:col-span-8"
                height={248}
            />
            <SkeletonCard
                className="col-span-full xl:col-span-4"
                height={248}
            />
            <SkeletonCard
                className="col-span-full xl:col-span-8"
                height={248}
            />
        </div>
    );
}

function EmptyChart({ height, message }: { height: number; message: string }) {
    return (
        <div
            className="flex items-center justify-center px-5 text-sm text-gray-400 dark:text-gray-500"
            style={{ height }}
        >
            {message}
        </div>
    );
}

function SeriesLegend({
    items,
}: {
    items: { label: string; color: string }[];
}) {
    return (
        <ul className="flex flex-wrap gap-x-4 gap-y-1">
            {items.map((item) => (
                <li
                    key={item.label}
                    className="flex items-center text-sm text-gray-500 dark:text-gray-400"
                >
                    <span
                        className="mr-1.5 block size-2 rounded-full"
                        style={{ backgroundColor: item.color }}
                    />
                    {item.label}
                </li>
            ))}
        </ul>
    );
}

/** Location ranking in the shape of Mosaic's AnalyticsCard05 report list, as a table. */
function RankingTable({ rows }: { rows: RankingRow[] }) {
    const busiest = Math.max(1, ...rows.map((row) => row.checkIns));

    if (rows.length === 0) {
        return <EmptyChart height={248} message="No locations to rank." />;
    }

    return (
        <div className="overflow-x-auto p-3">
            <table className="w-full table-auto dark:text-gray-300">
                <thead className="rounded-xs bg-gray-50 text-xs text-gray-400 uppercase dark:bg-gray-700/50 dark:text-gray-500">
                    <tr>
                        <th className="p-2 text-left font-semibold">
                            Location
                        </th>
                        <th className="p-2 text-right font-semibold">
                            Check-ins
                        </th>
                        <th className="p-2 text-right font-semibold">
                            Completed
                        </th>
                        <th className="p-2 text-right font-semibold">
                            Turnaround
                        </th>
                        <th className="p-2 text-right font-semibold">
                            Appointments
                        </th>
                        <th className="p-2 text-right font-semibold">
                            No-shows
                        </th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 text-sm font-medium dark:divide-gray-700/60">
                    {rows.map((row) => (
                        <tr key={row.id}>
                            <td className="relative p-2 whitespace-nowrap">
                                <span
                                    aria-hidden="true"
                                    className="absolute inset-y-1 left-0 rounded-sm bg-brand-green/10"
                                    style={{
                                        width: `${(row.checkIns / busiest) * 100}%`,
                                    }}
                                />
                                <span className="relative font-semibold text-gray-800 dark:text-gray-100">
                                    {row.name}
                                </span>
                            </td>
                            <td className="p-2 text-right whitespace-nowrap tabular-nums">
                                {row.checkIns}
                            </td>
                            <td className="p-2 text-right whitespace-nowrap tabular-nums">
                                {formatPercent(row.completionRate)}
                            </td>
                            <td className="p-2 text-right whitespace-nowrap tabular-nums">
                                {formatDuration(row.medianTurnaround)}
                            </td>
                            <td className="p-2 text-right whitespace-nowrap tabular-nums">
                                {row.appointments}
                            </td>
                            <td className="p-2 text-right whitespace-nowrap tabular-nums">
                                {formatPercent(row.noShowRate)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function Report({
    report,
    period,
    reloading,
}: {
    report: AnalyticsReport;
    period: PeriodOption;
    reloading: boolean;
}) {
    const { summary, volume, statuses, timeOfDay } = report;

    // Memoized because the charts rebuild whenever their series change identity.
    const volumeSeries = useMemo(
        () => [
            {
                label: 'Check-ins',
                points: volume.labels.map((label, index): [string, number] => [
                    label,
                    volume.checkIns[index],
                ]),
                fill: true,
            },
            {
                label: 'Appointments',
                color: CHART_SERIES.sky,
                points: volume.labels.map((label, index): [string, number] => [
                    label,
                    volume.appointments[index],
                ]),
            },
        ],
        [volume],
    );
    const checkInSlices = useMemo(
        () => slicesOf(statuses.checkIns, CHECK_IN_STATUS_COLORS),
        [statuses.checkIns],
    );
    const appointmentSlices = useMemo(
        () => slicesOf(statuses.appointments, APPOINTMENT_STATUS_COLORS),
        [statuses.appointments],
    );
    const hourlySeries = useMemo(
        () => [{ label: 'Check-ins', values: timeOfDay.hourly }],
        [timeOfDay.hourly],
    );
    const weekdaySeries = useMemo(
        () => [{ label: 'Check-ins', values: timeOfDay.weekdays }],
        [timeOfDay.weekdays],
    );

    const hasCheckIns = summary.checkIns.value > 0;
    const hasAppointments = summary.appointments.value > 0;

    return (
        <div
            className={`grid grid-cols-12 gap-6 transition-opacity ${reloading ? 'opacity-60' : ''}`}
        >
            <div className="col-span-full grid grid-cols-2 gap-6 lg:grid-cols-3 xl:grid-cols-5">
                <StatCard
                    className="col-span-2 lg:col-span-1"
                    title="Check-ins"
                    label={period.label}
                    value={summary.checkIns.value}
                    icon={Truck}
                    delta={deltaOf(
                        summary.checkIns,
                        'count',
                        true,
                        period.previousLabel,
                    )}
                />
                <StatCard
                    className=""
                    title="Completed"
                    label="Of check-ins"
                    value={formatPercent(summary.completionRate.value)}
                    icon={CheckCircle2}
                    delta={deltaOf(
                        summary.completionRate,
                        'rate',
                        true,
                        period.previousLabel,
                    )}
                />
                <StatCard
                    className=""
                    title="Turnaround"
                    label="Median, to complete"
                    value={formatDuration(summary.medianTurnaround.value)}
                    icon={Timer}
                    delta={deltaOf(
                        summary.medianTurnaround,
                        'count',
                        false,
                        period.previousLabel,
                    )}
                />
                <StatCard
                    className=""
                    title="Appointments"
                    label="Scheduled in period"
                    value={summary.appointments.value}
                    icon={CalendarClock}
                    delta={deltaOf(
                        summary.appointments,
                        'count',
                        true,
                        period.previousLabel,
                    )}
                />
                <StatCard
                    className=""
                    title="No-shows"
                    label="Of due appointments"
                    value={formatPercent(summary.noShowRate.value)}
                    icon={UserX}
                    delta={deltaOf(
                        summary.noShowRate,
                        'rate',
                        false,
                        period.previousLabel,
                    )}
                />
            </div>

            <Card
                title="Volume over time"
                className="col-span-full xl:col-span-8"
                action={
                    <SeriesLegend
                        items={[
                            { label: 'Check-ins', color: CHART_SERIES.green },
                            { label: 'Appointments', color: CHART_SERIES.sky },
                        ]}
                    />
                }
            >
                <LineChart
                    series={volumeSeries}
                    unit={volume.bucket}
                    height={248}
                />
            </Card>

            <Card
                title="Check-in outcomes"
                className="col-span-full sm:col-span-6 xl:col-span-4"
            >
                {hasCheckIns ? (
                    <DoughnutChart
                        key={checkInSlices.map((slice) => slice.value).join()}
                        slices={checkInSlices}
                        height={220}
                    />
                ) : (
                    <EmptyChart
                        height={248}
                        message="No check-ins in this period."
                    />
                )}
            </Card>

            <Card
                title="Appointment outcomes"
                className="col-span-full sm:col-span-6 xl:col-span-4"
            >
                {hasAppointments ? (
                    <DoughnutChart
                        key={appointmentSlices
                            .map((slice) => slice.value)
                            .join()}
                        slices={appointmentSlices}
                        height={220}
                    />
                ) : (
                    <EmptyChart
                        height={248}
                        message="No appointments in this period."
                    />
                )}
            </Card>

            <Card
                title="Check-ins by hour"
                className="col-span-full xl:col-span-8"
            >
                <BarChart
                    labels={HOUR_LABELS}
                    series={hourlySeries}
                    height={248}
                />
            </Card>

            <Card
                title="Check-ins by weekday"
                className="col-span-full xl:col-span-4"
            >
                <BarChart
                    labels={WEEKDAY_LABELS}
                    series={weekdaySeries}
                    height={248}
                />
            </Card>

            <Card title="Locations" className="col-span-full xl:col-span-8">
                <RankingTable rows={report.ranking} />
            </Card>
        </div>
    );
}

export default function AnalyticsIndex({
    filters,
    periods,
    locations,
    report,
}: AnalyticsProps) {
    const period =
        periods.find((option) => option.value === filters.period) ?? periods[0];

    // A link from the sidebar carries no timezone, so the first report is in the server's.
    useEffect(() => {
        if (filters.timezone !== browserTimezone()) {
            visit(filters.period, filters.locations);
        }
    }, [filters.timezone, filters.period, filters.locations]);

    return (
        <>
            <Head title="Analytics" />

            <PageHeader
                title="Analytics"
                subtitle="Check-in and appointment trends across your locations."
                actions={
                    <>
                        {locations.length > 1 && (
                            <LocationSelect
                                locations={locations}
                                value={filters.locations}
                                onChange={(selected) =>
                                    visit(filters.period, selected)
                                }
                            />
                        )}
                        <PeriodSelect
                            periods={periods}
                            value={filters.period}
                            onChange={(selected) =>
                                visit(selected, filters.locations)
                            }
                        />
                    </>
                }
            />

            <Deferred data="report" fallback={<ReportSkeleton />}>
                {({ reloading }) =>
                    report ? (
                        <Report
                            report={report}
                            period={period}
                            reloading={reloading}
                        />
                    ) : (
                        <ReportSkeleton />
                    )
                }
            </Deferred>
        </>
    );
}

AnalyticsIndex.layout = DashboardLayout;
