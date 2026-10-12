import { Menu, Search } from 'lucide-react';
import { useState } from 'react';

import LiveAlertsMenu from '@/components/dashboard/live/LiveAlertsMenu';
import NotificationsMenu from '@/components/dashboard/NotificationsMenu';
import ProfileMenu from '@/components/dashboard/ProfileMenu';
import SearchModal from '@/components/dashboard/SearchModal';
import ThemeToggle from '@/components/ThemeToggle';

interface HeaderProps {
    sidebarOpen: boolean;
    setSidebarOpen: (open: boolean) => void;
}

/** Ported from Mosaic's Header (default variant). */
export default function Header({ sidebarOpen, setSidebarOpen }: HeaderProps) {
    const [searchOpen, setSearchOpen] = useState(false);

    return (
        <header className="sticky top-0 z-30 before:absolute before:inset-0 before:-z-10 before:backdrop-blur-md max-lg:shadow-xs max-lg:before:bg-white/90 lg:before:bg-gray-100/90 dark:max-lg:before:bg-gray-800/90 dark:lg:before:bg-gray-900/90">
            <div className="px-4 sm:px-6 lg:px-8">
                <div className="flex h-16 items-center justify-between border-gray-200 lg:border-b dark:border-gray-700/60">
                    {/* Left */}
                    <div className="flex items-center">
                        <button
                            type="button"
                            className="text-gray-500 hover:text-gray-600 lg:hidden dark:hover:text-gray-400"
                            aria-controls="sidebar"
                            aria-expanded={sidebarOpen}
                            onClick={(event) => {
                                event.stopPropagation();
                                setSidebarOpen(!sidebarOpen);
                            }}
                        >
                            <span className="sr-only">Open sidebar</span>
                            <Menu className="size-6" />
                        </button>
                    </div>

                    {/* Right */}
                    <div className="flex items-center space-x-3">
                        <button
                            type="button"
                            className={`ml-3 flex size-8 items-center justify-center rounded-full hover:bg-gray-100 lg:hover:bg-gray-200 dark:hover:bg-gray-700/50 dark:lg:hover:bg-gray-800 ${
                                searchOpen ? 'bg-gray-200 dark:bg-gray-800' : ''
                            }`}
                            onClick={() => setSearchOpen(true)}
                        >
                            <span className="sr-only">Search</span>
                            <Search className="size-4 text-gray-500/80 dark:text-gray-400/80" />
                        </button>
                        <SearchModal
                            open={searchOpen}
                            onOpenChange={setSearchOpen}
                        />
                        <LiveAlertsMenu />
                        <NotificationsMenu />
                        <ThemeToggle />
                        <hr className="h-6 w-px border-none bg-gray-200 dark:bg-gray-700/60" />
                        <ProfileMenu />
                    </div>
                </div>
            </div>
        </header>
    );
}
