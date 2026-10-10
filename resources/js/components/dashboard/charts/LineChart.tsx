import type { ChartConfiguration } from 'chart.js';
import {
    Chart,
    Filler,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    TimeScale,
} from 'chart.js';
import 'chartjs-adapter-date-fns';
import { useEffect, useRef } from 'react';

import {
    applyChartTheme,
    applyTooltipTheme,
    areaGradient,
    chartTheme,
    CHART_SERIES,
} from '@/components/dashboard/charts/chartjs-config';
import { useThemeProvider } from '@/utils/ThemeContext';

Chart.register(
    LineController,
    LineElement,
    Filler,
    PointElement,
    LinearScale,
    TimeScale,
);

export interface LineSeries {
    label: string;
    /** `[YYYY-MM-DD, value]` pairs, oldest first. */
    points: [string, number][];
    color?: string;
    /** Fill the area under the line with a fading gradient. */
    fill?: boolean;
}

interface LineChartProps {
    series: LineSeries[];
    height: number;
    /** Sparkline mode: no axes or grid, as in Mosaic's DashboardCard01-03. */
    compact?: boolean;
}

/** Time-series line chart, ported from Mosaic's LineChart01 (compact) and LineChart02 (axes). */
export default function LineChart({
    series,
    height,
    compact = false,
}: LineChartProps) {
    const canvas = useRef<HTMLCanvasElement>(null);
    const chart = useRef<Chart<'line', { x: string; y: number }[]> | null>(
        null,
    );
    const { resolvedTheme } = useThemeProvider();

    useEffect(() => {
        if (!canvas.current) {
            return;
        }

        const colors = chartTheme(resolvedTheme);
        const config: ChartConfiguration<'line', { x: string; y: number }[]> = {
            type: 'line',
            data: {
                datasets: series.map((line) => {
                    const color = line.color ?? CHART_SERIES.green;

                    return {
                        label: line.label,
                        data: line.points.map(([x, y]) => ({ x, y })),
                        fill: line.fill ?? false,
                        backgroundColor: (context) =>
                            areaGradient(context.chart, color),
                        borderColor: color,
                        borderWidth: 2,
                        pointRadius: 0,
                        pointHoverRadius: 3,
                        pointBackgroundColor: color,
                        pointHoverBackgroundColor: color,
                        pointBorderWidth: 0,
                        pointHoverBorderWidth: 0,
                        clip: 20,
                        tension: 0.2,
                    };
                }),
            },
            options: {
                layout: {
                    padding: compact
                        ? 20
                        : { top: 12, bottom: 16, left: 20, right: 20 },
                },
                scales: {
                    y: {
                        display: !compact,
                        beginAtZero: true,
                        border: { display: false },
                        ticks: {
                            maxTicksLimit: 5,
                            color: colors.text,
                            precision: 0,
                        },
                        grid: { color: colors.grid },
                    },
                    x: {
                        type: 'time',
                        time: {
                            unit: 'day',
                            tooltipFormat: 'MMM d',
                            displayFormats: { day: 'MMM d' },
                        },
                        display: !compact,
                        border: { display: false },
                        grid: { display: false },
                        ticks: {
                            autoSkipPadding: 48,
                            maxRotation: 0,
                            color: colors.text,
                        },
                    },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: compact ? { title: () => '' } : {},
                    },
                },
                interaction: {
                    intersect: false,
                    mode: compact ? 'nearest' : 'index',
                },
                maintainAspectRatio: false,
                resizeDelay: 200,
            },
        };

        chart.current = new Chart(canvas.current, config);
        applyTooltipTheme(chart.current, resolvedTheme);
        chart.current.update('none');

        return () => {
            chart.current?.destroy();
            chart.current = null;
        };
        // The theme is applied in place below rather than by rebuilding the chart.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [series, compact]);

    useEffect(() => {
        if (chart.current) {
            applyChartTheme(chart.current, resolvedTheme);
        }
    }, [resolvedTheme]);

    return (
        <div style={{ height }}>
            <canvas
                ref={canvas}
                role="img"
                aria-label={series.map((line) => line.label).join(', ')}
            />
        </div>
    );
}
