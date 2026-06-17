<?php

namespace App\Providers;

use App\Models\StudyCalendarEvent;
use App\Services\AI\AIClientFactory;
use App\Services\AI\AIService;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AIClientFactory::class, function ($app) {
            return new AIClientFactory(config('ai'));
        });

        $this->app->singleton(AIService::class, function ($app) {
            return new AIService($app->make(AIClientFactory::class));
        });
    }

public function boot(): void
{
    // ของเดิมในไฟล์คุณ
    $this->applyEnvOverridesWhenConfigCached();

    // ✅ กรอง calendar_event ตาม user_id
    Route::bind('calendar_event', function ($value) {
        $userId = optional(request()->user())->id;
        if (! $userId) abort(401);

        return \App\Models\StudyCalendarEvent::where('id', $value)
            ->where('user_id', $userId)
            ->firstOrFail();
    });
}


    private function applyEnvOverridesWhenConfigCached(): void
    {
        if (! ($this->app instanceof CachesConfiguration) || ! $this->app->configurationIsCached()) {
            return;
        }

        $env = $this->readDotEnv(base_path('.env'));
        if ($env === []) {
            return;
        }

        $connection = $env['DB_CONNECTION'] ?? null;
        if ($connection !== null && $connection !== '') {
            config(['database.default' => $connection]);
        }

        $activeConnection = $connection ?: (string) config('database.default');
        if ($activeConnection !== '') {
            $this->overrideConnectionConfig($env, $activeConnection);
        }

        $cacheStore = $env['CACHE_STORE'] ?? $env['CACHE_DRIVER'] ?? null;
        if ($cacheStore !== null && $cacheStore !== '') {
            config(['cache.default' => $cacheStore]);
        }
    }

    private function overrideConnectionConfig(array $env, string $connection): void
    {
        $baseKey = "database.connections.{$connection}";
        $map = [
            'DB_HOST' => 'host',
            'DB_PORT' => 'port',
            'DB_DATABASE' => 'database',
            'DB_USERNAME' => 'username',
            'DB_PASSWORD' => 'password',
            'DB_CHARSET' => 'charset',
            'DB_COLLATION' => 'collation',
        ];

        foreach ($map as $envKey => $configKey) {
            if (! array_key_exists($envKey, $env)) {
                continue;
            }

            $value = $env[$envKey];
            if ($value === '') {
                continue;
            }

            config(["{$baseKey}.{$configKey}" => $value]);
        }
    }

    private function readDotEnv(string $path): array
    {
        if (! is_readable($path)) {
            return [];
        }

        $vars = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = substr($line, 7);
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            [$key, $value] = $parts;
            $key = trim($key);
            if ($key === '') {
                continue;
            }

            $value = trim($value);
            $value = $this->stripMatchingQuotes($value);
            $vars[$key] = $value;
        }

        return $vars;
    }

    private function stripMatchingQuotes(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        $first = $value[0];
        $last = $value[strlen($value) - 1];
        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
