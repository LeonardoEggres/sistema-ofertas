<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historico_cliques', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('usuario_id');
            $table->uuid('produto_marketplace_id');
            $table->decimal('preco_momento_clique', 10, 2)->nullable();
            $table->boolean('converteu_compra')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('usuario_id')->references('id')->on('usuarios')->onDelete('cascade');
            $table->foreign('produto_marketplace_id')->references('id')->on('produtos_marketplace')->onDelete('cascade');
            $table->index('usuario_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historico_cliques');
    }
};
