<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tokens behind the invitation links sent to staff an admin creates (the
     * invites password broker). Kept apart from password_reset_tokens so a
     * reset link can never be redeemed under the invitation's longer expiry.
     */
    public function up(): void
    {
        Schema::create('user_invitation_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_invitation_tokens');
    }
};
