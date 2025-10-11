<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estatisticas_precos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('produto_id');
            $table->decimal('preco_minimo', 10, 2)->nullable();
            $table->decimal('preco_maximo', 10, 2)->nullable();
            $table->decimal('preco_medio', 10, 2)->nullable();
            $table->uuid('marketplace_menor_preco_id')->nullable();
            $table->enum('periodo', ['7dias', '30dias', '90dias', '1ano']);
            $table->timestamp('ultima_atualizacao')->useCurrent();
            
            $table->foreign('produto_id')->references('id')->on('produtos')->onDelete('cascade');
            $table->foreign('marketplace_menor_preco_id')->references('id')->on('marketplaces')->onDelete('set null');
            $table->unique(['produto_id', 'periodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estatisticas_precos');
    }
};
