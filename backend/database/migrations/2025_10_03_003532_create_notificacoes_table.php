<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificacoes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('usuario_id');
            $table->uuid('produto_id')->nullable();
            $table->string('tipo', 50);
            $table->string('titulo', 200);
            $table->text('mensagem');
            $table->string('link', 500)->nullable();
            $table->boolean('lida')->default(false);
            $table->boolean('enviada_email')->default(false);
            $table->boolean('enviada_push')->default(false);
            $table->boolean('enviada_sms')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('usuario_id')->references('id')->on('usuarios')->onDelete('cascade');
            $table->foreign('produto_id')->references('id')->on('produtos')->onDelete('cascade');
            $table->index(['usuario_id', 'lida']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificacoes');
    }
};
