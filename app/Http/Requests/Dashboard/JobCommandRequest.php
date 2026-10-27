<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Enums\HorizonCommand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The control the jobs page sends to Horizon: restart, pause or continue.
 */
final class JobCommandRequest extends FormRequest
{
    /**
     * Who may control the workers is enforced by the route's permission
     * middleware.
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
        return [
            'command' => ['required', Rule::enum(HorizonCommand::class)],
        ];
    }

    public function command(): HorizonCommand
    {
        return HorizonCommand::from((string) $this->validated('command'));
    }
}
