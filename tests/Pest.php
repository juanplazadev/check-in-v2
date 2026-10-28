<?php

declare(strict_types=1);

use App\Pdf\FakeRecordPdfRenderer;
use App\Pdf\RecordPdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Spatie\SimpleExcel\SimpleExcelReader;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        Str::createRandomStringsNormally();
        Str::createUuidsNormally();
        Http::preventStrayRequests();
        Process::preventStrayProcesses();
        Sleep::fake();

        // Browsershot shells out via Symfony's Process, which
        // preventStrayProcesses() does not intercept, so an unfaked render
        // would really spawn Chrome. The fake still renders the Blade view.
        $this->swap(RecordPdfRenderer::class, new FakeRecordPdfRenderer);

        $this->freezeTime();
    })
    ->in('Browser', 'Feature', 'Unit');

pest()->beforeEach(function (): void {
    $unreachable = viteDevServerUnreachable();

    if ($unreachable !== null) {
        $this->fail("public/hot points at {$unreachable}, which this process cannot reach, so every page would render blank and the first click would hang. Run `npm run build` and stop the dev server (or move public/hot aside), or run the dev server inside Sail.");
    }
})->in('Browser');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', fn () => $this->toBe(1));

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something(): void
{
    // ..
}

/**
 * The dev server URL in public/hot when this process cannot connect to it,
 * otherwise null. A host-run dev server writes a host address that the Sail
 * container cannot reach. Checked once per process.
 */
function viteDevServerUnreachable(): ?string
{
    static $result = false;

    if ($result !== false) {
        return $result;
    }

    $hotFile = public_path('hot');

    if (! is_file($hotFile)) {
        return $result = null;
    }

    $url = mb_trim((string) file_get_contents($hotFile));
    $host = mb_trim((string) parse_url($url, PHP_URL_HOST), '[]');
    $port = parse_url($url, PHP_URL_PORT) ?? (str_starts_with($url, 'https') ? 443 : 80);

    $socket = @fsockopen($host, $port, $errorCode, $errorMessage, 1);

    if ($socket === false) {
        return $result = $url;
    }

    fclose($socket);

    return $result = null;
}

/**
 * Marks the session as having just confirmed a password.
 *
 * The profile page and Fortify's PUT /user/profile-information both sit behind
 * password confirmation, so any test that exercises them has to satisfy it.
 * Tests that are about the confirmation itself drive the real endpoint instead
 * of calling this - see PasswordConfirmationTest.
 */
function confirmPassword(): void
{
    session(['auth.password_confirmed_at' => now()->unix()]);
}

/**
 * The one email sent during the test. For the spreadsheet export tests, where
 * mail goes to the array transport.
 */
function sentExportEmail(): Email
{
    $messages = Mail::mailer()->getSymfonyTransport()->messages();

    expect($messages)->toHaveCount(1);

    return $messages->first()->getOriginalMessage();
}

/**
 * Every row of the emailed spreadsheet, header first.
 *
 * @return list<list<mixed>>
 */
function exportedRows(): array
{
    $attachments = sentExportEmail()->getAttachments();

    expect($attachments)->toHaveCount(1);

    $path = tempnam(sys_get_temp_dir(), 'export');
    file_put_contents($path, $attachments[0]->getBody());

    try {
        return SimpleExcelReader::create($path, 'xlsx')
            ->noHeaderRow()
            ->getRows()
            ->map(fn (array $row): array => array_values($row))
            ->all();
    } finally {
        unlink($path);
    }
}
