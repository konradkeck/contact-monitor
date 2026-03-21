<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // ── On localhost, append APP_PORT to app.url ──────────────────────────
        $port = env('APP_PORT');
        $url  = rtrim(config('app.url'), '/');
        if ($port && preg_match('#^https?://(localhost|127\.0\.0\.1)$#', $url)) {
            config(['app.url' => $url . ':' . $port]);
            url()->forceRootUrl($url . ':' . $port);
        }

        // ── Permission gates ──────────────────────────────────────────────────
        foreach (['browse_data', 'data_write', 'notes_write', 'analyse', 'configuration'] as $perm) {
            Gate::define($perm, fn ($user) => $user->hasPermission($perm));
        }

    }
}
