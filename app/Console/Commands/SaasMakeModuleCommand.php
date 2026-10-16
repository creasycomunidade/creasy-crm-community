<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SaasMakeModuleCommand extends Command
{
    protected $signature   = 'saas:make-module {name : PascalCase module name, e.g. ProjectManagement}';
    protected $description = 'Scaffold a new product module under app/Modules/{Name}/.';

    public function handle(): int
    {
        $name    = Str::studly($this->argument('name'));
        $slug    = Str::snake($name);
        $baseDir = app_path("Modules/{$name}");

        if (is_dir($baseDir)) {
            $this->error("Module {$name} already exists at {$baseDir}.");
            return self::FAILURE;
        }

        $this->info("Scaffolding module {$name}…");

        $dirs = [
            $baseDir,
            "{$baseDir}/Http/Controllers",
            "{$baseDir}/Models",
            "{$baseDir}/Services",
            "{$baseDir}/Policies",
            "{$baseDir}/database/migrations",
            "{$baseDir}/database/seeders",
            "{$baseDir}/routes",
            "{$baseDir}/resources/js/Pages",
        ];

        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
                $this->line("  <fg=green>created</> {$dir}");
            }
        }

        // Stub: ModuleServiceProvider
        $provider = <<<PHP
<?php

namespace App\\Modules\\{$name};

use Illuminate\\Support\\ServiceProvider;

class {$name}ServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        \$this->loadRoutesFrom(__DIR__.'/routes/web.php');
        \$this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
PHP;
        file_put_contents("{$baseDir}/{$name}ServiceProvider.php", $provider);
        $this->line("  <fg=green>created</> {$baseDir}/{$name}ServiceProvider.php");

        // Stub: routes/web.php
        $routes = <<<PHP
<?php

use Illuminate\\Support\\Facades\\Route;

// {$name} module routes
Route::middleware(['auth', 'verified'])->prefix('{$slug}')->name('{$slug}.')->group(function () {
    // Route::get('/', [{$name}Controller::class, 'index'])->name('index');
});
PHP;
        file_put_contents("{$baseDir}/routes/web.php", $routes);
        $this->line("  <fg=green>created</> {$baseDir}/routes/web.php");

        // Stub: placeholder README
        $readme = <<<MD
# {$name} Module

Place your {$name} business logic here.

## Structure

```
app/Modules/{$name}/
├── {$name}ServiceProvider.php   ← register routes & migrations
├── Http/Controllers/            ← Inertia / API controllers
├── Models/                      ← Eloquent models
├── Services/                    ← Business logic
├── Policies/                    ← Gate policies
├── database/
│   ├── migrations/
│   └── seeders/
├── routes/
│   └── web.php
└── resources/js/Pages/          ← React pages (copy to resources/js/Pages/{$name})
```

## Registration

Add `App\\Modules\\{$name}\\{$name}ServiceProvider::class` to the `providers` array in `bootstrap/providers.php`.
MD;
        file_put_contents("{$baseDir}/README.md", $readme);
        $this->line("  <fg=green>created</> {$baseDir}/README.md");

        $this->info('');
        $this->info("Module <fg=green>{$name}</> scaffolded successfully.");
        $this->info("Register it in bootstrap/providers.php:");
        $this->info("  App\\Modules\\{$name}\\{$name}ServiceProvider::class");

        return self::SUCCESS;
    }
}
