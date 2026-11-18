<?php

use App\Modules\ModuleRegistry;
use App\Modules\Setup;
use Illuminate\Support\Facades\Route;

// Opens a module's settings, after those of the modules it needs that aren't set up yet.
Route::middleware(['web', 'auth', 'can:administration'])->get('/notifications/setup/{package}', function (string $package, ModuleRegistry $modules, Setup $setup) {
    $module = $modules->all()[$package] ?? abort(404);

    return redirect()->to($setup->start($module) ?? ($module->settings ? url($module->settings) : route('dashboard')));
})->where('package', '[a-z0-9_.-]+/[a-z0-9_.-]+')->name('notifications.setup');
