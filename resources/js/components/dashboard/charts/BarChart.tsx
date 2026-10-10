import type { ChartConfiguration } from 'chart.js';
import {
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    Legend,
    LinearScale,
} from 'chart.js';
import { useEffect, useRef } from 'react';

import {
    applyChartTheme,
    applyTooltipTheme,
    chartTheme,
    CHART_SERIES,
} from '@/components/dashboard/charts/chartjs-config';
import { useThemeProvider } from '@/utils/ThemeContext';

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Legend);

export interface BarSeries {
    label: string;
    values: number[];
    color?: string;
}

interface BarChartProps {
    labels: string[];
    series: BarSeries[];
    height: number;
    stacked?: boolean;
}

/** Category bar chart, ported from Mosaic's BarChart01 (grouped) and BarChart03 (stacked). */
export default function BarChart({
    labels,
    series,
    height,
    stacked = false,
}: BarChartProps) {
    const canvas = useRef<HTMLCanvasElement>(null);
    const chart = useRef<Chart<'bar'> | null>(null);
    const { resolvedTheme } = useThemeProvider();

    useEffect(() => {
        if (!canvas.current) {
            return;
        }

        const colors = chartTheme(resolvedTheme);
        const config: ChartConfiguration<'bar'> = {
            type: 'bar',
            data: {
                labels,
                datasets: series.map((bar) => ({
                    label: bar.label,
                    data: bar.values,
                    backgroundColor: bar.color ?? CHART_SERIES.green,
                    hoverBackgroundColor: bar.color ?? CHART_SERIES.green,
                    barPercentage: 0.7,
                    categoryPercentage: 0.7,
                    borderRadius: 4,
                })),
            },
            options: {
                layout: {
                    padding: { top: 12, bottom: 16, left: 20, right: 20 },
                },
                scales: {
                    y: {
                        stacked,
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
                        stacked,
                        border: { display: false },
                        grid: { display: false },
                        ticks: { color: colors.text },
                    },
                },
                plugins: {
                    legend: {
                        display: series.length > 1,
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',
                            boxWidth: 8,
                            color: colors.text,
                        },
                    },
                },
                interaction: { intersect: false, mode: 'index' },
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
    }, [labels, series, stacked]);

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
                aria-label={series.map((bar) => bar.label).join(', ')}
            />
        </div>
    );
}
