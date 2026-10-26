<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Queries\LocationStaff;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The add location wizard's users step. Picking nobody is allowed: until
 * someone is added, only staff who see every location can see it.
 */
#[FailOnUnknownFields]
final class LocationWizardUsersRequest extends FormRequest
{
    /**
     * Who may add a location is enforced by the route's permission middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'users' => ['present', 'array', 'max:100'],
            'users.*' => ['required', 'uuid', 'distinct'],
        ];
    }

    /**
     * Only location-scoped staff may be added, as in LocationUserAttachRequest.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(LocationStaff $staff): array
    {
        return [
            function (Validator $validator) use ($staff): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var list<string> $uuids */
                $uuids = $validator->getData()['users'];

                if ($uuids !== [] && $staff->assignable($uuids)->count() !== count($uuids)) {
                    $validator->errors()->add('users', 'Only staff limited to their own locations can be added.');
                }
            },
        ];
    }
}
