<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produtos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nome', 500);
            $table->text('descricao')->nullable();
            $table->uuid('categoria_id')->nullable();
            $table->string('marca', 100)->nullable();
            $table->string('modelo', 100)->nullable();
            $table->string('ean', 13)->nullable();
            $table->string('imagem_url', 500)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->foreign('categoria_id')->references('id')->on('categorias')->onDelete('set null');
            $table->index('marca');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produtos');
    }
};
