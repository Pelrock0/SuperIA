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
        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('email', 255)->unique();
            $table->enum('shopping_companion', ['solo', 'pareja', 'familia', 'compañeros'])->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->enum('status', ['pending', 'invited', 'registered'])->default('pending');
            $table->string('invitation_token', 64)->nullable()->index();
            $table->timestamp('invitation_sent_at')->nullable();
            $table->timestamp('invitation_expires_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
