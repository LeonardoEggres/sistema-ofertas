<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class MakeRepositoryCommand extends Command
{
    protected $signature = 'make:repository {name}';
    protected $description = 'Cria um novo Repository em app/Repositories';

    public function handle()
    {
        $name = $this->argument('name');
        $filesystem = new Filesystem();

        $directory = app_path('Http/Repositories');
        $path = $directory . '/' . $name . '.php';

        if (! $filesystem->isDirectory($directory)) {
            $filesystem->makeDirectory($directory, 0755, true);
        }

        if ($filesystem->exists($path)) {
            $this->error("O repository {$name} já existe!");
            return;
        }

        $content = <<<PHP
        <?php

        namespace App\Repositories;

        class {$name}
        {
            public function __construct()
            {
                // inicialização do repository
            }
        }

        PHP;

        $filesystem->put($path, $content);

        $this->info("Repository {$name} criado em: {$path}");
    }
}
