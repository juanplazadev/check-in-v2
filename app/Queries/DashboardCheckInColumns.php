<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\CheckInColumn;
use App\Models\Location;
use App\Models\User;

/**
 * The check-in table columns a staff member may toggle, and their saved
 * choices. A column backed by an optional form field is offered when any of
 * the user's locations collects it; staff who see every location get every
 * column without a query.
 */
final readonly class DashboardCheckInColumns
{
    public const string CONFIG_KEY = 'dashboard.check_ins.columns';

    public const string ORDER_CONFIG_KEY = 'dashboard.check_ins.column_order';

    /**
     * Visibility is an object even when empty, so it reaches the browser as a
     * map rather than an empty list.
     *
     * @return array{options: list<string>, visibility: object, order: list<string>}
     */
    public function execute(User $user): array
    {
        $available = $this->available($user);

        return [
            'options' => $available,
            'visibility' => (object) $this->visibility($user, $available),
            'order' => $this->order($user, $available),
        ];
    }

    /**
     * @return list<string>
     */
    public function available(User $user): array
    {
        $locationIds = $user->dashboardLocationIds();

        if ($locationIds === null) {
            return array_map(fn (CheckInColumn $column): string => $column->value, CheckInColumn::cases());
        }

        $enabledFlags = Location::query()
            ->whereKey($locationIds)
            ->get(['id', 'config'])
            ->flatMap(fn (Location $location): array => array_keys(array_filter($location->checkInFormFields())))
            ->unique()
            ->all();

        return array_values(array_map(
            fn (CheckInColumn $column): string => $column->value,
            array_filter(
                CheckInColumn::cases(),
                fn (CheckInColumn $column): bool => $column->formFieldFlags() === []
                    || array_intersect($column->formFieldFlags(), $enabledFlags) !== [],
            ),
        ));
    }

    /**
     * Saved show/hide choices, limited to the columns still available. Columns
     * the user never toggled are left out, so the page applies its defaults.
     *
     * @param  list<string>  $available
     * @return array<string, bool>
     */
    private function visibility(User $user, array $available): array
    {
        $saved = data_get($user->config, self::CONFIG_KEY, []);

        if (! is_array($saved)) {
            return [];
        }

        return array_map(
            fn (mixed $visible): bool => (bool) $visible,
            array_intersect_key($saved, array_flip($available)),
        );
    }

    /**
     * Saved column order, limited to the columns still available, with any
     * available column the user never placed appended in default order. The
     * reference is always first, so it is left out.
     *
     * @param  list<string>  $available
     * @return list<string>
     */
    private function order(User $user, array $available): array
    {
        $saved = data_get($user->config, self::ORDER_CONFIG_KEY, []);
        $movable = array_values(array_filter(
            $available,
            fn (string $column): bool => CheckInColumn::from($column)->isHideable(),
        ));

        if (! is_array($saved)) {
            $saved = [];
        }

        $placed = array_values(array_intersect($saved, $movable));

        return [...$placed, ...array_values(array_diff($movable, $placed))];
    }
}
