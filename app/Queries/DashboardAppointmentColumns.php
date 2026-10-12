<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\AppointmentColumn;
use App\Models\User;

/**
 * The appointment table columns a staff member may toggle, and their saved
 * choices. Every column is offered to everyone: appointments have no optional
 * form fields.
 */
final readonly class DashboardAppointmentColumns
{
    public const string CONFIG_KEY = 'dashboard.appointments.columns';

    public const string ORDER_CONFIG_KEY = 'dashboard.appointments.column_order';

    /**
     * Visibility is an object even when empty, so it reaches the browser as a
     * map rather than an empty list.
     *
     * @return array{options: list<string>, visibility: object, order: list<string>}
     */
    public function execute(User $user): array
    {
        return [
            'options' => array_column(AppointmentColumn::cases(), 'value'),
            'visibility' => (object) $this->visibility($user),
            'order' => $this->order($user),
        ];
    }

    /**
     * The columns that may be hidden or moved: every one but the reference.
     *
     * @return list<string>
     */
    public function hideable(): array
    {
        return array_values(array_map(
            fn (AppointmentColumn $column): string => $column->value,
            array_filter(AppointmentColumn::cases(), fn (AppointmentColumn $column): bool => $column->isHideable()),
        ));
    }

    /**
     * Saved show/hide choices, limited to the columns that still exist.
     * Columns the user never toggled are left out, so the page applies its
     * defaults.
     *
     * @return array<string, bool>
     */
    private function visibility(User $user): array
    {
        $saved = data_get($user->config, self::CONFIG_KEY, []);

        if (! is_array($saved)) {
            return [];
        }

        return array_map(
            fn (mixed $visible): bool => (bool) $visible,
            array_intersect_key($saved, array_flip($this->hideable())),
        );
    }

    /**
     * Saved column order, with any column the user never placed appended in
     * default order. The reference is always first, so it is left out.
     *
     * @return list<string>
     */
    private function order(User $user): array
    {
        $saved = data_get($user->config, self::ORDER_CONFIG_KEY, []);
        $movable = $this->hideable();

        if (! is_array($saved)) {
            $saved = [];
        }

        $placed = array_values(array_intersect($saved, $movable));

        return [...$placed, ...array_values(array_diff($movable, $placed))];
    }
}
