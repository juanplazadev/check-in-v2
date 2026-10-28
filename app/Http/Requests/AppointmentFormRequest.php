<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Appointment\AppointmentAvailabilityResolver;
use App\Appointment\AppointmentScheduleResolver;
use App\Appointment\AppointmentSlotCapacity;
use App\DTOs\AppointmentLocationDTO;
use App\Models\Location;
use App\Queries\AppointmentLocation;
use App\Session\AppointmentSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Validator;

final class AppointmentFormRequest extends FormRequest
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
            'datetime' => ['required', 'date', 'date_format:Y-m-d H:i:s', 'after_or_equal:today'],
            'po_numbers' => ['required', 'array', 'min:1', 'max:10'],
            'po_numbers.*' => ['required', 'string', 'distinct:ignore_case', 'min:7', 'max:13', 'regex:/^(SO|PO|PU)-\d{4,10}$/i'],
            'drivers_name' => ['required', 'string', 'min:2', 'max:28'],
            'drivers_cellphone' => ['required', 'string', 'min:12', 'max:12', 'phone:US,PR,mobile'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'po_numbers.required' => __('messages.purchaseOrders.minOne'),
            'po_numbers.min' => __('messages.purchaseOrders.minOne'),
            'po_numbers.*.required' => __('messages.purchaseOrders.required'),
            'po_numbers.*.regex' => __('messages.purchaseOrders.format'),
            'po_numbers.*.distinct' => __('messages.purchaseOrders.duplicate'),
            'drivers_name.required' => __('messages.appointmentForm.driversNameRequired'),
            'drivers_name.min' => __('messages.appointmentForm.driversNameMin'),
            'drivers_name.max' => __('messages.appointmentForm.driversNameMax'),
            'drivers_cellphone.required' => __('messages.appointmentForm.cellphoneRequired'),
            'drivers_cellphone.min' => __('messages.appointmentForm.cellphoneInvalid'),
            'drivers_cellphone.max' => __('messages.appointmentForm.cellphoneInvalid'),
            'drivers_cellphone.phone' => __('messages.appointmentForm.cellphoneRegion'),
        ];
    }

    /**
     * The location is resolved from the gate context in the session (never from
     * user input) and re-queried so availability is validated against current data.
     *
     * @return array<int, callable>
     */
    public function after(
        AppointmentAvailabilityResolver $availabilityResolver,
        AppointmentScheduleResolver $scheduleResolver,
        AppointmentSlotCapacity $slotCapacity,
        AppointmentSession $session,
    ): array {
        return [
            function (Validator $validator) use ($availabilityResolver, $scheduleResolver, $slotCapacity, $session): void {
                $context = $session->getLocation();

                $location = $context instanceof AppointmentLocationDTO
                    ? resolve(AppointmentLocation::class)->execute($context->id)
                    : null;

                if (! $location instanceof Location) {
                    $validator->errors()->add('datetime', __('messages.appointmentSelectLocation.invalidLocation'));

                    return;
                }

                $appointmentDateTime = Date::parse($validator->validated()['datetime'], $location->timezone);

                $availability = $availabilityResolver->isAvailableForAppointment($location, $appointmentDateTime);

                if (! $availability->allowed) {
                    $validator->errors()->add('datetime', $availability->reason ?? __('messages.appointmentSelectLocation.invalidLocation'));

                    return;
                }

                if (! $scheduleResolver->isBookableSlot($location, $appointmentDateTime)) {
                    $validator->errors()->add('datetime', __('messages.appointmentBooking.outsideBookingWindow', [
                        'days' => $location->appointmentMaxBookingDaysAhead(),
                    ]));

                    return;
                }

                // Checked again under the location lock in BookAppointmentAction.
                if (! $slotCapacity->hasRoom($location, $appointmentDateTime)) {
                    $validator->errors()->add('datetime', __('messages.appointmentBooking.slotFull'));
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'drivers_cellphone' => '+1'.preg_replace('/\D/', '', (string) $this->input('drivers_cellphone', '')),
        ]);
    }
}
