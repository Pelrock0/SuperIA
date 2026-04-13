<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_catalogo', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);
            $table->enum('categoria', [
                'frutas_verduras', 'carnes_pescados', 'lacteos_huevos', 'panaderia',
                'bebidas', 'congelados', 'limpieza', 'higiene_personal', 'conservas', 'otros',
            ])->nullable();
            $table->enum('unidad_tipica', ['kg', 'g', 'L', 'ml', 'ud', 'pack'])->nullable();
            $table->decimal('cantidad_tipica', 8, 2)->nullable();
            $table->timestamps();

            $table->index('categoria', 'catalogo_categoria_idx');
        });

        DB::statement('ALTER TABLE producto_catalogo ADD FULLTEXT catalogo_nombre_fulltext (nombre)');
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_catalogo');
    }
};
