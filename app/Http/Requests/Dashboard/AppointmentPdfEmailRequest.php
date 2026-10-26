<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

final class AppointmentPdfEmailRequest extends FormRequest
{
    /**
     * Who may email the PDF is enforced by the route's permission middleware,
     * and which appointments the user may see by the controller's visibleTo lookup.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Nothing is read from the body: the recipient is always the driver's
     * address on file, never one supplied by the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
