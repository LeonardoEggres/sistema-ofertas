<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertas_preco', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('usuario_id');
            $table->uuid('produto_id');
            $table->decimal('preco_alvo', 10, 2);
            $table->enum('tipo_alerta', ['menor_igual', 'percentual_desconto'])->default('menor_igual');
            $table->decimal('percentual_desconto', 5, 2)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('usuario_id')->references('id')->on('usuarios')->onDelete('cascade');
            $table->foreign('produto_id')->references('id')->on('produtos')->onDelete('cascade');
            $table->index(['usuario_id', 'ativo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas_preco');
    }
};
