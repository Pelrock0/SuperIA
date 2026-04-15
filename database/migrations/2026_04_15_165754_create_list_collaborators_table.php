<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('list_collaborators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shopping_list_id')->constrained()->cascadeOnDelete();
            $table->enum('mode', ['edit', 'read_only']);
            $table->foreignId('share_token_id')->nullable()->constrained('list_share_tokens')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'shopping_list_id']);
            $table->index('shopping_list_id');
            $table->index('share_token_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_collaborators');
    }
};
