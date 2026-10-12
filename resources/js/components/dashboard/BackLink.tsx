import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

export default function BackLink({
    href,
    label,
}: {
    href: string;
    label: string;
}) {
    return (
        <Link
            href={href}
            className="mb-4 inline-flex items-center text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
        >
            <ArrowLeft className="mr-1.5 size-4" />
            {label}
        </Link>
    );
}
