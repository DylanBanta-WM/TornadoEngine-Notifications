<?php

namespace TornadoEngine\Notifications;

use App\Layout\Navbar;
use App\Models\User;
use App\Modules\Module;
use App\Modules\Setup;
use App\Updates\Rebuild;
use App\Updates\UpdateJob;
use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Notifications::class);
    }

    public function boot(Notifications $notifications): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'notifications');
        $this->loadRoutesFrom(__DIR__.'/../routes/notifications.php');
        app(Navbar::class)->add('notifications::bell');

        // An install or update that needs the containers rebuilt (Core's Rebuild).
        $notifications->source('rebuild', fn (User $user) => ! $user->can('administration') || ! class_exists(Rebuild::class) || ! Rebuild::needed() ? [] : [[
            'title' => __('The server needs a rebuild'),
            'text' => __('An install or update finishes once the containers are rebuilt.'),
            'url' => route('administration.tab', ['tab' => 'updates']),
        ]]);

        // An update that waits because the district's changes (to Core or a customized module) clash with it.
        $notifications->source('clashes', fn (User $user) => ! $user->can('administration') ? [] : UpdateJob::whereIn('kind', ['core', 'module'])
            ->where('status', 'failed')->where('log', 'like', '%clash with%')->latest('id')->take(20)->get()
            ->unique(fn ($job) => $job->kind.$job->package)
            ->filter(fn ($job) => ! UpdateJob::where('kind', $job->kind)->where('package', $job->package)->where('id', '>', $job->id)->where('status', 'done')->exists())
            ->map(fn ($job) => [
                'title' => __('An update is waiting'),
                'text' => __('Your changes clash with :name :version. Settings → Updates has what to do.', ['name' => $job->package ?? __('Core'), 'version' => $job->version]),
                'url' => route('administration.tab', ['tab' => 'updates']),
            ])->values()->all());

        // Modules that need their settings filled in before they work.
        $notifications->source('setup', fn (User $user) => ! $user->can('administration') ? [] : array_map(fn (Module $module) => [
            'title' => __('Finish setting up :name', ['name' => $module->name]),
            'text' => __('It needs its settings before it works.'),
            'url' => route('notifications.setup', ['package' => $module->package]),
        ], app(Setup::class)->pending()));
    }
}
