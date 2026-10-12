<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Which booking to link: a suggestion's uuid, or the reference number the
 * driver reads off their confirmation. Exactly one of the two.
 */
final class CheckInAppointmentLinkRequest extends FormRequest
{
    /**
     * Who may link is enforced by the route's permission middleware, and
     * which check-ins the user may see by the controller's visibleTo lookup.
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
            'appointment_uuid' => ['required_without:reference_number', 'prohibits:reference_number', 'nullable', 'uuid'],
            'reference_number' => ['required_without:appointment_uuid', 'nullable', 'string', 'max:20'],
        ];
    }

    public function appointmentUuid(): ?string
    {
        $uuid = $this->validated('appointment_uuid');

        return is_string($uuid) ? $uuid : null;
    }

    /**
     * Staff type what the driver shows them, so case and spacing are ignored.
     */
    public function referenceNumber(): ?string
    {
        $reference = $this->validated('reference_number');

        return is_string($reference) ? mb_strtoupper((string) preg_replace('/\s+/', '', $reference)) : null;
    }
}
