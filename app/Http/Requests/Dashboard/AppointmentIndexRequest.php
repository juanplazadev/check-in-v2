<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Http\Requests\Dashboard\Concerns\FiltersAppointments;
use Illuminate\Foundation\Http\FormRequest;

final class AppointmentIndexRequest extends FormRequest
{
    use FiltersAppointments;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->appointmentFilterRules();
    }
}
