<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Appointment\AppointmentAvailabilityResolver;
use App\Appointment\AppointmentScheduleResolver;
use App\Appointment\AppointmentSlotCapacity;
use App\DTOs\AppointmentLocationDTO;
use App\Models\LoadType;
use App\Models\Location;
use App\Queries\AppointmentLocation;
use App\Session\AppointmentSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Validator;

final class AppointmentFormRequest extends FormRequest
{
    /**
     * The load type the driver picked, resolved against the session's
     * location once validation passes. Null at a location with no load types.
     */
    public private(set) ?LoadType $loadType = null;

    /**
     * How long the booking holds a dock, worked out from the load type, never
     * taken from the client.
     */
    public private(set) int $durationMinutes = 0;

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
            // Whether one is required depends on the location, so after() checks it.
            'load_type' => ['nullable', 'uuid'],
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
            'load_type.uuid' => __('messages.appointmentForm.loadTypeInvalid'),
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

                if (! $this->resolveLoadType($validator, $location)) {
                    return;
                }

                $this->durationMinutes = $location->appointmentDurationMinutes($this->loadType);

                $appointmentDateTime = Date::parse($validator->validated()['datetime'], $location->timezone);
                $appointmentEnd = $appointmentDateTime->copy()->addMinutes($this->durationMinutes);

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

                if (! $scheduleResolver->isBookableSlot($location, $appointmentDateTime, $appointmentEnd)) {
                    $validator->errors()->add('datetime', __('messages.appointmentBooking.runsPastClosing'));

                    return;
                }

                // Checked again under the location lock in BookAppointmentAction.
                if (! $slotCapacity->hasRoom($location, $appointmentDateTime, $appointmentEnd)) {
                    $validator->errors()->add('datetime', __('messages.appointmentBooking.slotFull'));
                }
            },
        ];
    }

    /**
     * The validated booking plus what the server worked out from it.
     *
     * @return array<string, mixed>
     */
    public function booking(): array
    {
        return [
            ...$this->validated(),
            'load_type_id' => $this->loadType?->id,
            'duration_minutes' => $this->durationMinutes,
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'drivers_cellphone' => '+1'.preg_replace('/\D/', '', (string) $this->input('drivers_cellphone', '')),
        ]);
    }

    /**
     * A location with active load types needs one of them; a location with
     * none takes no load type at all.
     */
    private function resolveLoadType(Validator $validator, Location $location): bool
    {
        $uuid = $validator->validated()['load_type'] ?? null;
        $active = LoadType::query()->whereBelongsTo($location)->active();

        if ($uuid === null) {
            if ($active->exists()) {
                $validator->errors()->add('load_type', __('messages.appointmentForm.loadTypeRequired'));

                return false;
            }

            return true;
        }

        $this->loadType = $active->where('uuid', (string) $uuid)->first();

        if (! $this->loadType instanceof LoadType) {
            $validator->errors()->add('load_type', __('messages.appointmentForm.loadTypeInvalid'));

            return false;
        }

        return true;
    }
}
