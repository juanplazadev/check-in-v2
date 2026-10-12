<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Enums\CheckInColumn;
use App\Enums\ExportScope;
use App\Enums\Permission;
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

    /**
     * Each spreadsheet has its own permission; the route only lets in users
     * who hold at least one. An unknown scope is left to the rules.
     */
    public function authorize(): bool
    {
        $permission = match (ExportScope::tryFrom((string) $this->input('scope'))) {
            ExportScope::View => Permission::ExportCheckInsView,
            ExportScope::All => Permission::ExportCheckInsMaster,
            null => null,
        };

        return $permission === null || (bool) $this->user()?->can($permission);
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
            'scope' => ['required', Rule::enum(ExportScope::class)],
            'columns' => ['exclude_unless:scope,'.ExportScope::View->value, 'required', 'list', 'max:'.count(CheckInColumn::cases())],
            'columns.*' => ['string', 'distinct', Rule::in(resolve(DashboardCheckInColumns::class)->available($user))],
        ];
    }

    public function scope(): ExportScope
    {
        return ExportScope::from($this->validated('scope'));
    }

    /**
     * The current view's columns, reference first as on screen. Empty for the
     * master export, which has its own fixed set.
     *
     * @return list<string>
     */
    public function columns(): array
    {
        if ($this->scope() === ExportScope::All) {
            return [];
        }

        $reference = CheckInColumn::Reference->value;

        return [$reference, ...array_values(array_diff((array) $this->validated('columns'), [$reference]))];
    }
}
