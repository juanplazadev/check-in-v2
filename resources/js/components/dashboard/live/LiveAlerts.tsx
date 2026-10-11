import { router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useRef,
    useState,
} from 'react';

import { playChime, unlockChime } from '@/components/dashboard/live/chime';
import type { LiveToast } from '@/components/dashboard/live/Toaster';
import Toaster from '@/components/dashboard/live/Toaster';
import type { CheckInRecord } from '@/components/dashboard/types';
import { useCheckInFeed } from '@/hooks/useCheckInFeed';
import type { FeedStatus } from '@/hooks/useFeedConnection';
import { hasAnyRole, useStaffRoles } from '@/hooks/useStaffRoles';
import dashboardRoutes from '@/routes/dashboard';

const SETTINGS_KEY = 'dashboard.liveAlerts';
const TOAST_LIFETIME_MS = 8000;
const MAX_TOASTS = 4;

export interface LiveAlertSettings {
    sound: boolean;
    desktop: boolean;
}

interface LiveAlertsContextValue {
    status: FeedStatus;
    settings: LiveAlertSettings;
    setSound: (enabled: boolean) => void;
    /** Asks for notification permission first; stays off if the browser refuses. */
    setDesktop: (enabled: boolean) => Promise<void>;
    desktopPermission: NotificationPermission | 'unsupported';
}

const LiveAlertsContext = createContext<LiveAlertsContextValue | undefined>(
    undefined,
);

function readSettings(fallback: LiveAlertSettings): LiveAlertSettings {
    try {
        const stored = window.localStorage.getItem(SETTINGS_KEY);

        return stored === null
            ? fallback
            : { ...fallback, ...(JSON.parse(stored) as LiveAlertSettings) };
    } catch {
        return fallback;
    }
}

function writeSettings(settings: LiveAlertSettings): void {
    try {
        window.localStorage.setItem(SETTINGS_KEY, JSON.stringify(settings));
    } catch {
        // Private windows and blocked storage: the setting lasts for this visit only.
    }
}

function currentPermission(): NotificationPermission | 'unsupported' {
    return typeof Notification === 'undefined'
        ? 'unsupported'
        : Notification.permission;
}

function describe(checkIn: CheckInRecord): string {
    return `${checkIn.driver.name} · ${checkIn.customer} · ${checkIn.location.name}`;
}

const UNSEEN_PREFIX = /^\(\d+\) /;

/**
 * Tells staff a check-in arrived when they are not looking at it: a toast, a chime, a desktop
 * notification and a count in the tab title. Mounted once by the persistent DashboardLayout, so
 * it keeps listening across page visits.
 */
export function LiveAlertsProvider({ children }: { children: ReactNode }) {
    const roles = useStaffRoles();
    const { component } = usePage();
    const onQueue = component === 'Dashboard/Queue/Index';

    // Shipping works the queue, so they hear about arrivals by default; everyone else opts in.
    const isShipping = hasAnyRole(roles, ['shipping', 'shipping-manager']);
    const [settings, setSettings] = useState<LiveAlertSettings>({
        sound: isShipping,
        desktop: false,
    });
    const [desktopPermission, setDesktopPermission] = useState<
        NotificationPermission | 'unsupported'
    >('unsupported');

    // Browser-only state is read after hydration, so the server render and the first client
    // render agree.
    useEffect(() => {
        setSettings((defaults) => readSettings(defaults));
        setDesktopPermission(currentPermission());
    }, []);
    const [toasts, setToasts] = useState<LiveToast[]>([]);
    const unseen = useRef(0);

    const setSound = useCallback((enabled: boolean) => {
        setSettings((previous) => {
            const next = { ...previous, sound: enabled };
            writeSettings(next);

            return next;
        });

        if (enabled) {
            unlockChime();
            playChime();
        }
    }, []);

    const setDesktop = useCallback(async (enabled: boolean) => {
        let allowed = enabled;

        if (enabled && currentPermission() === 'default') {
            allowed = (await Notification.requestPermission()) === 'granted';
        }

        setDesktopPermission(currentPermission());
        setSettings((previous) => {
            const next = {
                ...previous,
                desktop: allowed && currentPermission() === 'granted',
            };
            writeSettings(next);

            return next;
        });
    }, []);

    const dismissToast = useCallback((id: string) => {
        setToasts((previous) => previous.filter((toast) => toast.id !== id));
    }, []);

    useEffect(() => {
        const unlock = () => unlockChime();
        window.addEventListener('pointerdown', unlock);
        window.addEventListener('keydown', unlock);

        return () => {
            window.removeEventListener('pointerdown', unlock);
            window.removeEventListener('keydown', unlock);
        };
    }, []);

    // Clear the tab title count as soon as someone looks at the tab again.
    useEffect(() => {
        const onVisible = () => {
            if (document.visibilityState === 'visible') {
                unseen.current = 0;
                document.title = document.title.replace(UNSEEN_PREFIX, '');
            }
        };
        document.addEventListener('visibilitychange', onVisible);

        return () =>
            document.removeEventListener('visibilitychange', onVisible);
    }, []);

    const status = useCheckInFeed({
        onEvent: (event) => {
            // The sidebar badge counts pending check-ins, which any event can change.
            router.reload({ only: ['dashboard'], async: true });

            if (event.type !== 'created') {
                return;
            }

            const { checkIn } = event;

            if (settings.sound) {
                playChime();
            }

            if (document.visibilityState === 'hidden') {
                unseen.current += 1;
                document.title = `(${unseen.current}) ${document.title.replace(UNSEEN_PREFIX, '')}`;

                if (settings.desktop && currentPermission() === 'granted') {
                    const notification = new Notification(
                        `New check-in ${checkIn.referenceNumber}`,
                        { body: describe(checkIn), tag: checkIn.uuid },
                    );
                    notification.onclick = () => {
                        window.focus();
                        router.visit(
                            dashboardRoutes.checkIns.show.url(checkIn.uuid),
                        );
                        notification.close();
                    };
                }
            }

            // The queue shows the new card itself; a toast on top would say it twice.
            if (!onQueue) {
                const id = `${checkIn.uuid}-${Date.now()}`;
                setToasts((previous) =>
                    [
                        ...previous,
                        {
                            id,
                            title: `New check-in ${checkIn.referenceNumber}`,
                            body: describe(checkIn),
                            href: dashboardRoutes.checkIns.show.url(
                                checkIn.uuid,
                            ),
                        },
                    ].slice(-MAX_TOASTS),
                );
                window.setTimeout(() => dismissToast(id), TOAST_LIFETIME_MS);
            }
        },
        onReconnect: () => router.reload({ only: ['dashboard'], async: true }),
    });

    return (
        <LiveAlertsContext.Provider
            value={{
                status,
                settings,
                setSound,
                setDesktop,
                desktopPermission,
            }}
        >
            {children}
            <Toaster toasts={toasts} onDismiss={dismissToast} />
        </LiveAlertsContext.Provider>
    );
}

export function useLiveAlerts(): LiveAlertsContextValue {
    const context = useContext(LiveAlertsContext);

    if (context === undefined) {
        throw new Error(
            'useLiveAlerts must be used inside LiveAlertsProvider.',
        );
    }

    return context;
}
