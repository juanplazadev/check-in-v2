<?php

declare(strict_types=1);

use App\Enums\HorizonCommand;
use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\UserHistoryEvent;
use App\Models\User;
use App\Models\UserHistory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Laravel\Horizon\Contracts\HorizonCommandQueue;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;
use Laravel\Horizon\SupervisorCommands\ContinueWorking;
use Laravel\Horizon\SupervisorCommands\Pause;
use Laravel\Horizon\SupervisorCommands\Restart;
use Ramsey\Uuid\UuidInterface;

use function Pest\Laravel\mock;

/*
 * Pins the admin jobs page: the queue report read from Horizon's Redis
 * records, the worker controls sent through Horizon's command queue, and
 * retrying or deleting failed jobs. Horizon's repositories are mocked so the
 * tests never touch the Redis the local Horizon uses. Who may open the page
 * is pinned in DashboardPagesTest.
 */

const FAILED_JOB_ID = '9b1f7a52-3c4d-4e5f-8a6b-7c8d9e0f1a2b';

function jobsAdmin(): User
{
    return User::factory()->withRole(Role::Admin)->create();
}

/**
 * @param  list<string>  $statuses  One master supervisor per status.
 * @return list<stdClass>
 */
function horizonMasters(array $statuses): array
{
    return array_map(fn (string $status, int $index): stdClass => (object) [
        'name' => 'horizon-'.$index,
        'status' => $status,
    ], $statuses, array_keys($statuses));
}

/**
 * @param  array<string, mixed>  $attributes
 */
function failedHorizonJob(array $attributes = []): stdClass
{
    return (object) [
        'id' => FAILED_JOB_ID,
        'name' => 'App\\Jobs\\GenerateCheckInPdf',
        'connection' => 'redis',
        'queue' => 'pdf',
        'payload' => json_encode(['uuid' => FAILED_JOB_ID, 'displayName' => 'App\\Jobs\\GenerateCheckInPdf', 'attempts' => 2]),
        'exception' => "RuntimeException: Chrome crashed\n#0 /app/Jobs/GenerateCheckInPdf.php(42)",
        'failed_at' => '1792065600.123',
        'retried_by' => null,
        ...$attributes,
    ];
}

/**
 * @param  list<string>  $masterStatuses
 */
function fakeHorizonReport(array $masterStatuses = ['running']): void
{
    mock(MasterSupervisorRepository::class)->shouldReceive('all')->andReturn(horizonMasters($masterStatuses));
    mock(SupervisorRepository::class)->shouldReceive('all')->andReturn([(object) [
        'name' => 'horizon-0:supervisor-pdf',
        'status' => 'running',
        'processes' => ['redis:pdf' => 2],
        'options' => ['queue' => 'pdf', 'maxProcesses' => 3],
    ]]);
    mock(WorkloadRepository::class)->shouldReceive('get')->andReturn([
        ['name' => 'pdf', 'length' => 4, 'wait' => 12, 'processes' => 2, 'split_queues' => null],
    ]);
    mock(MetricsRepository::class)->shouldReceive('jobsProcessedPerMinute')->andReturn(7.6);
    mock(JobRepository::class, function ($mock): void {
        $mock->shouldReceive('countRecent')->andReturn(30);
        $mock->shouldReceive('countRecentlyFailed')->andReturn(1);
        $mock->shouldReceive('countPending')->andReturn(4);
        $mock->shouldReceive('getFailed')->andReturn(collect([failedHorizonJob()]));
        $mock->shouldReceive('getRecent')->andReturn(collect([(object) [
            'id' => 'recent-1',
            'name' => 'App\\Jobs\\SendCheckInEmail',
            'queue' => 'default',
            'status' => 'completed',
            'payload' => json_encode(['pushedAt' => '1792065590.0']),
            'reserved_at' => '1792065595.0',
            'completed_at' => '1792065596.5',
            'failed_at' => null,
        ]]));
    });
}

/**
 * @param  Closure(AssertableInertia): AssertableInertia  $assert
 */
function jobsPage(Closure $assert): void
{
    test()->actingAs(jobsAdmin())
        ->get(route('dashboard.jobs'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Dashboard/Jobs/Index')
            ->missing('report')
            ->loadDeferredProps(fn (AssertableInertia $reload): AssertableInertia => $assert($reload)));
}

it('reports the workers, queues, failed and recent jobs from Horizon', function (): void {
    fakeHorizonReport();

    jobsPage(fn (AssertableInertia $page): AssertableInertia => $page
        ->where('report.state', 'ready')
        ->where('report.data.status', 'running')
        ->where('report.data.stats', ['jobsPerMinute' => 8, 'processes' => 2, 'recentJobs' => 30, 'failedJobs' => 1, 'pendingJobs' => 4])
        ->where('report.data.workload', [['name' => 'pdf', 'length' => 4, 'wait' => 12, 'processes' => 2]])
        ->where('report.data.supervisors', [['name' => 'supervisor-pdf', 'status' => 'running', 'queues' => ['pdf'], 'processes' => 2, 'maxProcesses' => 3]])
        ->where('report.data.failedJobs.0.id', FAILED_JOB_ID)
        ->where('report.data.failedJobs.0.summary', 'RuntimeException: Chrome crashed')
        ->where('report.data.failedJobs.0.retried', false)
        ->where('report.data.recentJobs.0.status', 'completed')
        ->where('report.data.recentJobs.0.runtime', 1.5));
});

it('reads Horizon as paused only when every master is paused, and inactive with none', function (array $statuses, string $expected): void {
    fakeHorizonReport($statuses);

    jobsPage(fn (AssertableInertia $page): AssertableInertia => $page->where('report.data.status', $expected));
})->with([
    'all paused' => [['paused', 'paused'], 'paused'],
    'one still running' => [['paused', 'running'], 'running'],
    'none' => [[], 'inactive'],
]);

it('shows the page as unavailable and reports it when Redis cannot be reached', function (): void {
    Exceptions::fake();
    mock(MasterSupervisorRepository::class)->shouldReceive('all')->andThrow(new RedisException('Connection refused'));
    mock(SupervisorRepository::class)->shouldReceive('all')->andThrow(new RedisException('Connection refused'));

    jobsPage(fn (AssertableInertia $page): AssertableInertia => $page->where('report', ['state' => 'unavailable']));

    Exceptions::assertReported(RedisException::class);
});

it('queues each worker command for every master and logs it on the admin trail', function (HorizonCommand $command, string $commandClass): void {
    $admin = jobsAdmin();
    mock(MasterSupervisorRepository::class)->shouldReceive('all')->andReturn(horizonMasters(['running', 'running']));
    $queue = mock(HorizonCommandQueue::class);
    $queue->shouldReceive('push')->once()->with('master:horizon-0', $commandClass);
    $queue->shouldReceive('push')->once()->with('master:horizon-1', $commandClass);

    $this->actingAs($admin)
        ->post(route('dashboard.jobs.command'), ['command' => $command->value])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => $command->successMessage()]);

    expect(UserHistory::query()->sole())
        ->user_id->toBe($admin->id)
        ->event->toBe($command->historyEvent());
})->with([
    'restart' => [HorizonCommand::Restart, Restart::class],
    'pause' => [HorizonCommand::Pause, Pause::class],
    'continue' => [HorizonCommand::Continue, ContinueWorking::class],
]);

it('says Horizon is not running when there is no master to command', function (): void {
    mock(MasterSupervisorRepository::class)->shouldReceive('all')->andReturn([]);
    mock(HorizonCommandQueue::class)->shouldNotReceive('push');

    $this->actingAs(jobsAdmin())
        ->post(route('dashboard.jobs.command'), ['command' => HorizonCommand::Restart->value])
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => "Horizon isn't running, so there are no workers to control."]);

    expect(UserHistory::query()->count())->toBe(0);
});

it('rejects an unknown worker command', function (): void {
    mock(HorizonCommandQueue::class)->shouldNotReceive('push');

    $this->actingAs(jobsAdmin())
        ->post(route('dashboard.jobs.command'), ['command' => 'terminate'])
        ->assertSessionHasErrors(['command' => 'The selected command is invalid.']);
});

it('pushes a fresh copy of a failed job onto its queue and logs it on the admin trail', function (): void {
    Queue::fake();
    $admin = jobsAdmin();
    mock(JobRepository::class, function ($mock): void {
        $mock->shouldReceive('findFailed')->with(FAILED_JOB_ID)->andReturn(failedHorizonJob());
        $mock->shouldReceive('storeRetryReference')->once()->with(FAILED_JOB_ID, Mockery::type(UuidInterface::class));
    });

    $this->actingAs($admin)
        ->post(route('dashboard.jobs.failed.retry', ['jobId' => FAILED_JOB_ID]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'The job was queued again.']);

    $retry = Queue::pushedRaw()->sole();
    expect($retry['queue'])->toBe('pdf')
        ->and(json_decode((string) $retry['payload'], true))
        ->retry_of->toBe(FAILED_JOB_ID)
        ->attempts->toBe(0)
        ->and(UserHistory::query()->sole())
        ->event->toBe(UserHistoryEvent::FailedJobRetried)
        ->changes->toBe(['job' => 'App\\Jobs\\GenerateCheckInPdf']);
});

it('reports a failed job that no longer exists instead of retrying it', function (): void {
    Queue::fake();
    mock(JobRepository::class)->shouldReceive('findFailed')->with(FAILED_JOB_ID)->andReturn(null);

    $this->actingAs(jobsAdmin())
        ->post(route('dashboard.jobs.failed.retry', ['jobId' => FAILED_JOB_ID]))
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'That failed job no longer exists.']);

    expect(Queue::pushedRaw())->toBeEmpty()
        ->and(UserHistory::query()->count())->toBe(0);
});

it('retries every failed job not yet retried, across pages', function (): void {
    Queue::fake();
    $firstPage = collect(range(0, 49))->mapWithKeys(fn (int $index): array => [$index => failedHorizonJob([
        'id' => 'first-'.$index,
        // Every tenth job was already retried.
        'retried_by' => $index % 10 === 0 ? json_encode([['id' => 'retry-'.$index]]) : null,
    ])]);
    $secondPage = new Collection([50 => failedHorizonJob(['id' => 'second-0'])]);
    mock(JobRepository::class, function ($mock) use ($firstPage, $secondPage): void {
        $mock->shouldReceive('getFailed')->with(null)->andReturn($firstPage);
        $mock->shouldReceive('getFailed')->with('49')->andReturn($secondPage);
        $mock->shouldReceive('findFailed')->andReturnUsing(fn (string $id): ?stdClass => $firstPage->merge($secondPage)->firstWhere('id', $id));
        $mock->shouldReceive('storeRetryReference')->times(46)->with(Mockery::not('first-0'), Mockery::any());
    });

    $this->actingAs(jobsAdmin())
        ->post(route('dashboard.jobs.failed.retryAll'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => '46 failed jobs were queued again.']);

    expect(Queue::pushedRaw())->toHaveCount(46)
        ->and(UserHistory::query()->sole())
        ->event->toBe(UserHistoryEvent::FailedJobsRetried)
        ->changes->toBe(['count' => 46]);
});

it('deletes a failed job from Horizon and the failed_jobs table', function (): void {
    DB::table('failed_jobs')->insert([
        'uuid' => FAILED_JOB_ID,
        'connection' => 'redis',
        'queue' => 'pdf',
        'payload' => '{}',
        'exception' => 'RuntimeException: Chrome crashed',
    ]);
    mock(JobRepository::class, function ($mock): void {
        $mock->shouldReceive('findFailed')->with(FAILED_JOB_ID)->andReturn(failedHorizonJob());
        $mock->shouldReceive('deleteFailed')->once()->with(FAILED_JOB_ID);
    });

    $this->actingAs(jobsAdmin())
        ->delete(route('dashboard.jobs.failed.destroy', ['jobId' => FAILED_JOB_ID]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'The failed job was deleted.']);

    $this->assertDatabaseMissing('failed_jobs', ['uuid' => FAILED_JOB_ID]);
    expect(UserHistory::query()->sole()->event)->toBe(UserHistoryEvent::FailedJobDeleted);
});

it('forbids the job controls to a user who may only view jobs', function (string $method, Closure $url): void {
    $viewer = User::factory()->withRole(Role::Shipping)->create();
    $viewer->givePermissionTo(Permission::ViewJobs);
    mock(HorizonCommandQueue::class)->shouldNotReceive('push');
    mock(JobRepository::class)->shouldNotReceive('findFailed', 'deleteFailed', 'getFailed');

    $this->actingAs($viewer)
        ->{$method}($url(), ['command' => HorizonCommand::Restart->value])
        ->assertForbidden();
})->with([
    'command' => ['post', fn (): string => route('dashboard.jobs.command')],
    'retry' => ['post', fn (): string => route('dashboard.jobs.failed.retry', ['jobId' => FAILED_JOB_ID])],
    'retry all' => ['post', fn (): string => route('dashboard.jobs.failed.retryAll')],
    'delete' => ['delete', fn (): string => route('dashboard.jobs.failed.destroy', ['jobId' => FAILED_JOB_ID])],
]);

it('404s a failed job id that is not a uuid', function (): void {
    $this->actingAs(jobsAdmin())
        ->post('/dashboard/jobs/failed/not-a-uuid/retry')
        ->assertNotFound();
});
