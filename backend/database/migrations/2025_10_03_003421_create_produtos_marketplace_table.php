<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produtos_marketplace', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('produto_id');
            $table->uuid('marketplace_id');
            $table->string('url_produto', 1000);
            $table->string('sku_marketplace', 100)->nullable();
            $table->decimal('preco_atual', 10, 2);
            $table->decimal('preco_original', 10, 2)->nullable();
            $table->boolean('em_estoque')->default(true);
            $table->integer('quantidade_vendida')->default(0);
            $table->decimal('avaliacao_media', 3, 2)->nullable();
            $table->integer('total_avaliacoes')->default(0);
            $table->timestamp('ultima_atualizacao')->useCurrent();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('produto_id')->references('id')->on('produtos')->onDelete('cascade');
            $table->foreign('marketplace_id')->references('id')->on('marketplaces')->onDelete('cascade');
            $table->unique(['produto_id', 'marketplace_id']);
            $table->index('preco_atual');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produtos_marketplace');
    }
};
