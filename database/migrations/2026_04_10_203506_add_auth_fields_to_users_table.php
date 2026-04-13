<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('jwt_version')->default(0)->after('remember_token');
            $table->timestamp('privacy_accepted_at')->nullable()->after('jwt_version');
            $table->timestamp('scheduled_hard_delete_at')->nullable()->after('privacy_accepted_at');
            $table->softDeletes()->after('scheduled_hard_delete_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['jwt_version', 'privacy_accepted_at', 'scheduled_hard_delete_at']);
        });
    }
};
