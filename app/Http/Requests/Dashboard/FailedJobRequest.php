<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

final class FailedJobRequest extends FormRequest
{
    /**
     * Who may retry or delete failed jobs is enforced by the route's
     * permission middleware; the job id is constrained to a uuid by the route.
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
