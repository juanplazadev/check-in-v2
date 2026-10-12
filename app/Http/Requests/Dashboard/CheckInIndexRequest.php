<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Http\Requests\Dashboard\Concerns\FiltersCheckIns;
use Illuminate\Foundation\Http\FormRequest;

final class CheckInIndexRequest extends FormRequest
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
        return $this->checkInFilterRules();
    }
}
