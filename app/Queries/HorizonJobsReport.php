<?php

declare(strict_types=1);

namespace App\Queries;

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;
use stdClass;

/**
 * The jobs page, read from the same Redis records the Horizon dashboard uses:
 * whether Horizon is running, its supervisors, how long each queue is, and
 * the latest recent and failed jobs (Horizon pages them 50 at a time).
 *
 * Horizon is running while any master supervisor is, and paused only when
 * every master is paused, matching Horizon's own dashboard.
 */
final readonly class HorizonJobsReport
{
    public function __construct(
        private MasterSupervisorRepository $masters,
        private SupervisorRepository $supervisors,
        private WorkloadRepository $workload,
        private JobRepository $jobs,
        private MetricsRepository $metrics,
    ) {}

    /**
     * @return array{
     *     status: 'running'|'paused'|'inactive',
     *     stats: array{jobsPerMinute: int, processes: int, recentJobs: int, failedJobs: int, pendingJobs: int},
     *     periods: array{recent: int, failed: int},
     *     workload: list<array{name: string, length: int, wait: int, processes: int}>,
     *     supervisors: list<array{name: string, status: string, queues: list<string>, processes: int, maxProcesses: int|null}>,
     *     failedJobs: list<array{id: string, name: string, queue: string, failedAt: string|null, exception: string, summary: string, retried: bool}>,
     *     recentJobs: list<array{id: string, name: string, queue: string, status: string, queuedAt: string|null, completedAt: string|null, runtime: float|null}>,
     * }
     */
    public function execute(): array
    {
        $supervisors = array_values(array_map($this->supervisor(...), $this->supervisors->all()));

        return [
            'status' => $this->status(),
            'stats' => [
                'jobsPerMinute' => (int) round((float) $this->metrics->jobsProcessedPerMinute()),
                'processes' => array_sum(array_column($supervisors, 'processes')),
                'recentJobs' => (int) $this->jobs->countRecent(),
                'failedJobs' => (int) $this->jobs->countRecentlyFailed(),
                'pendingJobs' => (int) $this->jobs->countPending(),
            ],
            'periods' => [
                'recent' => (int) config('horizon.trim.recent'),
                'failed' => (int) config('horizon.trim.recent_failed', config('horizon.trim.failed')),
            ],
            'workload' => array_values(array_map(fn (array $queue): array => [
                'name' => (string) $queue['name'],
                'length' => (int) $queue['length'],
                'wait' => (int) $queue['wait'],
                'processes' => (int) $queue['processes'],
            ], $this->workload->get())),
            'supervisors' => $supervisors,
            'failedJobs' => array_values($this->jobs->getFailed()->map($this->failedJob(...))->all()),
            'recentJobs' => array_values($this->jobs->getRecent()->map($this->recentJob(...))->all()),
        ];
    }

    /**
     * @return 'running'|'paused'|'inactive'
     */
    private function status(): string
    {
        $masters = $this->masters->all();

        if ($masters === []) {
            return 'inactive';
        }

        return collect($masters)->every(fn (stdClass $master): bool => $master->status === 'paused') ? 'paused' : 'running';
    }

    /**
     * Horizon names a supervisor "{master}:{name}"; the page shows the name.
     *
     * @return array{name: string, status: string, queues: list<string>, processes: int, maxProcesses: int|null}
     */
    private function supervisor(stdClass $supervisor): array
    {
        $options = is_array($supervisor->options) ? $supervisor->options : [];

        return [
            'name' => Str::afterLast((string) $supervisor->name, ':'),
            'status' => (string) $supervisor->status,
            'queues' => array_values(array_filter(explode(',', (string) ($options['queue'] ?? '')))),
            'processes' => (int) array_sum(is_array($supervisor->processes) ? $supervisor->processes : []),
            'maxProcesses' => isset($options['maxProcesses']) ? (int) $options['maxProcesses'] : null,
        ];
    }

    /**
     * @return array{id: string, name: string, queue: string, failedAt: string|null, exception: string, summary: string, retried: bool}
     */
    private function failedJob(stdClass $job): array
    {
        $exception = mb_convert_encoding((string) $job->exception, 'UTF-8');
        $retriedBy = $job->retried_by === null ? [] : (array) json_decode((string) $job->retried_by, true);

        return [
            'id' => (string) $job->id,
            'name' => (string) $job->name,
            'queue' => (string) $job->queue,
            'failedAt' => $this->timestamp($job->failed_at),
            'exception' => $exception,
            'summary' => Str::limit(Str::before($exception, "\n"), 300),
            'retried' => $retriedBy !== [],
        ];
    }

    /**
     * The runtime is from when a worker reserved the job until it finished.
     *
     * @return array{id: string, name: string, queue: string, status: string, queuedAt: string|null, completedAt: string|null, runtime: float|null}
     */
    private function recentJob(stdClass $job): array
    {
        $payload = (array) json_decode((string) $job->payload, true);
        $reservedAt = is_numeric($job->reserved_at ?? null) ? (float) $job->reserved_at : null;
        $finishedAt = is_numeric($job->completed_at ?? null) ? (float) $job->completed_at
            : (is_numeric($job->failed_at ?? null) ? (float) $job->failed_at : null);

        return [
            'id' => (string) $job->id,
            'name' => (string) $job->name,
            'queue' => (string) $job->queue,
            'status' => (string) $job->status,
            'queuedAt' => $this->timestamp($payload['pushedAt'] ?? null),
            'completedAt' => $this->timestamp($finishedAt),
            'runtime' => $reservedAt !== null && $finishedAt !== null ? round($finishedAt - $reservedAt, 2) : null,
        ];
    }

    private function timestamp(mixed $value): ?string
    {
        return is_numeric($value) ? Date::createFromTimestamp((float) $value)->toIso8601String() : null;
    }
}
