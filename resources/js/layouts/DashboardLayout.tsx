import type { ReactNode } from 'react';
import { useState } from 'react';

import Header from '@/components/dashboard/Header';
import { LiveAlertsProvider } from '@/components/dashboard/live/LiveAlerts';
import Sidebar from '@/components/dashboard/Sidebar';

/**
 * Shell for the staff dashboard, ported from the Mosaic template. Pages attach it as a persistent
 * layout (`Page.layout = DashboardLayout`) so the sidebar keeps its state between visits. The `dashboard`
 * class scopes the Mosaic palette and component classes defined in app.css. Being persistent is also
 * what keeps the live check-in alerts subscribed while staff move between pages.
 */
export default function DashboardLayout({ children }: { children: ReactNode }) {
    const [sidebarOpen, setSidebarOpen] = useState(false);

    return (
        <div className="dashboard flex h-dvh overflow-hidden bg-gray-100 text-gray-600 antialiased dark:bg-gray-900 dark:text-gray-400">
            <LiveAlertsProvider>
                <Sidebar
                    sidebarOpen={sidebarOpen}
                    setSidebarOpen={setSidebarOpen}
                />

                <div className="relative flex flex-1 flex-col overflow-x-hidden overflow-y-auto">
                    <Header
                        sidebarOpen={sidebarOpen}
                        setSidebarOpen={setSidebarOpen}
                    />

                    <main className="grow">
                        <div className="mx-auto w-full max-w-[96rem] px-4 py-8 sm:px-6 lg:px-8">
                            {children}
                        </div>
                    </main>
                </div>
            </LiveAlertsProvider>
        </div>
    );
}
