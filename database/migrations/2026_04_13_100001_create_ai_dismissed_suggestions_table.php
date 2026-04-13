<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_dismissed_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('producto_nombre', 80);
            $table->timestamp('dismissed_until');
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'dismissed_until'], 'dismissed_user_until_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_dismissed_suggestions');
    }
};
