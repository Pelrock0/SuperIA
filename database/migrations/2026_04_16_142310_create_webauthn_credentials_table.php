<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webauthn_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('credential_id', 512);
            $table->text('public_key');
            $table->unsignedBigInteger('sign_count')->default(0);
            $table->json('transports')->nullable();
            $table->char('aaguid', 36)->nullable();
            $table->string('attestation_type', 20)->default('none');
            $table->string('name', 50);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['credential_id'], 'webauthn_credentials_credential_id_unique');
            $table->index('user_id', 'webauthn_credentials_user_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webauthn_credentials');
    }
};
