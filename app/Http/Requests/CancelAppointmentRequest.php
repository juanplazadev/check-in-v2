<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The reason is required rather than optional free text: it is the one field
 * that tells the dock why a slot came back, and it is what the admin dashboard
 * will group cancellations by.
 */
final class CancelAppointmentRequest extends FormRequest
{
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
            'uuid' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => __('messages.appointmentCancel.reasonRequired'),
            'reason.min' => __('messages.appointmentCancel.reasonRequired'),
        ];
    }

    public function uuid(): string
    {
        return (string) $this->validated('uuid');
    }

    public function reason(): string
    {
        return mb_trim((string) $this->validated('reason'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['uuid' => $this->route('uuid')]);
    }
}
