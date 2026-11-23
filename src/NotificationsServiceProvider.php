<?php

namespace TornadoEngine\Notifications;

use App\Layout\Navbar;
use App\Models\User;
use App\Modules\Module;
use App\Modules\Setup;
use App\Updates\Rebuild;
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

        // Modules that need their settings filled in before they work.
        $notifications->source('setup', fn (User $user) => ! $user->can('administration') ? [] : array_map(fn (Module $module) => [
            'title' => __('Finish setting up :name', ['name' => $module->name]),
            'text' => __('It needs its settings before it works.'),
            'url' => route('notifications.setup', ['package' => $module->package]),
        ], app(Setup::class)->pending()));
    }
}
