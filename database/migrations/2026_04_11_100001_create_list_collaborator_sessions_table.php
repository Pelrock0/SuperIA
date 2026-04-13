<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('list_collaborator_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_share_token_id')->constrained('list_share_tokens')->cascadeOnDelete();
            $table->char('session_uuid', 36);
            $table->timestamp('last_heartbeat_at');
            $table->timestamp('created_at')->nullable();

            $table->unique(['list_share_token_id', 'session_uuid'], 'collab_sessions_token_uuid_unique');
            $table->index(['list_share_token_id', 'last_heartbeat_at'], 'collab_sessions_token_heartbeat_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_collaborator_sessions');
    }
};
