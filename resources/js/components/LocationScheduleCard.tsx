import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';

export interface ScheduleCardLocation {
    name: string;
    address: string;
    isOpen: boolean;
    todayOpenCloseTime: string | null;
    reason: string | null;
    hasOverride: boolean;
    isOverrideClosure: boolean;
}

export default function LocationScheduleCard({
    location,
    todaysHoursLabel,
    openLabel,
    openInMapsLabel = 'Open in Maps',
}: {
    location: ScheduleCardLocation;
    openLabel: string;
    todaysHoursLabel: string;
    openInMapsLabel?: string;
}) {
    return (
        <div className="border-gray-150/50 overflow-hidden rounded-lg border bg-gray-100/50 dark:border-gray-900/50 dark:bg-gray-950/30">
            {/* Location row */}
            <div className="relative flex items-start gap-3 overflow-hidden px-4 py-3">
                <span className="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-gray-100/70 dark:bg-gray-900/40">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 384 512"
                        className="h-3.5 w-3.5 fill-current text-brand-green"
                    >
                        <path d="M215.7 499.2C267 435 384 279.4 384 192C384 86 298 0 192 0S0 86 0 192c0 87.4 117 243 168.3 307.2c12.3 15.3 35.1 15.3 47.4 0zM192 128a64 64 0 1 1 0 128 64 64 0 1 1 0-128z" />
                    </svg>
                </span>
                <div className="min-w-0 flex-1">
                    <p className="text-sm leading-tight font-semibold text-gray-900 dark:text-gray-100">
                        {location.name}
                    </p>
                    <p className="mt-1 truncate text-xs leading-tight text-gray-500 dark:text-gray-400">
                        {location.address}
                    </p>
                </div>

                <a
                    href={`https://maps.google.com/?q=${encodeURIComponent(location.address)}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label={openInMapsLabel}
                    className="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-gray-200/60 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-gray-700/40 dark:hover:text-gray-300"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        className="h-3.5 w-3.5"
                    >
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
                        <polyline points="15 3 21 3 21 9" />
                        <line x1="10" y1="14" x2="21" y2="3" />
                    </svg>
                </a>
            </div>

            {/* Today's hours */}
            <Accordion
                type="single"
                collapsible={true}
                defaultValue="schedule"
                className="rounded-none border-0"
            >
                <AccordionItem
                    value="schedule"
                    className="border-0 data-[state=open]:bg-transparent"
                >
                    <AccordionTrigger className="cursor-pointer py-2.5 pr-4.5 pl-14 text-xs font-semibold text-gray-600 hover:bg-transparent hover:no-underline data-[state=open]:bg-transparent dark:text-gray-400">
                        {todaysHoursLabel}
                    </AccordionTrigger>
                    <AccordionContent>
                        <div className="flex items-start justify-between gap-4 py-2 pr-4 pl-11">
                            <div className="flex shrink-0 items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                {location.todayOpenCloseTime && (
                                    <span>{location.todayOpenCloseTime}</span>
                                )}
                                {location.isOpen && (
                                    <span className="inline-flex rounded-full bg-green-500/10 px-2.5 py-1 text-xs font-medium text-brand-green">
                                        {openLabel}
                                    </span>
                                )}
                            </div>
                            {location.hasOverride && location.reason && (
                                <span
                                    className={`self-start rounded-full px-2.5 py-1 text-xs font-medium ${
                                        location.isOverrideClosure
                                            ? 'bg-red-500/15 text-red-700 dark:text-red-400'
                                            : 'bg-amber-500/15 text-amber-700 dark:text-amber-400'
                                    }`}
                                >
                                    {location.reason}
                                </span>
                            )}
                        </div>
                    </AccordionContent>
                </AccordionItem>
            </Accordion>
        </div>
    );
}
