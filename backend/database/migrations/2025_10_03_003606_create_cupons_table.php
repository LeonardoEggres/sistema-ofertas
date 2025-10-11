<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cupons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('marketplace_id');
            $table->string('codigo', 50);
            $table->text('descricao')->nullable();
            $table->enum('tipo_desconto', ['percentual', 'valor_fixo', 'frete_gratis']);
            $table->decimal('valor_desconto', 10, 2)->nullable();
            $table->decimal('percentual_desconto', 5, 2)->nullable();
            $table->decimal('valor_minimo_compra', 10, 2)->nullable();
            $table->timestamp('data_inicio')->nullable();
            $table->timestamp('data_fim')->nullable();
            $table->integer('usos_maximos')->nullable();
            $table->integer('usos_realizados')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('marketplace_id')->references('id')->on('marketplaces')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cupons');
    }
};
