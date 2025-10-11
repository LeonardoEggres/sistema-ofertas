<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preferencias_usuario', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('usuario_id');
            $table->boolean('notificacoes_email')->default(true);
            $table->boolean('notificacoes_push')->default(true);
            $table->boolean('notificacoes_sms')->default(false);
            $table->enum('frequencia_notificacao', ['instantaneo', 'diario', 'semanal'])->default('instantaneo');
            $table->decimal('percentual_desconto_minimo', 5, 2)->default(10.00);
            $table->decimal('preco_maximo_interesse', 10, 2)->nullable();
            $table->timestamps();

            $table->foreign('usuario_id')->references('id')->on('usuarios')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preferencias_usuario');
    }
};
