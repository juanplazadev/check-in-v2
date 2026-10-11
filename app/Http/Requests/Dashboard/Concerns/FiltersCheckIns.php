<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard\Concerns;

use App\Enums\CheckInDateField;
use App\Enums\CheckInStatus;
use App\Queries\DashboardCheckIns;
use Illuminate\Validation\Rule;

/**
 * The check-in table's search, tab, locations, date range, paging and sort,
 * shared by the table and its current view export so both read the same rows.
 */
trait FiltersCheckIns
{
    /**
     * @return array{search: string|null, status: string|null, locations: list<int>, dateField: string|null, from: string|null, to: string|null, timezone: string, perPage: int, sort: string|null, direction: string}
     */
    public function filters(): array
    {
        $search = mb_trim((string) $this->validated('search'));
        $from = $this->validated('from');
        $to = $this->validated('to');
        $hasRange = $from !== null && $to !== null;

        return [
            'search' => mb_strlen($search) < DashboardCheckIns::MIN_SEARCH_LENGTH ? null : $search,
            'status' => $this->validated('status'),
            'locations' => array_values(array_map(intval(...), (array) $this->validated('locations', []))),
            'dateField' => $hasRange ? $this->validated('date_field') : null,
            'from' => $hasRange ? $from : null,
            'to' => $hasRange ? $to : null,
            'timezone' => $this->validated('tz') ?? config('app.timezone'),
            'perPage' => (int) ($this->validated('per_page') ?? DashboardCheckIns::DEFAULT_PER_PAGE),
            'sort' => $this->validated('sort'),
            'direction' => $this->validated('direction') ?? 'asc',
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function checkInFilterRules(): array
    {
        return [
            // See SearchRequest: no control characters or invalid UTF-8.
            'search' => ['sometimes', 'nullable', 'string', 'max:100', 'regex:/^[^\p{C}]+$/u'],
            'status' => ['sometimes', 'nullable', Rule::in([...array_column(CheckInStatus::cases(), 'value'), DashboardCheckIns::ARCHIVED])],
            'locations' => ['sometimes', 'array', 'max:50'],
            'locations.*' => ['integer'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['sometimes', 'integer', Rule::in(DashboardCheckIns::PER_PAGE_OPTIONS)],
            // A range is a pair of calendar days, read in the viewer's timezone. No
            // `sometimes` on the pair: it would skip required_with when a key is absent.
            'date_field' => ['nullable', Rule::enum(CheckInDateField::class), 'required_with:from,to'],
            'from' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'required_with:to'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:2100-12-31', 'required_with:from'],
            'tz' => ['sometimes', 'nullable', 'string', 'timezone:all'],
            'sort' => ['sometimes', 'nullable', Rule::in(DashboardCheckIns::SORTABLE)],
            'direction' => ['sometimes', 'nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
