<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->morphs('purchasable');
            $table->string('number');
            $table->timestampsTz();

            $table->unique(['purchasable_type', 'purchasable_id', 'number']);
            $table->index('number');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Trigram indexes serve the dashboard's "contains" search (ilike '%term%'). This is the
        // first migration that needs them, so it enables the extension for the later ones too.
        DB::statement('create extension if not exists pg_trgm');
        DB::statement('create index purchase_orders_number_trgm_index on purchase_orders using gin (number gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
