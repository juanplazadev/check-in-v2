<?php

declare(strict_types=1);

namespace App\Session;

use App\Enums\LocationWizardStep;

/**
 * The add location wizard's draft: each step's validated answers, kept in the
 * session until the location is created or the admin cancels. Only plain
 * arrays are stored, so nothing here needs the session's php serialization.
 */
final readonly class LocationWizardSession
{
    private const string DRAFT_KEY = 'locationWizard';

    /**
     * Store a step's answers. Every save restarts the TTL, so the draft only
     * expires once the admin has left it alone.
     *
     * @param  array<string, mixed>  $answers
     */
    public function put(LocationWizardStep $step, array $answers): void
    {
        $steps = $this->freshDraft()['steps'] ?? [];
        $steps[$step->value] = $answers;

        session([
            self::DRAFT_KEY => [
                'steps' => $steps,
                'storedAt' => now()->timestamp,
            ],
        ]);
    }

    /**
     * A step's answers, or null when the step has not been saved or the draft
     * has expired.
     *
     * @return array<string, mixed>|null
     */
    public function get(LocationWizardStep $step): ?array
    {
        return $this->freshDraft()['steps'][$step->value] ?? null;
    }

    /**
     * Every saved step's answers, keyed by step value.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->freshDraft()['steps'] ?? [];
    }

    /**
     * The first step without answers, or null once every step that stores
     * answers has them and the draft is ready to create.
     */
    public function firstIncompleteStep(): ?LocationWizardStep
    {
        $steps = $this->all();

        foreach (LocationWizardStep::answered() as $step) {
            if (! isset($steps[$step->value])) {
                return $step;
            }
        }

        return null;
    }

    public function forgetStep(LocationWizardStep $step): void
    {
        $draft = $this->freshDraft();

        if ($draft === null) {
            return;
        }

        unset($draft['steps'][$step->value]);

        session([self::DRAFT_KEY => $draft]);
    }

    public function forget(): void
    {
        session()->forget(self::DRAFT_KEY);
    }

    /**
     * The stored draft if it exists and is within its TTL, otherwise null.
     * Mirrors CheckInSession::freshGatePass().
     *
     * @return array{steps: array<string, array<string, mixed>>, storedAt: int}|null
     */
    private function freshDraft(): ?array
    {
        $draft = session(self::DRAFT_KEY);

        if (! $draft) {
            return null;
        }

        if (now()->timestamp - $draft['storedAt'] > config('app.location_wizard_ttl')) {
            $this->forget();

            return null;
        }

        return $draft;
    }
}
