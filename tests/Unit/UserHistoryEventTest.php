<?php

declare(strict_types=1);

use App\Enums\UserHistoryEvent;

/*
 * A case added without a label would break the user detail page's activity
 * table with an UnhandledMatchError.
 */
it('labels every user history event', function (UserHistoryEvent $event): void {
    expect($event->label())->not->toBe('');
})->with(UserHistoryEvent::cases());
