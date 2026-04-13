<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('operation', [
                'suggestion', 'generation', 'summary', 'complement', 'replenishment',
            ]);
            $table->enum('status', [
                'success', 'error', 'budget_capped', 'user_capped', 'circuit_open',
            ]);
            $table->date('date');
            $table->decimal('estimated_cost_usd', 8, 4)->default(0);
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'date', 'operation'], 'ai_usage_user_date_op_idx');
            $table->index('date', 'ai_usage_date_idx');
            $table->index(['operation', 'status'], 'ai_usage_op_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_log');
    }
};
