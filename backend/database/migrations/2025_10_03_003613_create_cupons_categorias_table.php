<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cupons_categorias', function (Blueprint $table) {
            $table->uuid('cupom_id');
            $table->uuid('categoria_id');

            $table->foreign('cupom_id')->references('id')->on('cupons')->onDelete('cascade');
            $table->foreign('categoria_id')->references('id')->on('categorias')->onDelete('cascade');
            $table->primary(['cupom_id', 'categoria_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cupons_categorias');
    }
};
