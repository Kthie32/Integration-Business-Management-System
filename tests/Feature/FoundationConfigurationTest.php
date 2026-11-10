<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FoundationConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tests_run_against_the_dedicated_mysql_testing_database(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertSame('ibms_testing', DB::connection()->getDatabaseName());
        $this->assertTrue(Schema::hasTable('users'));
    }

    public function test_mysql_runs_in_strict_mode_so_invalid_values_are_rejected(): void
    {
        $sqlMode = DB::selectOne('SELECT @@SESSION.sql_mode AS mode')->mode;

        $this->assertStringContainsString('STRICT_TRANS_TABLES', $sqlMode);
    }

    public function test_application_uses_kampala_timezone(): void
    {
        $this->assertSame('Africa/Kampala', config('app.timezone'));
        $this->assertSame('Africa/Kampala', now()->getTimezone()->getName());
    }

    public function test_login_page_renders_with_bootstrap_assets(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee(config('app.name'));
        $response->assertSee('/build/assets/app-', false);
    }
}
