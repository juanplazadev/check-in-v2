<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Appointment;
use App\Models\Dock;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\Date;

/*
 * The drag itself only exists in the browser; the rules behind it are covered
 * by the Feature tests.
 */

/**
 * A drag in small mouse moves, as a hand makes it. Playwright's own dragTo jumps
 * in one move, ending before dnd-kit has measured what the box is over. Resolves
 * once the box shows up in the target column.
 */
function dragBoxByMouse(string $reference, string $dockUuid, int $pixelsDown): string
{
    return sprintf(<<<'JS'
        (async () => {
            const box = document.querySelector('[data-appointment="%1$s"]');
            const column = document.querySelector('[data-dock-column="%2$s"]');
            const from = box.getBoundingClientRect();
            const startX = from.left + 20;
            const startY = from.top + 10;
            const endX = column.getBoundingClientRect().left + 20;
            const endY = startY + %3$d;
            const frame = () => new Promise((resolve) => requestAnimationFrame(resolve));
            const fire = (type, target, x, y) => target.dispatchEvent(new MouseEvent(type, { bubbles: true, clientX: x, clientY: y, button: 0 }));

            fire('mousedown', box, startX, startY);
            for (let step = 1; step <= 12; step++) {
                fire('mousemove', document, startX + (endX - startX) * step / 12, startY + (endY - startY) * step / 12);
                await frame();
            }
            await frame();
            fire('mouseup', document, endX, endY);

            for (let attempt = 0; attempt < 100; attempt++) {
                if (document.querySelector('[data-dock-column="%2$s"] [data-appointment="%1$s"]')) {
                    return true;
                }
                await new Promise((resolve) => setTimeout(resolve, 50));
            }

            return false;
        })()
        JS, $reference, $dockUuid, $pixelsDown);
}

it('drags a booking to a new dock and time with the mouse', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    $dock = Dock::factory()->for($location)->create(['name' => 'Door 1']);
    $date = Date::now($location->timezone)->addDay();
    $start = $date->copy()->setTime(1, 0);
    $appointment = Appointment::factory()->for($location)->scheduledFor($start)->create(['reference_number' => 'APT44444']);

    $this->actingAs(User::factory()->withRole(Role::Shipping)->worksAt($location)->create());

    $page = visit(route('dashboard.dockView', ['location' => $location->id, 'date' => $date->toDateString()]))
        ->assertSee('APT44444')
        ->assertSee('Door 1');

    // 96 pixels is one hour on the grid.
    expect($page->script(dragBoxByMouse('APT44444', $dock->uuid, 96)))->toBeTrue();

    $page->refresh()
        ->assertPresent(sprintf('[data-dock-column="%s"] [data-appointment="APT44444"]', $dock->uuid))
        ->assertNoJavaScriptErrors();

    $appointment->refresh();

    expect($appointment->dock_id)->toBe($dock->id)
        ->and($appointment->scheduled_for->equalTo($start->copy()->addHour()))->toBeTrue();
});

it('moves a booking with the keyboard', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    $dock = Dock::factory()->for($location)->create(['name' => 'Door 1']);
    $date = Date::now($location->timezone)->addDay();
    $start = $date->copy()->setTime(1, 0);
    $appointment = Appointment::factory()->for($location)->scheduledFor($start)->create(['reference_number' => 'APT66666']);

    $this->actingAs(User::factory()->withRole(Role::Shipping)->worksAt($location)->create());

    $page = visit(route('dashboard.dockView', ['location' => $location->id, 'date' => $date->toDateString()]))
        ->assertSee('APT66666');

    $page->keys('[data-appointment="APT66666"]', ['Space', ...array_fill(0, 30, 'ArrowRight'), 'Space']);

    $page->assertPresent(sprintf('[data-dock-column="%s"] [data-appointment="APT66666"]', $dock->uuid))
        ->assertNoJavaScriptErrors();

    expect($appointment->refresh()->dock_id)->toBe($dock->id)
        ->and($appointment->scheduled_for->equalTo($start))->toBeTrue();
});

it('keeps each door header over its column when bookings share lanes', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    Dock::factory()->for($location)->create(['name' => 'Door 1']);
    Dock::factory()->for($location)->create(['name' => 'A door with a very long name that does not fit in its column at all']);
    $date = Date::now($location->timezone)->addDay();

    foreach (['APT77771', 'APT77772', 'APT77773', 'APT77774'] as $reference) {
        Appointment::factory()->for($location)->scheduledFor($date->copy()->setTime(1, 0))->create(['reference_number' => $reference]);
    }

    $this->actingAs(User::factory()->withRole(Role::Shipping)->worksAt($location)->create());

    $page = visit(route('dashboard.dockView', ['location' => $location->id, 'date' => $date->toDateString()]))
        ->assertSee('APT77771');

    $misaligned = $page->script(<<<'JS'
        [...document.querySelectorAll('[data-dock-column]')]
            .map((column) => {
                const header = document.querySelector(`[data-dock-header="${column.dataset.dockColumn}"]`);
                const headerBox = header.getBoundingClientRect();
                const columnBox = column.getBoundingClientRect();

                return {
                    id: column.dataset.dockColumn,
                    left: Math.abs(headerBox.left - columnBox.left),
                    width: Math.abs(headerBox.width - columnBox.width),
                };
            })
            .filter(({ left, width }) => left > 1 || width > 1)
        JS);

    expect($misaligned)->toBe([]);

    $page->assertNoJavaScriptErrors();
});

it('opens the booking on a click', function (): void {
    $location = Location::factory()->appointmentsOnly()->create();
    $date = Date::now($location->timezone)->addDay();
    Appointment::factory()->for($location)->scheduledFor($date->copy()->setTime(1, 0))->create(['reference_number' => 'APT55555']);

    $this->actingAs(User::factory()->withRole(Role::Shipping)->worksAt($location)->create());

    visit(route('dashboard.dockView', ['location' => $location->id, 'date' => $date->toDateString()]))
        ->click('[data-appointment="APT55555"]')
        ->assertPathContains('/appointments/')
        ->assertNoJavaScriptErrors();
});
