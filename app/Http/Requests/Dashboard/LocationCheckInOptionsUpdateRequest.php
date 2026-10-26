<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Enums\CheckInFormField;
use App\Http\Requests\Dashboard\Concerns\ValidatesNotificationEmails;
use App\Models\Location;
use App\Models\User;
use App\Queries\LocationDirectory;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An admin edit of one section of a location's check-in form & notifications
 * page: which optional fields the form shows, keyed by their config flag, or
 * who is emailed when a driver checks in. Every field is optional, so each
 * section sends only its own; any field outside these rules fails the whole
 * request, so a tampered payload cannot reach the rest of the config.
 */
#[FailOnUnknownFields]
final class LocationCheckInOptionsUpdateRequest extends FormRequest
{
    use ValidatesNotificationEmails;

    /**
     * The location being edited, scoped like the location's page, so one the
     * user may not see is a 404.
     */
    public ?Location $location = null {
        get {
            $user = $this->user();

            return $this->location ??= $user instanceof User
                ? resolve(LocationDirectory::class)->find($user, (string) $this->route('uuid'))
                : null;
        }
    }

    /**
     * Who may edit is enforced by the route's permission middleware, and
     * which locations the user may see by the LocationDirectory lookup.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [];

        foreach (CheckInFormField::cases() as $field) {
            $rules[$field->value] = ['sometimes', 'required', 'boolean'];
        }

        return [...$rules, ...$this->notificationEmailRules()];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->notificationEmailMessages();
    }

    /**
     * The form field flags sent, keyed by their config flag.
     *
     * @return array<string, bool>
     */
    public function formFields(): array
    {
        $validated = $this->validated();
        $fields = [];

        foreach (CheckInFormField::cases() as $field) {
            if (array_key_exists($field->value, $validated)) {
                $fields[$field->value] = filter_var($validated[$field->value], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $fields;
    }

    /**
     * A location the user may not see is a 404 before any validation.
     */
    protected function prepareForValidation(): void
    {
        abort_unless($this->location instanceof Location, 404);

        $this->normalizeNotificationEmails();
    }
}
