<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An admin edit of the roles and permissions matrix: for each editable role
 * sent, the complete list of permissions it should grant. A locked role
 * (admin, driver), the driver account permission, or any field outside these
 * rules fails the whole request.
 */
#[FailOnUnknownFields]
final class RolePermissionsUpdateRequest extends FormRequest
{
    /**
     * Who may edit is enforced by the route's permission middleware.
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
        $editableRoles = array_map(fn (Role $role): string => $role->value, Role::editable());

        return [
            'roles' => ['required', 'array:'.implode(',', $editableRoles)],
            'roles.*' => ['present', 'array'],
            'roles.*.*' => ['string', Rule::in(array_column(Role::assignablePermissions(), 'value'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'roles.array' => 'Only the shipping manager, shipping and sales roles can be changed.',
            'roles.*.*.in' => 'That permission cannot be granted to this role.',
        ];
    }

    /**
     * The permissions each sent role should grant, keyed by role. A repeated
     * permission is dropped rather than refused: distinct on roles.*.* would
     * compare across roles, which share permissions.
     *
     * @return array<string, list<Permission>>
     */
    public function grants(): array
    {
        /** @var array<string, list<string>> $roles */
        $roles = $this->validated('roles');

        return array_map(
            fn (array $permissions): array => array_map(Permission::from(...), array_values(array_unique($permissions))),
            $roles,
        );
    }
}
