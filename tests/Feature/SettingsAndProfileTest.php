<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BroSevenHelpers;
use Tests\TestCase;

class SettingsAndProfileTest extends TestCase
{
    use BroSevenHelpers;
    use RefreshDatabase;

    private array $colors = [
        'primary' => '#ff6b6b',
        'secondary' => '#FFD166',
        'danger' => '#EF233C',
        'background' => '#F7FFF7',
        'dark' => '#26547C',
    ];

    public function test_colors_can_be_saved_reflected_publicly_and_reset(): void
    {
        $this->signIn(Role::SUPERADMIN);

        $this->getJson('/api/settings/colors')->assertOk()->assertJsonPath('data.primary', '#06D6A0')->assertJsonPath('defaults.dark', '#26547C');

        $this->putJson('/api/settings/colors', $this->colors)->assertOk()->assertJsonPath('data.primary', '#FF6B6B');
        $this->assertDatabaseHas('website_settings', ['primary_color' => '#FF6B6B']);
        $this->getJson('/api/store/info')->assertJsonPath('colors.primary', '#FF6B6B');

        $this->postJson('/api/settings/colors/reset')->assertOk()->assertJsonPath('data.primary', '#06D6A0');
        $this->getJson('/api/store/info')->assertJsonPath('colors.primary', '#06D6A0');

        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.colors_updated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.colors_reset']);
    }

    public function test_colors_are_validated_and_unreadable_combinations_are_rejected(): void
    {
        $this->signIn(Role::SUPERADMIN);

        $this->putJson('/api/settings/colors', array_merge($this->colors, ['primary' => '#GGGGGG']))->assertUnprocessable()->assertJsonValidationErrors('primary');
        $this->putJson('/api/settings/colors', array_merge($this->colors, ['dark' => '06D6A0']))->assertUnprocessable()->assertJsonValidationErrors('dark');
        $this->putJson('/api/settings/colors', array_merge($this->colors, ['dark' => '#FFFFFF']))->assertUnprocessable()->assertJsonValidationErrors('dark');
        $this->putJson('/api/settings/colors', array_merge($this->colors, ['dark' => '#F0F0F0']))->assertUnprocessable()->assertJsonValidationErrors('dark');
        $this->putJson('/api/settings/colors', [])->assertUnprocessable();
    }


    public function test_company_profile_is_sanitised_and_validated(): void
    {
        $this->signIn(Role::SUPERADMIN);
        $headers = ['Accept' => 'application/json'];

        $this->post('/api/settings/company', ['store_name' => ''], $headers)->assertUnprocessable()->assertJsonValidationErrors('store_name');
        $this->post('/api/settings/company', ['store_name' => 'Toko', 'phone' => 'abc'], $headers)->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->post('/api/settings/company', ['store_name' => 'Toko', 'email' => 'bukan-email'], $headers)->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->post('/api/settings/company', [
            'store_name' => '<b>Toko Baru</b>',
            'phone' => '0411-123456',
            'address' => '<script>x</script>Jl. Contoh 1',
            'footer_text' => 'Hak cipta',
        ], $headers)->assertOk()->assertJsonPath('data.store_name', 'Toko Baru')->assertJsonPath('data.address', 'xJl. Contoh 1');

        $this->getJson('/api/store/info')->assertJsonPath('company.store_name', 'Toko Baru')->assertJsonPath('company.phone', '0411-123456');
    }

    public function test_logo_and_favicon_uploads_are_validated_and_old_files_are_removed(): void
    {
        Storage::fake('public');
        $this->signIn(Role::SUPERADMIN);
        $headers = ['Accept' => 'application/json'];

        $this->post('/api/settings/company', ['store_name' => 'Toko', 'logo' => UploadedFile::fake()->create('logo.svg', 5, 'image/svg+xml')], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('logo');
        $this->post('/api/settings/company', ['store_name' => 'Toko', 'favicon' => UploadedFile::fake()->image('f.png', 32, 32)->size(600)], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('favicon');

        $this->post('/api/settings/company', ['store_name' => 'Toko', 'logo' => UploadedFile::fake()->image('logo.png', 200, 100)], $headers)->assertOk();
        $first = CompanySetting::current()->logo_path;
        Storage::disk('public')->assertExists($first);
        $this->getJson('/api/store/info')->assertJsonPath('company.logo_url', '/storage/'.$first);

        $this->post('/api/settings/company', ['store_name' => 'Toko', 'logo' => UploadedFile::fake()->image('logo2.png', 200, 100)], $headers)->assertOk();
        Storage::disk('public')->assertMissing($first);

        $second = CompanySetting::current()->logo_path;
        $this->post('/api/settings/company', ['store_name' => 'Toko', 'remove_logo' => 1], $headers)->assertOk();
        Storage::disk('public')->assertMissing($second);
        $this->assertNull(CompanySetting::current()->logo_path);
    }

    public function test_profile_update_validation_and_avatar(): void
    {
        Storage::fake('public');
        $user = $this->signIn(Role::ADMIN_MASTER, ['username' => 'profil.saya', 'email' => 'profil.saya@broseven.test']);
        $other = $this->makeUser(Role::CASHIER, ['username' => 'orang.lain', 'email' => 'orang.lain@broseven.test']);
        $headers = ['Accept' => 'application/json'];
        $base = ['name' => 'Nama Saya', 'username' => 'profil.saya', 'email' => 'profil.saya@broseven.test'];

        $this->post('/api/profile', array_merge($base, ['username' => $other->username]), $headers)->assertUnprocessable()->assertJsonValidationErrors('username');
        $this->post('/api/profile', array_merge($base, ['email' => $other->email]), $headers)->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->post('/api/profile', array_merge($base, ['avatar' => UploadedFile::fake()->create('x.jpg', 5)]), $headers)->assertUnprocessable()->assertJsonValidationErrors('avatar');

        $this->post('/api/profile', array_merge($base, ['avatar' => UploadedFile::fake()->image('a.png', 100, 100)]), $headers)->assertOk();
        $first = $user->fresh()->avatar_path;
        Storage::disk('public')->assertExists($first);

        $this->post('/api/profile', array_merge($base, ['avatar' => UploadedFile::fake()->image('b.png', 100, 100)]), $headers)->assertOk();
        Storage::disk('public')->assertMissing($first);

        $this->post('/api/profile', array_merge($base, ['remove_avatar' => 1]), $headers)->assertOk();
        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_change_password_rules(): void
    {
        $user = $this->signIn(Role::CASHIER);

        $this->putJson('/api/profile/password', ['current_password' => 'salah', 'password' => 'BaruAman123', 'password_confirmation' => 'BaruAman123'])
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->putJson('/api/profile/password', ['current_password' => 'password', 'password' => 'lemah', 'password_confirmation' => 'lemah'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->putJson('/api/profile/password', ['current_password' => 'password', 'password' => 'BaruAman123', 'password_confirmation' => 'BedaSemua1'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->putJson('/api/profile/password', ['current_password' => 'password', 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->putJson('/api/profile/password', ['current_password' => 'password', 'password' => 'BaruAman123', 'password_confirmation' => 'BaruAman123'])->assertOk();

        $this->assertTrue(Hash::check('BaruAman123', User::find($user->id)->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'profile.password_changed']);
    }
}
