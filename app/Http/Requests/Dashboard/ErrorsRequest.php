<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Enums\ErrorIssueSort;
use App\Enums\ErrorIssueTab;
use App\Enums\ErrorPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The errors page's period, issue tab, sort, environment, search and page
 * cursor. The search is passed to Sentry as part of its own search syntax.
 */
final class ErrorsRequest extends FormRequest
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
            'period' => ['sometimes', 'nullable', Rule::enum(ErrorPeriod::class)],
            'tab' => ['sometimes', 'nullable', Rule::enum(ErrorIssueTab::class)],
            'sort' => ['sometimes', 'nullable', Rule::enum(ErrorIssueSort::class)],
            'environment' => ['sometimes', 'nullable', 'string', 'max:64', 'regex:/^[\w.\-]+$/'],
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
            'cursor' => ['sometimes', 'nullable', 'string', 'max:64', 'regex:/^[\d:.\-]+$/'],
        ];
    }

    /**
     * @return array{period: ErrorPeriod, tab: ErrorIssueTab, sort: ErrorIssueSort, environment: ?string, search: string, cursor: ?string}
     */
    public function filters(): array
    {
        return [
            'period' => ErrorPeriod::tryFrom((string) $this->validated('period')) ?? ErrorPeriod::DEFAULT,
            'tab' => ErrorIssueTab::tryFrom((string) $this->validated('tab')) ?? ErrorIssueTab::DEFAULT,
            'sort' => ErrorIssueSort::tryFrom((string) $this->validated('sort')) ?? ErrorIssueSort::DEFAULT,
            'environment' => $this->validated('environment'),
            'search' => mb_trim((string) $this->validated('search')),
            'cursor' => $this->validated('cursor'),
        ];
    }
}
