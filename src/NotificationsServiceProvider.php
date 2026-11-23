<?php

namespace TornadoEngine\Notifications;

use App\Layout\Navbar;
use App\Models\User;
use App\Modules\Module;
use App\Modules\Setup;
use App\Patches\Patches;
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

        // A district's patch (Core's App\Patches) that an update no longer fits: the update happened, the patch is off.
        $notifications->source('patches', fn (User $user) => ! $user->can('administration') || ! class_exists(Patches::class) ? [] : array_map(fn ($target) => [
            'title' => __('A patch is off'),
            'text' => __('The patch for :target doesn\'t fit its new version. Redo it, or remove it.', ['target' => $target === 'core' ? __('Core') : $target]),
            'url' => route('administration.tab', ['tab' => 'updates']),
        ], app(Patches::class)->off()));

        // Modules that need their settings filled in before they work.
        $notifications->source('setup', fn (User $user) => ! $user->can('administration') ? [] : array_map(fn (Module $module) => [
            'title' => __('Finish setting up :name', ['name' => $module->name]),
            'text' => __('It needs its settings before it works.'),
            'url' => route('notifications.setup', ['package' => $module->package]),
        ], app(Setup::class)->pending()));
    }
}
