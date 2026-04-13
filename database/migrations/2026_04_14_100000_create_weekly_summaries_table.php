<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('week_start_date');
            $table->string('status', 20);
            $table->longText('payload_json')->nullable();
            $table->decimal('claude_cost_usd', 10, 4)->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'week_start_date'], 'weekly_summary_user_week_unique');
            $table->index('week_start_date', 'weekly_summary_week_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_summaries');
    }
};
