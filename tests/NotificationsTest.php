<?php

namespace TornadoEngine\Notifications\Tests;

use App\Models\Setting;
use App\Models\User;
use App\Modules\ModuleRegistry;
use App\Modules\Setup;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_setting_up_a_module_opens_the_modules_it_needs_first_then_the_dashboard(): void
    {
        $admin = $this->admin();
        $runner = app(ModuleRegistry::class)->all()['tornado-engine/gam-runner'];

        // GAM Runner needs GAM, which isn't set up; GAM Runner has nothing of its own to set up.
        $this->actingAs($admin)->get(route('notifications.setup', ['package' => 'tornado-engine/gam-runner']))->assertRedirect(url('/administration/gam'));
        $this->assertSame(['tornado-engine/gam'], session('setup.chain'));
        $this->actingAs($admin)->get('/administration/gam')->assertSee('Set up GAM, then save to finish.');

        // Saved, but GAM still isn't set up: stay.
        $this->actingAs($admin)->from('/administration/gam')->post('/test/save')->assertRedirect('/administration/gam');

        $this->gamReady = true;
        $this->actingAs($admin)->from('/administration/gam')->post('/test/save')->assertRedirect(route('dashboard'))->assertSessionHas('setup_status', 'GAM is set up.');
        $this->assertNull(session('setup.chain'));
        $this->assertSame([], app(Setup::class)->chain($runner));
    }
}
