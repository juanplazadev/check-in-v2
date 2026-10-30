import logo from './../../images/logo.png';

type LogoSize = 'sm' | 'md' | 'lg';

interface LogoProps {
    size?: LogoSize;
    className?: string;
    /** Alt text, in the page's language; defaults to "<app> Logo". */
    label?: string;
}

export default function Logo({
    size = 'md',
    className = '',
    label,
}: LogoProps) {
    const appName = import.meta.env.VITE_APP_NAME;
    const altText = label ?? `${appName} Logo`;

    const sizeClasses = {
        sm: 'h-5',
        md: 'h-8',
        lg: 'h-12',
    };

    return (
        <img
            src={logo}
            className={`${sizeClasses[size] || sizeClasses.md} ${className}`}
            role="img"
            aria-label={altText}
            alt={altText}
        />
    );
}
