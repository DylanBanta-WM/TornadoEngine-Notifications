<?php

namespace TornadoEngine\Notifications\Tests;

use App\Models\Setting;
use App\Models\User;
use App\Modules\ModuleRegistry;
use App\Modules\Setup;
use App\Patches\Patches;
use App\Updates\Rebuild;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use TornadoEngine\Notifications\Notifications;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private bool $gamReady = false;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => fn () => Http::response(['configured' => $this->gamReady])]);
        // Stands in for a module's settings form, which saves and goes back.
        Route::middleware('web')->post('/test/save', fn () => back());
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_the_bell_lists_modules_still_to_be_set_up(): void
    {
        $this->actingAs($this->admin())->get('/dashboard')
            ->assertSee('Finish setting up GitHub')
            ->assertSee(route('notifications.setup', ['package' => 'tornado-engine/github']), false);

        Setting::put('github.client_id', 'Iv1.abc');
        Setting::putSecret('github.token', 'gho_test');
        Setting::put('github.login', 'someone');
        Setting::putSecret('github.packagist_token', 'pk-test');
        cache()->flush();
        $this->actingAs($this->admin())->get('/dashboard')->assertDontSee('Finish setting up GitHub');

        $this->actingAs(User::factory()->create())->get('/dashboard')->assertDontSee('Finish setting up', false);
    }

    public function test_modules_can_add_notifications(): void
    {
        app(Notifications::class)->source('test', fn (User $user) => [['title' => 'Hello '.$user->id, 'url' => '/somewhere']]);
        $admin = $this->admin();
        $this->actingAs($admin)->get('/dashboard')->assertSee('Hello '.$admin->id);
    }

    public function test_setting_up_a_module_opens_the_modules_it_needs_first(): void
    {
        $admin = $this->admin();
        $runner = app(ModuleRegistry::class)->all()['tornado-engine/gam-runner'];

        // GAM Runner needs GAM, which isn't set up; GAM Runner has nothing of its own to set up.
        $this->actingAs($admin)->get(route('notifications.setup', ['package' => 'tornado-engine/gam-runner']))->assertRedirect(url('/settings/gam'));
        $this->assertSame(['tornado-engine/gam'], session('setup.chain'));
        $this->actingAs($admin)->get('/settings/gam')->assertSee('Set up GAM, then save to finish.');

        // Saved, but GAM still isn't set up: stay.
        $this->actingAs($admin)->from('/settings/gam')->post('/test/save')->assertRedirect('/settings/gam');

        $this->gamReady = true;
        $this->actingAs($admin)->from('/settings/gam')->post('/test/save')->assertRedirect(url('/settings/gam'))->assertSessionHas('setup_status', 'GAM is set up.');
        $this->assertNull(session('setup.chain'));
        $this->assertSame([], app(Setup::class)->chain($runner));
    }

    public function test_the_bell_says_when_the_server_needs_a_rebuild(): void
    {
        $admin = $this->admin();
        $this->assertNotContains('The server needs a rebuild', array_column(app(Notifications::class)->for($admin), 'title'));

        Rebuild::markNeeded();

        $this->assertContains('The server needs a rebuild', array_column(app(Notifications::class)->for($admin), 'title'));
        $this->assertNotContains('The server needs a rebuild', array_column(app(Notifications::class)->for(User::factory()->create()), 'title'));
    }

    public function test_the_bell_says_when_a_patch_is_off(): void
    {
        $base = sys_get_temp_dir().'/bell-patch-'.uniqid();
        File::ensureDirectoryExists("{$base}/local/patches");
        file_put_contents("{$base}/local/patches/core.patch", "diff --git a/app/X.php b/app/X.php\n");
        file_put_contents("{$base}/local/patches/state.json", json_encode(['core' => ['state' => 'off']]));
        $this->app->instance(Patches::class, new Patches($base));

        try {
            $this->assertContains('A patch is off', array_column(app(Notifications::class)->for($this->admin()), 'title'));
            $this->assertNotContains('A patch is off', array_column(app(Notifications::class)->for(User::factory()->create()), 'title'));
        } finally {
            File::deleteDirectory($base);
        }
    }
}
