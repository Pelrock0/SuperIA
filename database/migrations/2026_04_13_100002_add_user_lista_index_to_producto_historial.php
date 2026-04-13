<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producto_historial', function (Blueprint $table) {
            $table->index(['user_id', 'lista_id'], 'historial_user_lista_idx');
        });
    }

    public function down(): void
    {
        Schema::table('producto_historial', function (Blueprint $table) {
            $table->dropIndex('historial_user_lista_idx');
        });
    }
};
