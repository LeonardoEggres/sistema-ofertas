<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nome', 100);
            $table->string('email', 255)->unique();
            $table->string('senha_hash', 255);
            $table->string('telefone', 20)->nullable();
            $table->string('avatar_url', 500)->nullable();
            $table->boolean('ativo')->default(true);
            $table->boolean('email_verificado')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};
