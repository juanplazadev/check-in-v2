import { Link } from '@inertiajs/react';
import { SearchX } from 'lucide-react';

/** Shown by detail pages when the URL's identifier is not in the (mock) data. */
export default function RecordNotFound({
    backHref,
    backLabel,
}: {
    backHref: string;
    backLabel: string;
}) {
    return (
        <div className="flex flex-col items-center rounded-xl bg-white px-5 py-16 text-center shadow-xs dark:bg-gray-800">
            <span className="mb-4 flex size-14 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700">
                <SearchX className="size-6 text-gray-400" />
            </span>
            <h2 className="mb-2 text-lg font-semibold text-gray-800 dark:text-gray-100">
                Record not found
            </h2>
            <p className="mb-6 text-sm">
                It may have been removed, or the link is wrong.
            </p>
            <Link
                href={backHref}
                className="btn bg-gray-900 text-gray-100 hover:bg-gray-800 dark:bg-gray-100 dark:text-gray-800 dark:hover:bg-white"
            >
                {backLabel}
            </Link>
        </div>
    );
}
