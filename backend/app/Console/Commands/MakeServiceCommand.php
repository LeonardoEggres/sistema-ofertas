<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class MakeServiceCommand extends Command
{
    /**
     * Nome e assinatura do comando Artisan.
     *
     * Exemplo de uso: php artisan make:service NomeService
     */
    protected $signature = 'make:service {name}';

    /**
     * Descrição do comando.
     */
    protected $description = 'Cria um novo Service em app/Http/Services';

    /**
     * Executa o comando.
     */
    public function handle()
    {
        $name = $this->argument('name');
        $filesystem = new Filesystem();

        $directory = app_path('Http/Services');
        $path = $directory . '/' . $name . '.php';

        // Cria diretório se não existir
        if (! $filesystem->isDirectory($directory)) {
            $filesystem->makeDirectory($directory, 0755, true);
        }

        // Evita sobrescrever arquivo existente
        if ($filesystem->exists($path)) {
            $this->error("O service {$name} já existe!");
            return;
        }

        // Conteúdo base do service
        $content = <<<PHP
        <?php

        namespace App\Http\Services;

        class {$name}
        {
            public function __construct()
            {
                // inicialização do service
            }
        }

        PHP;

        $filesystem->put($path, $content);

        $this->info("Service {$name} criado em: {$path}");
    }
}
