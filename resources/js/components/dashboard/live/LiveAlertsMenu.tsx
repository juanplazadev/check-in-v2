import { BellOff, BellRing } from 'lucide-react';
import { useId } from 'react';

import { useLiveAlerts } from '@/components/dashboard/live/LiveAlerts';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Switch } from '@/components/ui/switch';

/** Header control for how new check-ins announce themselves on this browser. */
export default function LiveAlertsMenu() {
    const { settings, setSound, setDesktop, desktopPermission } =
        useLiveAlerts();
    const soundId = useId();
    const desktopId = useId();
    const anyOn = settings.sound || settings.desktop;

    return (
        <Popover>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    className="flex size-8 items-center justify-center rounded-full hover:bg-gray-100 lg:hover:bg-gray-200 dark:hover:bg-gray-700/50 dark:lg:hover:bg-gray-800"
                >
                    <span className="sr-only">Check-in alerts</span>
                    {anyOn ? (
                        <BellRing className="size-4 text-brand-green" />
                    ) : (
                        <BellOff className="size-4 text-gray-500/80 dark:text-gray-400/80" />
                    )}
                </button>
            </PopoverTrigger>
            {/* Portalled out of the `.dashboard` wrapper, so it re-applies the scope itself. */}
            <PopoverContent
                align="end"
                className="dashboard w-72 gap-0 rounded-lg border border-gray-200 bg-white p-0 shadow-lg ring-0 dark:border-gray-700/60 dark:bg-gray-800"
            >
                <div className="px-4 pt-3 pb-2 text-xs font-semibold text-gray-400 uppercase dark:text-gray-500">
                    New check-in alerts
                </div>
                <ul className="pb-2">
                    <li className="flex items-center justify-between gap-4 px-4 py-2">
                        <label
                            htmlFor={soundId}
                            className="text-sm font-medium text-gray-700 dark:text-gray-200"
                        >
                            Play a sound
                        </label>
                        <Switch
                            id={soundId}
                            checked={settings.sound}
                            onCheckedChange={setSound}
                        />
                    </li>
                    <li className="px-4 py-2">
                        <div className="flex items-center justify-between gap-4">
                            <label
                                htmlFor={desktopId}
                                className="text-sm font-medium text-gray-700 dark:text-gray-200"
                            >
                                Desktop notification
                            </label>
                            <Switch
                                id={desktopId}
                                checked={settings.desktop}
                                disabled={
                                    desktopPermission === 'denied' ||
                                    desktopPermission === 'unsupported'
                                }
                                onCheckedChange={(checked) =>
                                    void setDesktop(checked)
                                }
                            />
                        </div>
                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {desktopPermission === 'denied'
                                ? 'Blocked in this browser. Allow notifications for this site to turn it on.'
                                : desktopPermission === 'unsupported'
                                  ? 'This browser does not support notifications.'
                                  : 'Shown while this tab is in the background.'}
                        </p>
                    </li>
                </ul>
                <p className="border-t border-gray-200 bg-gray-50 px-4 py-2 text-xs text-gray-500 dark:border-gray-700/60 dark:bg-gray-700/20 dark:text-gray-400">
                    Saved on this browser only.
                </p>
            </PopoverContent>
        </Popover>
    );
}
