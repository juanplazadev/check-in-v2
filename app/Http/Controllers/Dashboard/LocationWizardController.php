<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Actions\CreateLocationAction;
use App\Actions\GeocodeLocationAddressAction;
use App\Dashboard\LocationAbbreviation;
use App\Dashboard\LocationRecordResolver;
use App\Dashboard\LocationTimezones;
use App\Enums\LocationWizardStep;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\LocationWizardAddressRequest;
use App\Http\Requests\Dashboard\LocationWizardDetailsRequest;
use App\Http\Requests\Dashboard\LocationWizardFeaturesRequest;
use App\Http\Requests\Dashboard\LocationWizardHoursRequest;
use App\Http\Requests\Dashboard\LocationWizardPlacementRequest;
use App\Http\Requests\Dashboard\LocationWizardUsersRequest;
use App\Models\User;
use App\Session\LocationWizardSession;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * The add location wizard. Each step saves its validated answers to
 * LocationWizardSession and moves on to the first step still missing, which
 * is the next one on a first pass and Review once a finished draft is being
 * edited. A step whose earlier steps are not saved yet sends the admin back to
 * the first missing one. The Review step creates the location from the draft.
 */
final class LocationWizardController extends Controller
{
    public function __construct(private readonly LocationWizardSession $session) {}

    /**
     * Resumes an unfinished draft where it was left, or starts a new one.
     */
    public function start(): RedirectResponse
    {
        return $this->redirectTo($this->session->firstIncompleteStep() ?? LocationWizardStep::Review);
    }

    public function show(
        LocationWizardStep $step,
        LocationTimezones $timezones,
        LocationRecordResolver $resolver,
        LocationAbbreviation $abbreviations,
    ): Response|RedirectResponse {
        $missing = $this->session->firstIncompleteStep();

        if ($missing instanceof LocationWizardStep && $missing->position() < $step->position()) {
            return $this->redirectTo($missing);
        }

        $draft = $this->session->all();

        return Inertia::render('Dashboard/Locations/Create', [
            'step' => $step->value,
            'steps' => array_map(fn (LocationWizardStep $each): array => [
                'value' => $each->value,
                'label' => $each->label(),
                'completed' => isset($draft[$each->value]),
            ], LocationWizardStep::cases()),
            'draft' => (object) $draft,
            'timezones' => fn (): array => $step === LocationWizardStep::Placement ? $timezones->for() : [],
            'defaultMaxDistance' => config('app.location_default_max_distance'),
            'defaultHours' => fn (): array => $step === LocationWizardStep::Hours ? $this->defaultHours() : [],
            'staff' => fn (): array => in_array($step, [LocationWizardStep::Users, LocationWizardStep::Review], true)
                ? $resolver->assignableUsers()
                : [],
            'abbreviationPreview' => fn (): ?string => $step === LocationWizardStep::Review
                ? $abbreviations->suggest((string) $draft[LocationWizardStep::Details->value]['name'])
                : null,
        ]);
    }

    public function details(LocationWizardDetailsRequest $request): RedirectResponse
    {
        return $this->save(LocationWizardStep::Details, $request->validated());
    }

    /**
     * A new address moves the pin and may change the timezone, so the map
     * step's answers are dropped for the admin to confirm again.
     */
    public function address(LocationWizardAddressRequest $request, GeocodeLocationAddressAction $geocode): RedirectResponse
    {
        $validated = $request->validated();
        $placed = [...$validated, ...$geocode->handle($validated)];

        if ($this->session->get(LocationWizardStep::Address) !== $placed) {
            $this->session->forgetStep(LocationWizardStep::Placement);
        }

        return $this->save(LocationWizardStep::Address, $placed);
    }

    public function placement(LocationWizardPlacementRequest $request): RedirectResponse
    {
        return $this->save(LocationWizardStep::Placement, $request->validated());
    }

    /**
     * Turning a feature on or off changes which weeks the hours step asks
     * for, so its answers are dropped for the admin to set again.
     */
    public function features(LocationWizardFeaturesRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if ($this->session->get(LocationWizardStep::Features) !== $validated) {
            $this->session->forgetStep(LocationWizardStep::Hours);
        }

        return $this->save(LocationWizardStep::Features, $validated);
    }

    public function hours(LocationWizardHoursRequest $request): RedirectResponse
    {
        return $this->save(LocationWizardStep::Hours, $request->validated());
    }

    public function users(LocationWizardUsersRequest $request): RedirectResponse
    {
        return $this->save(LocationWizardStep::Users, $request->validated());
    }

    /**
     * @throws Throwable
     */
    public function store(#[CurrentUser] User $user, CreateLocationAction $create): RedirectResponse
    {
        $missing = $this->session->firstIncompleteStep();

        if ($missing instanceof LocationWizardStep) {
            return $this->redirectTo($missing);
        }

        $location = $create->handle($user, $this->session->all());

        $this->session->forget();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf('%s was created.', $location->name),
        ]);

        return to_route('dashboard.locations.show', ['uuid' => $location->uuid]);
    }

    /**
     * Throws the draft away.
     */
    public function destroy(): RedirectResponse
    {
        $this->session->forget();

        return to_route('dashboard.locations');
    }

    /**
     * The week the hours step starts from, from config('app.location_default_hours'),
     * shaped like the hours editor's days.
     *
     * @return list<array{is_open: bool, open_time: string, close_time: string}>
     */
    private function defaultHours(): array
    {
        /** @var array{days: list<int>, open_time: string, close_time: string} $hours */
        $hours = config('app.location_default_hours');

        return array_map(fn (int $day): array => [
            'is_open' => in_array($day, $hours['days'], true),
            'open_time' => mb_substr($hours['open_time'], 0, 5),
            'close_time' => mb_substr($hours['close_time'], 0, 5),
        ], range(0, 6));
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function save(LocationWizardStep $step, array $answers): RedirectResponse
    {
        $this->session->put($step, $answers);

        return $this->redirectTo($this->session->firstIncompleteStep() ?? LocationWizardStep::Review);
    }

    private function redirectTo(LocationWizardStep $step): RedirectResponse
    {
        return to_route('dashboard.locations.create.step', ['step' => $step->value]);
    }
}
