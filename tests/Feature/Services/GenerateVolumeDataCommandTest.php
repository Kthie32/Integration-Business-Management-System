<?php

namespace Tests\Feature\Services;

use App\Enums\PayrollRunStatus;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\PayrollRun;
use App\Models\Quotation;
use App\Models\Requisition;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\InventorySeeder;
use Database\Seeders\PayrollSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateVolumeDataCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_issued_invoices_documents_and_paid_payroll_runs_through_the_services(): void
    {
        $this->seedRolesAndPermissions();
        $this->seed([SettingsSeeder::class, InventorySeeder::class, ExpenseCategorySeeder::class, PayrollSeeder::class]);

        $this->artisan('ibms:demo:volume', ['--invoices' => 8, '--employees' => 2, '--months' => 3, '--force' => true])->assertSuccessful();

        $this->assertSame(8, Invoice::query()->whereNotNull('number')->count());
        $this->assertSame(300, Customer::query()->count());
        $this->assertSame(2, Quotation::query()->count());
        $this->assertSame(2, Expense::query()->count());
        $this->assertSame(1, Requisition::query()->count());
        $this->assertSame(2, PayrollRun::query()->where('status', PayrollRunStatus::Paid)->count());
    }

    public function test_it_refuses_to_run_in_production(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->artisan('ibms:demo:volume', ['--force' => true])->assertFailed();

        $this->assertSame(0, Invoice::query()->count());
    }
}
