import { Columns3, GripVertical } from 'lucide-react';
import type { KeyboardEvent } from 'react';
import { useState } from 'react';

import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

export interface DataTableColumnOption {
    id: string;
    label: string;
}

/** `ids` with `id` moved to `index`, shifting the rest along. */
function moveTo(ids: string[], id: string, index: number): string[] {
    const rest = ids.filter((other) => other !== id);

    return [...rest.slice(0, index), id, ...rest.slice(index)];
}

/**
 * Show/hide popover for table columns, styled like `DataTableFilter`. With `onReorder`, rows also
 * drag (or move with the arrow keys on their handle) to change the column order; options render
 * in the order given. The caller owns the state, so it decides where the choice is saved.
 */
export default function DataTableColumns({
    options,
    isVisible,
    onToggle,
    onReorder,
    onReset,
    title = 'Columns',
}: {
    options: DataTableColumnOption[];
    isVisible: (id: string) => boolean;
    onToggle: (id: string, visible: boolean) => void;
    onReorder?: (ids: string[]) => void;
    onReset: () => void;
    title?: string;
}) {
    const [draggingId, setDraggingId] = useState<string | null>(null);
    const [overId, setOverId] = useState<string | null>(null);
    const ids = options.map((option) => option.id);

    const reorder = (id: string, index: number) => {
        if (index >= 0 && index < ids.length) {
            onReorder?.(moveTo(ids, id, index));
        }
    };

    const endDrag = () => {
        setDraggingId(null);
        setOverId(null);
    };

    const moveWithKeys = (event: KeyboardEvent, id: string) => {
        const step =
            event.key === 'ArrowUp' ? -1 : event.key === 'ArrowDown' ? 1 : 0;

        if (step !== 0) {
            event.preventDefault();
            reorder(id, ids.indexOf(id) + step);
        }
    };

    /** Where the dragged row would land: above the target when moving up, below when down. */
    const dropIndicator = (id: string): string => {
        if (draggingId === null || overId !== id || draggingId === id) {
            return 'border-y border-transparent';
        }

        return ids.indexOf(draggingId) > ids.indexOf(id)
            ? 'border-y border-transparent border-t-brand-green'
            : 'border-y border-transparent border-b-brand-green';
    };

    return (
        <Popover>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    className="btn border-gray-200 bg-white px-2.5 text-gray-400 hover:border-gray-300 dark:border-gray-700/60 dark:bg-gray-800 dark:text-gray-500 dark:hover:border-gray-600"
                >
                    <span className="sr-only">{title}</span>
                    <Columns3 className="size-4" />
                </button>
            </PopoverTrigger>
            {/* Portalled out of the `.dashboard` wrapper, so it re-applies the scope itself. */}
            <PopoverContent
                align="end"
                className="dashboard w-56 gap-0 overflow-hidden rounded-lg border border-gray-200 bg-white p-0 pt-1.5 shadow-lg ring-0 dark:border-gray-700/60 dark:bg-gray-800"
            >
                <div className="px-3 pt-1.5 pb-2 text-xs font-semibold text-gray-400 uppercase dark:text-gray-500">
                    {title}
                </div>
                <ul className="mb-4 max-h-80 overflow-y-auto">
                    {options.map((option) => (
                        <li
                            key={option.id}
                            className={`flex items-center px-3 py-1 ${onReorder ? dropIndicator(option.id) : ''} ${draggingId === option.id ? 'opacity-50' : ''}`}
                            draggable={onReorder !== undefined}
                            onDragStart={(event) => {
                                event.dataTransfer.effectAllowed = 'move';
                                event.dataTransfer.setData(
                                    'text/plain',
                                    option.id,
                                );
                                setDraggingId(option.id);
                            }}
                            onDragOver={(event) => {
                                if (draggingId !== null) {
                                    event.preventDefault();
                                    setOverId(option.id);
                                }
                            }}
                            onDrop={(event) => {
                                event.preventDefault();

                                if (draggingId !== null) {
                                    reorder(draggingId, ids.indexOf(option.id));
                                }

                                endDrag();
                            }}
                            onDragEnd={endDrag}
                        >
                            {onReorder && (
                                <button
                                    type="button"
                                    className="mr-1.5 cursor-grab text-gray-300 hover:text-gray-500 dark:text-gray-600 dark:hover:text-gray-400"
                                    aria-label={`Move ${option.label} (arrow keys)`}
                                    onKeyDown={(event) =>
                                        moveWithKeys(event, option.id)
                                    }
                                >
                                    <GripVertical className="size-3.5" />
                                </button>
                            )}
                            <label className="flex items-center">
                                <input
                                    type="checkbox"
                                    className="form-checkbox"
                                    checked={isVisible(option.id)}
                                    onChange={(event) =>
                                        onToggle(
                                            option.id,
                                            event.target.checked,
                                        )
                                    }
                                />
                                <span className="ml-2 text-sm font-medium text-gray-600 dark:text-gray-300">
                                    {option.label}
                                </span>
                            </label>
                        </li>
                    ))}
                </ul>
                <div className="border-t border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700/60 dark:bg-gray-700/20">
                    <button
                        type="button"
                        className="btn-xs w-full border-gray-200 bg-white text-gray-600 hover:border-gray-300 dark:border-gray-700/60 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-gray-600"
                        onClick={onReset}
                    >
                        Reset to default
                    </button>
                </div>
            </PopoverContent>
        </Popover>
    );
}
