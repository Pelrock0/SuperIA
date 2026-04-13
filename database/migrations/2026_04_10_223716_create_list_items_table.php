<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shopping_list_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->decimal('quantity', 8, 2)->nullable();
            $table->enum('unit', ['kg', 'g', 'L', 'ml', 'ud', 'pack'])->nullable();
            $table->enum('category', [
                'frutas_verduras', 'carnes_pescados', 'lacteos_huevos', 'panaderia',
                'bebidas', 'congelados', 'limpieza', 'higiene_personal', 'conservas', 'otros',
            ])->nullable();
            $table->decimal('estimated_price', 8, 2)->nullable();
            $table->boolean('is_purchased')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['shopping_list_id', 'is_purchased']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_items');
    }
};
