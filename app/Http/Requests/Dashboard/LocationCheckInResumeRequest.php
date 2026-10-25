<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

final class LocationCheckInResumeRequest extends FormRequest
{
    /**
     * Who may resume is enforced by the route's permission middleware, and
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
        return [];
    }
}
