<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('weekly_summary_email_opted_in')->default(false)->after('privacy_accepted_at');
            $table->timestamp('weekly_summary_in_app_dismissed_at')->nullable()->after('weekly_summary_email_opted_in');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['weekly_summary_email_opted_in', 'weekly_summary_in_app_dismissed_at']);
        });
    }
};
