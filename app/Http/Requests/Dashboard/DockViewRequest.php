<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Which location and day the dock view shows. Whether the user may see the
 * location is checked by the controller.
 */
final class DockViewRequest extends FormRequest
{
    /**
     * Who may open the page is enforced by the route's permission middleware.
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
            'location' => ['nullable', 'integer'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function locationId(): ?int
    {
        $location = $this->validated('location');

        return $location === null ? null : (int) $location;
    }

    public function viewedDate(): ?string
    {
        $date = $this->validated('date');

        return is_string($date) ? $date : null;
    }
}
