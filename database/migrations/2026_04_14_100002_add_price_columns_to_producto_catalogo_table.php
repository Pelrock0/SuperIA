<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producto_catalogo', function (Blueprint $table) {
            $table->decimal('precio_min', 8, 2)->nullable()->after('cantidad_tipica');
            $table->decimal('precio_max', 8, 2)->nullable()->after('precio_min');
        });
    }

    public function down(): void
    {
        Schema::table('producto_catalogo', function (Blueprint $table) {
            $table->dropColumn(['precio_min', 'precio_max']);
        });
    }
};
