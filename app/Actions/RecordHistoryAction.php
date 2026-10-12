<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RecordHistoryEvent;
use App\Models\Appointment;
use App\Models\CheckIn;
use App\Models\RecordHistory;
use App\Models\User;

/**
 * Appends a row to a check-in or booking's trail.
 *
 * Redaction happens here rather than in the callers so no future caller can
 * leak a secret into the trail by accident, the same guarantee
 * {@see RecordUserHistoryAction} gives the account trail.
 */
final readonly class RecordHistoryAction
{
    /**
     * Context keys whose values must never be stored. Their presence is still
     * recorded - we keep the fact that they were involved, not what they were.
     *
     * @var list<string>
     */
    private const array REDACTED = [
        'drivers_license_number',
        'drivers_license_hash',
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * @param  array<string, mixed>  $context
     */
    public function handle(
        CheckIn|Appointment $record,
        RecordHistoryEvent $event,
        ?string $subject = null,
        ?string $channel = null,
        ?string $locale = null,
        ?User $user = null,
        array $context = [],
    ): RecordHistory {
        $context = $this->redact($context);

        return RecordHistory::query()->create([
            'recordable_type' => $record->getMorphClass(),
            'recordable_id' => $record->getKey(),
            'event' => $event,
            'subject' => $subject,
            'channel' => $channel,
            'locale' => $locale ?? $record->locale,
            'user_id' => $user instanceof User ? $user->id : $record->user_id,
            'context' => $context === [] ? null : $context,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function redact(array $context): array
    {
        foreach (self::REDACTED as $key) {
            if (array_key_exists($key, $context)) {
                $context[$key] = ['redacted' => true];
            }
        }

        return $context;
    }
}
