<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('list_activity_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shopping_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('list_share_token_id')->nullable()->constrained('list_share_tokens')->cascadeOnDelete();
            $table->enum('actor_type', ['owner', 'anonymous']);
            $table->enum('action', [
                'item_added',
                'item_checked',
                'item_unchecked',
                'item_edited',
                'item_deleted',
                'list_cleared',
            ]);
            $table->string('item_name', 80);
            $table->timestamp('created_at')->nullable();

            $table->index(['shopping_list_id', 'id']);
            $table->index('list_share_token_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_activity_log');
    }
};
