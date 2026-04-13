<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopping_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('emoji', 10)->nullable();
            $table->enum('category', ['supermercado', 'mercado', 'online', 'farmacia', 'otro'])->nullable();
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->boolean('is_shared')->default(false);
            $table->unsignedInteger('items_total')->default(0);
            $table->unsignedInteger('items_completed')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_lists');
    }
};
