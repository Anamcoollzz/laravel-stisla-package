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
            $blocks[] = "if (config('stisla.menu_app') === 'online-shop') {\n    require __DIR__.'/ecommerce-pages-web.php';\n}";
        }

        if (in_array($module, ['spp-payment', 'all'], true)) {
            $blocks[] = "if (config('stisla.menu_app') === 'spp-payment') {\n    require __DIR__.'/spp-payment-web.php';\n}";
        }

        if (in_array($module, ['news', 'all'], true)) {
            $blocks[] = "if (config('stisla.menu_app') === 'news') {\n    require __DIR__.'/news-web.php';\n}";
        }

        if (in_array($module, ['lp-compro-workers', 'all'], true)) {
            $blocks[] = "if (config('stisla.menu_app') === 'lp-compro-workers') {\n    require __DIR__.'/lp-compro-web.php';\n}";
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
     * Ensure required environment keys exist in .env and .env.example.
     */
    protected function ensureEnvKeys(string $module): void
    {
        $menuApp = $module === 'all' || $module === 'core' ? 'online-shop' : $module;
        $defaults = [
            'STISLA_IS_DEMO' => 'false',
            'STISLA_ADMIN_TEMPLATE' => 'stisla',
            'STISLA_ADMIN_MENU_APP' => $menuApp,
            'GOOGLE_CLIENT_ID' => '',
            'GOOGLE_CLIENT_SECRET' => '',
            'GOOGLE_REDIRECT_URI' => '"${APP_URL}/app/auth/google/callback"',
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
