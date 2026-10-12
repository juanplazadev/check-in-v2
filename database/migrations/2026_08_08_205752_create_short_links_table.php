<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs the short URLs sent by text. One table for every record type, which
     * is the point: a single unique index on `code` is what makes a collision
     * between a check-in link and an appointment link impossible, where the
     * per-table reference indexes could never see each other.
     */
    public function up(): void
    {
        Schema::create('short_links', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 10)->unique();
            $table->morphs('linkable');
            $table->timestamp('last_visited_at')->nullable();
            $table->unsignedInteger('visits')->default(0);
            $table->timestamps();

            // One link per record, so repeat sends reuse the same short URL
            // rather than minting a new row on every text.
            $table->unique(['linkable_type', 'linkable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('short_links');
    }
};
