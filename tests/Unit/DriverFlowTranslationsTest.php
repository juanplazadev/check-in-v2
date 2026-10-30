<?php

declare(strict_types=1);

/*
 * Every string a driver reads while booking or checking in comes from
 * lang/{locale}/messages.php. A key added in one language only would show
 * English to the others, so each group a driver flow uses must match across
 * the app's locales.
 */

/**
 * @param  list<string>  $groups
 * @return array<string, string> "group.key" => text
 */
function driverFlowTranslations(string $locale, array $groups): array
{
    $messages = require dirname(__DIR__, 2).sprintf('/lang/%s/messages.php', $locale);

    $flatten = function (array $values, string $prefix) use (&$flatten): array {
        $flat = [];

        foreach ($values as $key => $value) {
            $flat += is_array($value)
                ? $flatten($value, $prefix.$key.'.')
                : [$prefix.$key => (string) $value];
        }

        return $flat;
    };

    $flat = [];

    foreach ($groups as $group) {
        $flat += $flatten($messages[$group] ?? [], $group.'.');
    }

    return $flat;
}

it('has every driver flow string in the locale', function (array $groups, string $locale): void {
    $english = driverFlowTranslations('en', $groups);
    $translated = driverFlowTranslations($locale, $groups);

    expect(array_keys($translated))->toEqualCanonicalizing(array_keys($english))
        ->and(array_keys(array_filter($translated, fn (string $text): bool => mb_trim($text) === '')))->toBe([]);
})->with([
    // The groups behind the pages (see config/translations.php), the emails and
    // PDF each flow sends, and the labels the server fills in.
    'booking' => [[
        'publicNavigation',
        'appointmentSelectLocation',
        'appointmentForm',
        'purchaseOrders',
        'appointmentBooking',
        'appointmentConfirmation',
        'registerCta',
        'accountHistoryRecord',
        'appointmentCopyEmail',
        'appointmentReminderEmail',
        'appointmentCancellationEmail',
        'loadDirection',
        'mailFooter',
    ]],
    'check-in' => [[
        'publicNavigation',
        'checkInSelectLocation',
        'locationRequiredModal',
        'checkInForm',
        'purchaseOrders',
        'checkInConfirmation',
        'registerCta',
        'accountHistoryRecord',
        'checkInCopyEmail',
        'checkInCopySms',
        'mailFooter',
    ]],
])->with([
    'Spanish' => 'es',
    'French' => 'fr',
]);
