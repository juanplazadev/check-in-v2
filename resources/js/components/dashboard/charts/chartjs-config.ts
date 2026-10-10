import { Chart, Tooltip } from 'chart.js';

import type { ResolvedTheme } from '@/utils/ThemeContext';

Chart.register(Tooltip);

// Ported from Mosaic's ChartjsConfig.
Chart.defaults.font.family = "'Figtree Variable', sans-serif";
Chart.defaults.font.weight = 500;
Chart.defaults.plugins.tooltip.borderWidth = 1;
Chart.defaults.plugins.tooltip.displayColors = false;
Chart.defaults.plugins.tooltip.mode = 'nearest';
Chart.defaults.plugins.tooltip.intersect = false;
Chart.defaults.plugins.tooltip.position = 'nearest';
Chart.defaults.plugins.tooltip.caretSize = 0;
Chart.defaults.plugins.tooltip.caretPadding = 20;
Chart.defaults.plugins.tooltip.cornerRadius = 8;
Chart.defaults.plugins.tooltip.padding = 8;

/**
 * Hex rather than CSS variables: canvas needs concrete colors, and reading them off the DOM (as
 * Mosaic does) breaks during SSR. Grays are Mosaic's; series colors start from the brand green.
 */
export const CHART_SERIES = {
    green: '#37b44a',
    sky: '#56b1f3',
    amber: '#f0bb33',
    rose: '#fa4949',
    gray: '#9ca3af',
} as const;

const THEMED = {
    light: {
        text: '#9ca3af',
        grid: '#f3f4f6',
        tooltipTitle: '#1f2937',
        tooltipBody: '#6b7280',
        tooltipBackground: '#ffffff',
        tooltipBorder: '#e5e7eb',
    },
    dark: {
        text: '#6b7280',
        grid: 'rgba(55, 65, 81, 0.6)',
        tooltipTitle: '#f3f4f6',
        tooltipBody: '#9ca3af',
        tooltipBackground: '#374151',
        tooltipBorder: '#4b5563',
    },
} as const;

export function chartTheme(theme: ResolvedTheme) {
    return THEMED[theme];
}

export function withOpacity(hex: string, opacity: number): string {
    const value = hex.replace('#', '');
    const [r, g, b] = [0, 2, 4].map((offset) =>
        parseInt(value.slice(offset, offset + 2), 16),
    );

    return `rgba(${r}, ${g}, ${b}, ${opacity})`;
}

/** Vertical fade under a line, from transparent at the baseline to `opacity` at the top. */
export function areaGradient(
    chart: Chart<any, any, any>,
    hex: string,
    opacity = 0.2,
): CanvasGradient | string {
    const { ctx, chartArea } = chart;

    if (!chartArea) {
        return 'transparent';
    }

    const gradient = ctx.createLinearGradient(
        0,
        chartArea.bottom,
        0,
        chartArea.top,
    );
    gradient.addColorStop(0, withOpacity(hex, 0));
    gradient.addColorStop(1, withOpacity(hex, opacity));

    return gradient;
}

export function applyTooltipTheme(
    chart: Chart<any, any, any>,
    theme: ResolvedTheme,
): void {
    const colors = chartTheme(theme);
    const tooltip = chart.options.plugins?.tooltip;

    if (tooltip) {
        tooltip.titleColor = colors.tooltipTitle;
        tooltip.bodyColor = colors.tooltipBody;
        tooltip.backgroundColor = colors.tooltipBackground;
        tooltip.borderColor = colors.tooltipBorder;
    }
}

/** Repaints an existing chart's axes, legend and tooltip for `theme` without rebuilding it. */
export function applyChartTheme(
    chart: Chart<any, any, any>,
    theme: ResolvedTheme,
): void {
    const colors = chartTheme(theme);
    const { scales, plugins } = chart.options;

    applyTooltipTheme(chart, theme);

    // Chart.js types each scale by its axis kind; only the shared tick and grid colors matter here.
    type ThemedScale = {
        ticks?: { color?: unknown };
        grid?: { display?: boolean; color?: unknown };
    };

    for (const scale of Object.values(scales ?? {}) as (
        ThemedScale | undefined
    )[]) {
        if (scale?.ticks) {
            scale.ticks.color = colors.text;
        }

        if (scale?.grid && scale.grid.display !== false) {
            scale.grid.color = colors.grid;
        }
    }

    if (plugins?.legend?.labels) {
        plugins.legend.labels.color = colors.text;
    }

    chart.update('none');
}
