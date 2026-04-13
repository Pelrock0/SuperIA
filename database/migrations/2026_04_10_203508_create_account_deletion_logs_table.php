<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_deletion_logs', function (Blueprint $table) {
            $table->id();
            $table->string('hashed_user_id', 64);
            $table->enum('reason', ['user_request', 'admin_action']);
            $table->timestamp('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deletion_logs');
    }
};
