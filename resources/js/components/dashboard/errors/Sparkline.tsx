/** A tiny bar sparkline for table rows, drawn as SVG so a page of rows stays cheap. */
export default function Sparkline({
    values,
    className = 'h-6 w-24',
    color = 'currentColor',
}: {
    values: number[];
    className?: string;
    color?: string;
}) {
    const max = Math.max(1, ...values);
    const width = 100 / Math.max(1, values.length);

    return (
        <svg
            viewBox="0 0 100 24"
            preserveAspectRatio="none"
            className={className}
            role="img"
            aria-label={`Peak of ${max} events per bucket`}
        >
            {values.map((value, index) => {
                const height =
                    value === 0 ? 1 : Math.max(2, (value / max) * 24);

                return (
                    <rect
                        key={index}
                        x={index * width + width * 0.15}
                        y={24 - height}
                        width={width * 0.7}
                        height={height}
                        rx={0.6}
                        fill={color}
                        opacity={value === 0 ? 0.25 : 1}
                    />
                );
            })}
        </svg>
    );
}
