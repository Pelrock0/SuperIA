<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('list_share_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shopping_list_id')->constrained()->cascadeOnDelete();
            $table->uuid('token_id')->unique();
            $table->enum('mode', ['edit', 'read_only']);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['shopping_list_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_share_tokens');
    }
};
