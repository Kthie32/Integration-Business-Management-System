<?php

namespace Tests\Feature\Admin;

use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditLogControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_user_without_audit_permission_is_forbidden(): void
    {
        $accountant = User::factory()->withRole(SystemRole::Accountant)->create();

        $this->actingAs($accountant)->get(route('admin.audit-logs.index'))->assertForbidden();
    }

    public function test_manager_can_browse_and_filter_the_audit_log(): void
    {
        $manager = User::factory()->withRole(SystemRole::Manager)->create();
        AuditLog::create(['event' => 'login', 'description' => 'Signed in from the office']);
        AuditLog::create(['event' => 'logout', 'description' => 'Signed out at night']);

        $this->actingAs($manager)->get(route('admin.audit-logs.index', ['event' => 'login']))
            ->assertOk()
            ->assertSee('Signed in from the office')
            ->assertDontSee('Signed out at night');
    }

    public function test_entry_detail_shows_before_and_after_values(): void
    {
        $manager = User::factory()->withRole(SystemRole::Manager)->create();
        $log = AuditLog::create(['event' => 'updated', 'old_values' => ['name' => 'Old Name'], 'new_values' => ['name' => 'New Name']]);

        $this->actingAs($manager)->get(route('admin.audit-logs.show', $log))
            ->assertOk()
            ->assertSee('Old Name')
            ->assertSee('New Name');
    }

    public function test_audit_entries_cannot_be_changed_or_deleted(): void
    {
        $log = AuditLog::create(['event' => 'login']);

        try {
            $log->update(['event' => 'tampered']);
            $this->fail('Updating an audit entry should throw.');
        } catch (LogicException) {
        }

        $this->expectException(LogicException::class);
        $log->delete();
    }
}
