<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Enums\AnalyticsPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The analytics page's period, locations and viewer timezone. A location the
 * user may not see is not refused here; the query's visibleTo scope drops it.
 */
final class AnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'period' => ['sometimes', 'nullable', Rule::enum(AnalyticsPeriod::class)],
            'locations' => ['sometimes', 'array', 'max:50'],
            'locations.*' => ['integer'],
            'tz' => ['sometimes', 'nullable', 'string', 'timezone:all'],
        ];
    }

    /**
     * @return array{period: AnalyticsPeriod, locations: list<int>, timezone: string}
     */
    public function filters(): array
    {
        return [
            'period' => AnalyticsPeriod::tryFrom((string) $this->validated('period')) ?? AnalyticsPeriod::DEFAULT,
            'locations' => array_values(array_map(intval(...), (array) $this->validated('locations', []))),
            'timezone' => $this->validated('tz') ?? config('app.timezone'),
        ];
    }
}
