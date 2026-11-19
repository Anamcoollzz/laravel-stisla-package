<?php

namespace LaravelStisla\Stisla\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stisla:install
        {--module=core : App module to enable (core, online-shop, news, spp-payment, lp-compro-workers, all)}
        {--force : Overwrite existing files}
        {--seed : Run storage:link and migrate --seed after publishing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install and publish Laravel Stisla starter files into the application';

    public function __construct(private readonly Filesystem $files)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $module = (string) $this->option('module');
        $force = (bool) $this->option('force');

        $this->info('Publishing Laravel Stisla stubs...');

        $this->call('vendor:publish', [
            '--tag' => 'stisla-all',
            '--force' => $force,
        ]);

        $this->registerRoutesInWebPhp($module);
        $this->ensureAppConfigKeys();
        $this->ensureEnvKeys($module);

        if ($this->option('seed')) {
            $this->call('storage:link');
            $this->call('migrate', ['--seed' => true]);
        }

        $this->newLine();
        $this->info('Laravel Stisla installed successfully.');

        if (! $this->option('seed')) {
            $this->line('Next steps:');
            $this->line('  1. php artisan storage:link');
            $this->line('  2. php artisan migrate --seed');
        }

        return self::SUCCESS;
    }

    /**
     * Append modular route requires to routes/web.php if not already registered.
     */
    protected function registerRoutesInWebPhp(string $module): void
    {
        $webPath = base_path('routes/web.php');
        if (! $this->files->exists($webPath)) {
            return;
        }

        $content = $this->files->get($webPath);
        $blocks = [
            "require __DIR__.'/stisla-web.php';",
            "require __DIR__.'/stisla-pages-web.php';",
            "require __DIR__.'/spark-admin-web.php';",
        ];

        if (in_array($module, ['online-shop', 'all'], true)) {
            $blocks[] = "if (config('app.menu_app') === 'online-shop') {\n    require __DIR__.'/ecommerce-pages-web.php';\n}";
        }

        if (in_array($module, ['spp-payment', 'all'], true)) {
            $blocks[] = "if (config('app.menu_app') === 'spp-payment') {\n    require __DIR__.'/spp-payment-web.php';\n}";
        }

        if (in_array($module, ['news', 'all'], true)) {
            $blocks[] = "if (config('app.menu_app') === 'news') {\n    require __DIR__.'/news-web.php';\n}";
        }

        if (in_array($module, ['lp-compro-workers', 'all'], true)) {
            $blocks[] = "if (config('app.menu_app') === 'lp-compro-workers') {\n    require __DIR__.'/lp-compro-web.php';\n}";
        }

        $toAppend = [];
        foreach ($blocks as $block) {
            $needle = preg_replace('/\s+/', ' ', $block);
            $normalizedContent = preg_replace('/\s+/', ' ', $content);
            if ($needle !== null && $normalizedContent !== null && ! str_contains($normalizedContent, $needle)) {
                $toAppend[] = $block;
            }
        }

        if ($toAppend !== []) {
            $this->files->append($webPath, "\n".implode("\n\n", $toAppend)."\n");
        }
    }

    /**
     * Ensure is_demo and menu_app keys exist in config/app.php.
     */
    protected function ensureAppConfigKeys(): void
    {
        $appConfigPath = config_path('app.php');
        if (! $this->files->exists($appConfigPath)) {
            return;
        }

        $content = $this->files->get($appConfigPath);
        if (str_contains($content, "'is_demo'") && str_contains($content, "'menu_app'")) {
            return;
        }

        $insertion = "\n    'is_demo' => (bool) env('APP_IS_DEMO', false),\n\n    'menu_app' => env('ADMIN_MENU_APP', 'online-shop'),\n";
        $updated = preg_replace(
            "/('debug'\s*=>\s*[^,\n]+,)/",
            "$1\n".$insertion,
            $content,
            1
        );

        if (is_string($updated) && $updated !== $content) {
            $this->files->put($appConfigPath, $updated);
        }
    }

    /**
     * Ensure required environment keys exist in .env and .env.example.
     */
    protected function ensureEnvKeys(string $module): void
    {
        $menuApp = $module === 'all' || $module === 'core' ? 'online-shop' : $module;
        $defaults = [
            'APP_IS_DEMO' => 'false',
            'ADMIN_MENU_APP' => $menuApp,
        ];

        foreach ([base_path('.env'), base_path('.env.example')] as $envPath) {
            if (! $this->files->exists($envPath)) {
                continue;
            }

            $content = $this->files->get($envPath);
            $added = [];
            foreach ($defaults as $key => $value) {
                if (! preg_match('/^'.preg_quote($key, '/').'=/m', $content)) {
                    $added[] = "{$key}={$value}";
                }
            }

            if ($added !== []) {
                $this->files->append($envPath, "\n".implode("\n", $added)."\n");
            }
        }
    }
}
