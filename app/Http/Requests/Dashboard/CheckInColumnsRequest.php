<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Enums\CheckInColumn;
use App\Enums\Permission;
use App\Models\User;
use App\Queries\DashboardCheckInColumns;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CheckInColumnsRequest extends FormRequest
{
    /**
     * Column display and column order are separate permissions; the route
     * only lets in users who hold at least one.
     */
    public function authorize(): bool
    {
        /** @var User $user */
        $user = $this->user();

        return (! $this->has('columns') || $user->can(Permission::SaveCheckInColumnDisplay))
            && (! $this->has('order') || $user->can(Permission::SaveCheckInColumnOrder));
    }

    /**
     * Only columns offered to this user may be saved, and never the reference,
     * which is always shown first. Visibility, order or both may be sent.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(DashboardCheckInColumns $columns): array
    {
        /** @var User $user */
        $user = $this->user();

        $hideable = array_filter(
            $columns->available($user),
            fn (string $column): bool => CheckInColumn::from($column)->isHideable(),
        );

        return [
            'columns' => ['required_without:order', 'array:'.implode(',', $hideable)],
            'columns.*' => ['required', 'boolean'],
            'order' => ['required_without:columns', 'list'],
            'order.*' => ['required', 'string', 'distinct', Rule::in($hideable)],
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function columns(): array
    {
        return array_map(boolval(...), (array) $this->validated('columns', []));
    }

    /**
     * The new column order, or null when only visibility was sent.
     *
     * @return list<string>|null
     */
    public function order(): ?array
    {
        $order = $this->validated('order');

        return is_array($order) ? array_values($order) : null;
    }
}
