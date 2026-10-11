<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Enums\CheckInColumn;
use App\Enums\CheckInExportScope;
use App\Http\Requests\Dashboard\Concerns\FiltersCheckIns;
use App\Models\User;
use App\Queries\DashboardCheckInColumns;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The table's own filters, so a current view export matches what is on
 * screen, plus which spreadsheet and, for the current view, its columns in
 * display order. Only columns offered to this user may be exported.
 */
final class CheckInExportRequest extends FormRequest
{
    use FiltersCheckIns;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        return [
            ...$this->checkInFilterRules(),
            'scope' => ['required', Rule::enum(CheckInExportScope::class)],
            'columns' => ['exclude_unless:scope,'.CheckInExportScope::View->value, 'required', 'list', 'max:'.count(CheckInColumn::cases())],
            'columns.*' => ['string', 'distinct', Rule::in(resolve(DashboardCheckInColumns::class)->available($user))],
        ];
    }

    public function scope(): CheckInExportScope
    {
        return CheckInExportScope::from($this->validated('scope'));
    }

    /**
     * The current view's columns, reference first as on screen. Empty for the
     * master export, which has its own fixed set.
     *
     * @return list<string>
     */
    public function columns(): array
    {
        if ($this->scope() === CheckInExportScope::All) {
            return [];
        }

        $reference = CheckInColumn::Reference->value;

        return [$reference, ...array_values(array_diff((array) $this->validated('columns'), [$reference]))];
    }
}
