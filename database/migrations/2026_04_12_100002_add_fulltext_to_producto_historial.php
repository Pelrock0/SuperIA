<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE producto_historial ADD FULLTEXT historial_nombre_fulltext (producto_nombre)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE producto_historial DROP INDEX historial_nombre_fulltext');
    }
};
