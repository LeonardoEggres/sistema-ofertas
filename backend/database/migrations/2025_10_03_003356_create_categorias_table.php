<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nome', 100);
            $table->string('slug', 100)->unique();
            $table->uuid('categoria_pai_id')->nullable();
            $table->string('icone', 50)->nullable();
            $table->integer('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->index('categoria_pai_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias');
    }
};
