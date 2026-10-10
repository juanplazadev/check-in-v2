<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Enums\CheckInColumn;
use App\Models\User;
use App\Queries\DashboardCheckInColumns;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CheckInColumnsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
