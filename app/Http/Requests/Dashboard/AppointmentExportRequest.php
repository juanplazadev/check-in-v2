<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Enums\AppointmentColumn;
use App\Enums\ExportScope;
use App\Enums\Permission;
use App\Http\Requests\Dashboard\Concerns\FiltersAppointments;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The table's own filters, so a current view export matches what is on
 * screen, plus which spreadsheet and, for the current view, its columns in
 * display order. Every column is offered to everyone (see
 * {@see \App\Queries\DashboardAppointmentColumns}).
 */
final class AppointmentExportRequest extends FormRequest
{
    use FiltersAppointments;

    /**
     * Each spreadsheet has its own permission; the route only lets in users
     * who hold at least one. An unknown scope is left to the rules.
     */
    public function authorize(): bool
    {
        $permission = match (ExportScope::tryFrom((string) $this->input('scope'))) {
            ExportScope::View => Permission::ExportAppointmentsView,
            ExportScope::All => Permission::ExportAppointmentsMaster,
            null => null,
        };

        return $permission === null || (bool) $this->user()?->can($permission);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->appointmentFilterRules(),
            'scope' => ['required', Rule::enum(ExportScope::class)],
            'columns' => ['exclude_unless:scope,'.ExportScope::View->value, 'required', 'list', 'max:'.count(AppointmentColumn::cases())],
            'columns.*' => ['string', 'distinct', Rule::enum(AppointmentColumn::class)],
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

        $reference = AppointmentColumn::Reference->value;

        return [$reference, ...array_values(array_diff((array) $this->validated('columns'), [$reference]))];
    }
}
