<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historico_precos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('produto_marketplace_id');
            $table->decimal('preco', 10, 2);
            $table->decimal('preco_original', 10, 2)->nullable();
            $table->boolean('em_estoque')->default(true);
            $table->timestamp('data_registro')->useCurrent();

            $table->foreign('produto_marketplace_id')->references('id')->on('produtos_marketplace')->onDelete('cascade');
            $table->index(['produto_marketplace_id', 'data_registro']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historico_precos');
    }
};
