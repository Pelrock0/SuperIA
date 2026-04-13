<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('producto_nombre', 80);
            $table->enum('categoria', [
                'frutas_verduras', 'carnes_pescados', 'lacteos_huevos', 'panaderia',
                'bebidas', 'congelados', 'limpieza', 'higiene_personal', 'conservas', 'otros',
            ])->nullable();
            $table->decimal('cantidad', 8, 2)->nullable();
            $table->enum('unidad', ['kg', 'g', 'L', 'ml', 'ud', 'pack'])->nullable();
            $table->decimal('precio_real', 8, 2)->nullable();
            $table->timestamp('fecha_compra');
            $table->foreignId('lista_id')->nullable()->constrained('shopping_lists')->nullOnDelete();

            $table->index(['user_id', 'producto_nombre']);
            $table->index(['user_id', 'fecha_compra']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_historial');
    }
};
