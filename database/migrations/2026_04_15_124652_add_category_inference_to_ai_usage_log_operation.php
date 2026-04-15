<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE ai_usage_log MODIFY COLUMN operation ENUM('suggestion','generation','summary','complement','replenishment','category_inference') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE ai_usage_log MODIFY COLUMN operation ENUM('suggestion','generation','summary','complement','replenishment') NOT NULL");
    }
};
