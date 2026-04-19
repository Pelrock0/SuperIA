<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('price_cache', function (Blueprint $table) {
            $table->id();
            $table->string('input_name', 255);
            $table->decimal('precio_min', 8, 2)->nullable();
            $table->decimal('precio_max', 8, 2)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique('input_name');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_cache');
    }
};
