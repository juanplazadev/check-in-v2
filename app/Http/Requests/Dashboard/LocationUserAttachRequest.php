<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Enums\Permission;
use App\Models\User;
use App\Queries\LocationStaff;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class LocationUserAttachRequest extends FormRequest
{
    /**
     * Resolved once by the after hook and reused by the controller.
     *
     * @var Collection<int, User>|null
     */
    private ?Collection $resolvedUsers = null;

    /**
     * Who may attach is enforced by the route's permission middleware, and
     * which locations the user may see by the controller's LocationDirectory
     * lookup.
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
            'users' => ['required', 'array', 'min:1', 'max:100'],
            'users.*' => ['required', 'uuid', 'distinct'],
        ];
    }

    /**
     * Only location-scoped staff may be attached: anyone else either is not
     * staff or sees every location already.
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

                $this->resolvedUsers = $staff->locationScoped()
                    ->whereIn('uuid', $uuids)
                    ->get()
                    ->reject(fn (User $user): bool => $user->can(Permission::ViewAllLocations))
                    ->values();

                if ($this->resolvedUsers->count() !== count($uuids)) {
                    $validator->errors()->add('users', 'Only staff limited to their own locations can be added.');
                }
            },
        ];
    }

    /**
     * @return Collection<int, User>
     */
    public function users(): Collection
    {
        return $this->resolvedUsers ?? new Collection;
    }
}
