<?php

namespace Tests\Feature\Settings;

use App\Enums\SystemRole;
use App\Models\User;
use App\Services\System\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrganizationSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->owner = $this->userWithRole(SystemRole::SuperAdministrator);
        Storage::fake('public');
    }

    /**
     * @return array<string, string>
     */
    private function validProfile(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Kampala Traders Ltd',
            'address' => 'Plot 12, Kampala Road',
            'phone' => '+256 700 000000',
            'email' => 'info@kampalatraders.test',
            'website' => 'https://kampalatraders.test',
            'tin' => '1000123456',
            'brand_color' => '#0a7d4f',
        ], $overrides);
    }

    public function test_user_without_organization_permission_is_forbidden(): void
    {
        $financeOfficer = User::factory()->withRole(SystemRole::FinanceOfficer)->create();

        $this->actingAs($financeOfficer)->get(route('settings.organization.edit'))->assertForbidden();
        $this->actingAs($financeOfficer)->put(route('settings.organization.update'), $this->validProfile())->assertForbidden();
    }

    public function test_saving_the_profile_updates_the_name_shown_across_the_system_and_is_audited(): void
    {
        $this->actingAs($this->owner)
            ->put(route('settings.organization.update'), $this->validProfile())
            ->assertRedirect(route('settings.organization.edit'))
            ->assertSessionHas('success');

        $this->assertSame('Kampala Traders Ltd', app(SettingsService::class)->organizationName());
        $this->assertDatabaseHas('audit_logs', ['event' => 'settings_updated', 'description' => 'Updated organization profile']);

        $this->actingAs($this->owner)->get(route('dashboard'))
            ->assertSee('Kampala Traders Ltd')
            ->assertSee('--bs-primary: #0a7d4f;', false);
    }

    public function test_rejects_an_invalid_profile(): void
    {
        $this->actingAs($this->owner)
            ->put(route('settings.organization.update'), $this->validProfile(['name' => '', 'email' => 'not-an-email', 'brand_color' => 'red']))
            ->assertSessionHasErrors([
                'name',
                'email',
                'brand_color' => 'Choose a colour in the form #RRGGBB.',
            ]);

        $this->assertDatabaseCount('settings', 0);
    }

    public function test_uploading_a_logo_stores_it_and_replaces_the_previous_one(): void
    {
        $this->actingAs($this->owner)->put(route('settings.organization.update'), $this->validProfile([
            'logo' => UploadedFile::fake()->image('first.png', 200, 80),
        ]))->assertSessionHasNoErrors();

        $firstLogo = app(SettingsService::class)->get('organization', 'logo_path');
        Storage::disk('public')->assertExists($firstLogo);

        app(SettingsService::class)->forget('organization');
        $this->actingAs($this->owner)->put(route('settings.organization.update'), $this->validProfile([
            'logo' => UploadedFile::fake()->image('second.jpg', 200, 80),
        ]))->assertSessionHasNoErrors();

        $secondLogo = app(SettingsService::class)->get('organization', 'logo_path');
        Storage::disk('public')->assertExists($secondLogo);
        Storage::disk('public')->assertMissing($firstLogo);
    }

    public function test_rejects_logos_that_are_too_large_or_not_images(): void
    {
        $this->actingAs($this->owner)->put(route('settings.organization.update'), $this->validProfile([
            'logo' => UploadedFile::fake()->image('huge.png')->size(1500),
        ]))->assertSessionHasErrors(['logo' => 'The logo must not be larger than 1 MB.']);

        $this->actingAs($this->owner)->put(route('settings.organization.update'), $this->validProfile([
            'logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
        ]))->assertSessionHasErrors('logo');
    }

    public function test_removing_the_logo_deletes_the_file(): void
    {
        $this->actingAs($this->owner)->put(route('settings.organization.update'), $this->validProfile([
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]));
        $logo = app(SettingsService::class)->get('organization', 'logo_path');

        $this->actingAs($this->owner)->delete(route('settings.organization.logo.destroy'))
            ->assertRedirect(route('settings.organization.edit'));

        Storage::disk('public')->assertMissing($logo);
        $this->assertNull(app(SettingsService::class)->get('organization', 'logo_path'));
    }
}
