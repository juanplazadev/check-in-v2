import { Link } from '@inertiajs/react';
import { CalendarClock, Search, Truck, User } from 'lucide-react';
import { Dialog } from 'radix-ui';
import { useState } from 'react';

import { hasAnyRole, useStaffRoles } from '@/hooks/useStaffRoles';
import {
    MOCK_APPOINTMENTS,
    MOCK_CHECK_INS,
    MOCK_USERS,
} from '@/lib/dashboard/mock';
import dashboardRoutes from '@/routes/dashboard';

interface SearchModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

interface SearchResult {
    key: string;
    label: string;
    detail: string;
    href: string;
    icon: typeof Truck;
}

const RESULT_LIMIT = 8;

function matches(query: string, ...fields: string[]): boolean {
    return fields.some((field) => field.toLowerCase().includes(query));
}

/**
 * Mosaic's ModalSearch on a Radix dialog. Searches the mock records client-side; the backend
 * version will hit a search endpoint instead.
 */
export default function SearchModal({ open, onOpenChange }: SearchModalProps) {
    const roles = useStaffRoles();
    const [query, setQuery] = useState('');
    const needle = query.trim().toLowerCase();

    const results: SearchResult[] =
        needle.length < 2
            ? []
            : [
                  ...MOCK_CHECK_INS.filter((checkIn) =>
                      matches(
                          needle,
                          checkIn.referenceNumber,
                          checkIn.driver.name,
                          checkIn.truck.plate,
                          checkIn.trailer.plate,
                      ),
                  ).map((checkIn) => ({
                      key: `check-in-${checkIn.uuid}`,
                      label: checkIn.referenceNumber,
                      detail: `Check-in · ${checkIn.driver.name}`,
                      href: dashboardRoutes.checkIns.show.url(checkIn.uuid),
                      icon: Truck,
                  })),
                  ...MOCK_APPOINTMENTS.filter((appointment) =>
                      matches(
                          needle,
                          appointment.referenceNumber,
                          appointment.driver.name,
                      ),
                  ).map((appointment) => ({
                      key: `appointment-${appointment.uuid}`,
                      label: appointment.referenceNumber,
                      detail: `Appointment · ${appointment.driver.name}`,
                      href: dashboardRoutes.appointments.show.url(
                          appointment.uuid,
                      ),
                      icon: CalendarClock,
                  })),
                  ...(hasAnyRole(roles, ['admin'])
                      ? MOCK_USERS.filter((user) =>
                            matches(needle, user.name, user.email),
                        ).map((user) => ({
                            key: `user-${user.id}`,
                            label: user.name,
                            detail: `User · ${user.email}`,
                            href: dashboardRoutes.users.show.url(user.id),
                            icon: User,
                        }))
                      : []),
              ].slice(0, RESULT_LIMIT);

    return (
        <Dialog.Root
            open={open}
            onOpenChange={(next) => {
                onOpenChange(next);

                if (!next) {
                    setQuery('');
                }
            }}
        >
            <Dialog.Portal>
                <Dialog.Overlay className="fixed inset-0 z-50 bg-gray-900/30 data-[state=open]:animate-in data-[state=open]:fade-in-0" />
                <Dialog.Content className="fixed inset-x-4 top-20 z-50 mx-auto max-h-[calc(100dvh-10rem)] max-w-2xl overflow-auto rounded-lg border border-transparent bg-white shadow-lg data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:slide-in-from-top-4 dark:border-gray-700/60 dark:bg-gray-800">
                    <Dialog.Title className="sr-only">Search</Dialog.Title>
                    <Dialog.Description className="sr-only">
                        Search check-ins, appointments and users by reference,
                        driver, plate or email.
                    </Dialog.Description>

                    <form
                        className="border-b border-gray-200 dark:border-gray-700/60"
                        onSubmit={(event) => event.preventDefault()}
                    >
                        <div className="relative">
                            <label
                                htmlFor="dashboard-search"
                                className="sr-only"
                            >
                                Search
                            </label>
                            <input
                                id="dashboard-search"
                                className="w-full appearance-none border-0 bg-white py-3 pr-4 pl-10 text-gray-800 placeholder-gray-400 focus:ring-transparent focus:outline-none dark:bg-gray-800 dark:text-gray-100 dark:placeholder-gray-500"
                                type="search"
                                placeholder="Reference, driver, plate or email…"
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                                autoComplete="off"
                            />
                            <Search className="absolute inset-y-0 left-0 my-auto ml-4 size-4 text-gray-400 dark:text-gray-500" />
                        </div>
                    </form>

                    <div className="px-2 py-4">
                        <div className="mb-2 px-2 text-xs font-semibold text-gray-400 uppercase dark:text-gray-500">
                            {needle.length < 2 ? 'Type to search' : 'Results'}
                        </div>
                        {needle.length >= 2 && results.length === 0 && (
                            <p className="px-2 text-sm text-gray-500 dark:text-gray-400">
                                Nothing matches “{query}”.
                            </p>
                        )}
                        <ul className="text-sm">
                            {results.map((result) => {
                                const Icon = result.icon;

                                return (
                                    <li key={result.key}>
                                        <Link
                                            href={result.href}
                                            onClick={() => onOpenChange(false)}
                                            className="group flex items-center rounded-lg p-2 text-gray-800 hover:bg-gray-100 dark:text-gray-100 dark:hover:bg-gray-700/20"
                                        >
                                            <Icon className="mr-3 size-4 shrink-0 text-gray-400 dark:text-gray-500" />
                                            <span className="font-medium">
                                                {result.label}
                                            </span>
                                            <span className="ml-2 truncate text-gray-500 dark:text-gray-400">
                                                {result.detail}
                                            </span>
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
