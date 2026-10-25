import type { ChartConfiguration } from 'chart.js';
import { ArcElement, Chart, DoughnutController } from 'chart.js';
import { useEffect, useRef, useState } from 'react';

import {
    applyTooltipTheme,
    CHART_SERIES,
} from '@/components/dashboard/charts/chartjs-config';
import { useThemeProvider } from '@/utils/ThemeContext';

Chart.register(DoughnutController, ArcElement);

export interface DoughnutSlice {
    label: string;
    value: number;
    color?: string;
}

interface DoughnutChartProps {
    slices: DoughnutSlice[];
    height: number;
}

interface LegendItem {
    index: number;
    label: string;
    value: number;
    color: string;
    hidden: boolean;
}

const FALLBACK_COLORS = Object.values(CHART_SERIES);

/**
 * Doughnut with a toggleable legend, ported from Mosaic's DoughnutChart. The legend is React
 * rather than Mosaic's DOM-building plugin, so it follows the app's markup and theming.
 * Toggled slices are not reset when `slices` changes; key the chart on its data to reset them.
 */
export default function DoughnutChart({ slices, height }: DoughnutChartProps) {
    const canvas = useRef<HTMLCanvasElement>(null);
    const chart = useRef<Chart<'doughnut'> | null>(null);
    const { resolvedTheme } = useThemeProvider();
    const [hidden, setHidden] = useState<number[]>([]);

    const colors = slices.map(
        (slice, index) =>
            slice.color ?? FALLBACK_COLORS[index % FALLBACK_COLORS.length],
    );

    useEffect(() => {
        if (!canvas.current) {
            return;
        }

        const config: ChartConfiguration<'doughnut'> = {
            type: 'doughnut',
            data: {
                labels: slices.map((slice) => slice.label),
                datasets: [
                    {
                        data: slices.map((slice) => slice.value),
                        backgroundColor: colors,
                        hoverBackgroundColor: colors,
                        borderWidth: 0,
                    },
                ],
            },
            options: {
                cutout: '80%',
                layout: { padding: 24 },
                plugins: { legend: { display: false } },
                interaction: { intersect: false, mode: 'nearest' },
                animation: { duration: 500 },
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
    }, [slices]);

    useEffect(() => {
        if (chart.current) {
            applyTooltipTheme(chart.current, resolvedTheme);
            chart.current.update('none');
        }
    }, [resolvedTheme]);

    const toggle = (index: number) => {
        chart.current?.toggleDataVisibility(index);
        chart.current?.update();
        setHidden((current) =>
            current.includes(index)
                ? current.filter((item) => item !== index)
                : [...current, index],
        );
    };

    const legend: LegendItem[] = slices.map((slice, index) => ({
        index,
        label: slice.label,
        value: slice.value,
        color: colors[index],
        hidden: hidden.includes(index),
    }));

    return (
        <div className="flex grow flex-col justify-center">
            <div style={{ height }}>
                <canvas
                    ref={canvas}
                    role="img"
                    aria-label={slices
                        .map((slice) => `${slice.label}: ${slice.value}`)
                        .join(', ')}
                />
            </div>
            <div className="px-5 pt-2 pb-6">
                <ul className="-m-1 flex flex-wrap justify-center">
                    {legend.map((item) => (
                        <li key={item.label} className="m-1">
                            <button
                                type="button"
                                onClick={() => toggle(item.index)}
                                aria-pressed={!item.hidden}
                                className={`btn-xs rounded-full bg-white text-gray-500 shadow-xs shadow-black/[0.08] dark:bg-gray-700 dark:text-gray-400 ${item.hidden ? 'opacity-30' : ''}`}
                            >
                                <span
                                    className="mr-1 block size-2 rounded-full"
                                    style={{ backgroundColor: item.color }}
                                />
                                {item.label}
                                <span className="ml-1 text-gray-400 tabular-nums dark:text-gray-500">
                                    {item.value}
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}
