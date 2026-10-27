<?php

declare(strict_types=1);

namespace App\Enums;

use Laravel\Horizon\SupervisorCommands\ContinueWorking;
use Laravel\Horizon\SupervisorCommands\Pause;
use Laravel\Horizon\SupervisorCommands\Restart;

/**
 * The controls the jobs page sends to Horizon. Each one is queued in Redis
 * for every master supervisor, which runs it on its next loop, so the web
 * container never needs to signal the Horizon process directly.
 */
enum HorizonCommand: string
{
    case Restart = 'restart';
    case Pause = 'pause';
    case Continue = 'continue';

    /**
     * @return class-string
     */
    public function commandClass(): string
    {
        return match ($this) {
            self::Restart => Restart::class,
            self::Pause => Pause::class,
            self::Continue => ContinueWorking::class,
        };
    }

    public function historyEvent(): UserHistoryEvent
    {
        return match ($this) {
            self::Restart => UserHistoryEvent::QueueWorkersRestarted,
            self::Pause => UserHistoryEvent::QueuePaused,
            self::Continue => UserHistoryEvent::QueueResumed,
        };
    }

    public function successMessage(): string
    {
        return match ($this) {
            self::Restart => 'The queue workers are restarting.',
            self::Pause => 'The queue workers are pausing. Running jobs will finish first.',
            self::Continue => 'The queue workers are resuming.',
        };
    }
}
