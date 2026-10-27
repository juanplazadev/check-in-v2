<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\HorizonCommand;
use App\Models\User;
use Laravel\Horizon\Contracts\HorizonCommandQueue;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\MasterSupervisor;

/**
 * Queues a control command for every running Horizon master supervisor and
 * logs it on the acting admin's trail. Masters read their command queue on
 * each loop, so the command lands within a few seconds. With no master
 * running there is nothing to command and nothing is logged.
 */
final readonly class SendHorizonCommandAction
{
    public function __construct(
        private MasterSupervisorRepository $masters,
        private HorizonCommandQueue $commands,
        private RecordUserHistoryAction $recordUserHistory,
    ) {}

    /**
     * @return int How many master supervisors were sent the command.
     */
    public function handle(HorizonCommand $command, User $actor): int
    {
        $masters = $this->masters->all();

        foreach ($masters as $master) {
            $this->commands->push(MasterSupervisor::commandQueueFor($master->name), $command->commandClass());
        }

        if ($masters !== []) {
            $this->recordUserHistory->handle($actor, $command->historyEvent());
        }

        return count($masters);
    }
}
