<?php

declare(strict_types=1);

use App\Enums\ErpSyncMethod;
use App\Enums\Role;
use App\Models\CheckIn;
use App\Models\User;
use Carbon\CarbonImmutable;

it('records who completed the check-in and who synced it to the ERP, and how', function (): void {
    $shipping = User::factory()->withRole(Role::Shipping)->create();
    $sales = User::factory()->withRole(Role::Sales)->create();

    $checkIn = CheckIn::factory()
        ->completed($shipping)
        ->erpSynced(ErpSyncMethod::Manual, $sales)
        ->create()
        ->fresh();

    expect($checkIn->completed_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($checkIn->completedBy->is($shipping))->toBeTrue()
        ->and($checkIn->erp_synced_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($checkIn->erp_synced_via)->toBe(ErpSyncMethod::Manual)
        ->and($checkIn->erpSyncedBy->is($sales))->toBeTrue();
});

it('records an automatic ERP sync with no staff member behind it', function (): void {
    $checkIn = CheckIn::factory()->erpSynced()->create()->fresh();

    expect($checkIn->erp_synced_via)->toBe(ErpSyncMethod::Automatic)
        ->and($checkIn->erp_synced_by)->toBeNull();
});

it('keeps the check-in but clears the staff reference when that user is deleted', function (): void {
    $staff = User::factory()->withRole(Role::Shipping)->create();
    $checkIn = CheckIn::factory()
        ->completed($staff)
        ->erpSynced(ErpSyncMethod::Manual, $staff)
        ->create();

    $staff->delete();

    $checkIn->refresh();

    expect($checkIn->completed_by)->toBeNull()
        ->and($checkIn->erp_synced_by)->toBeNull()
        ->and($checkIn->completed_at)->not->toBeNull()
        ->and($checkIn->erp_synced_at)->not->toBeNull();
});
