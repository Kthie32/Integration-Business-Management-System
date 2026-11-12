<?php

namespace Tests\Feature\Payroll;

use App\Enums\StatutoryRateCode;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\PayComponent;
use App\Models\SalaryStructure;
use App\Models\StatutoryRate;
use App\Models\User;
use Database\Seeders\PayrollSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPayroll;
use Tests\TestCase;

class PayrollConfigurationTest extends TestCase
{
    use CreatesPayroll, RefreshDatabase;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seedPayroll();
        $this->hr = $this->userWithRole(SystemRole::HumanResourceOfficer);
    }

    public function test_the_seeder_adds_unverified_rule_sets_and_starter_components_once(): void
    {
        $this->seed(PayrollSeeder::class);

        $this->assertSame(4, StatutoryRate::query()->count());
        $this->assertSame(0, StatutoryRate::query()->whereNotNull('verified_at')->count());
        $this->assertSame(count(PayrollSeeder::COMPONENTS), PayComponent::query()->count());
    }

    public function test_pay_components_are_created_and_an_allowance_cannot_be_a_percentage_of_gross(): void
    {
        $payload = ['code' => 'risk', 'name' => 'Risk allowance', 'kind' => 'allowance', 'calculation' => 'percent_basic', 'default_value' => '15', 'is_taxable' => '1', 'is_nssf_applicable' => '1', 'is_active' => '1'];

        $this->actingAs($this->hr)->post(route('pay-components.store'), $payload)->assertSessionHas('success');
        $this->assertSame('RISK', PayComponent::query()->where('name', 'Risk allowance')->sole()->code);

        $this->actingAs($this->hr)->post(route('pay-components.store'), [...$payload, 'code' => 'BONUS', 'calculation' => 'percent_gross'])
            ->assertSessionHas('error');
        $this->assertDatabaseMissing('pay_components', ['code' => 'BONUS']);

        $this->actingAs($this->hr)->post(route('pay-components.store'), [...$payload, 'name' => 'Duplicate'])->assertSessionHasErrors('code');
    }

    public function test_a_component_in_use_keeps_its_type_and_is_deactivated_not_deleted(): void
    {
        $housing = PayComponent::query()->where('code', 'HOUSING')->sole();
        $this->employeeWithSalary('800000', [$housing->id => '100000']);

        $this->actingAs($this->hr)->put(route('pay-components.update', $housing), [
            'code' => 'HOUSING', 'name' => 'Housing', 'kind' => 'deduction', 'calculation' => 'fixed', 'default_value' => '0',
        ])->assertSessionHas('error');

        $this->actingAs($this->hr)->delete(route('pay-components.destroy', $housing))->assertSessionHas('error');
        $this->assertModelExists($housing);

        $this->actingAs($this->hr)->put(route('pay-components.update', $housing), [
            'code' => 'HOUSING', 'name' => 'Housing', 'kind' => 'allowance', 'calculation' => 'fixed', 'default_value' => '0', 'is_active' => '0',
        ])->assertSessionHas('success');
        $this->assertFalse($housing->refresh()->is_active);
    }

    public function test_only_payroll_configurers_change_components_and_rates(): void
    {
        $accountant = $this->userWithRole(SystemRole::Accountant);
        $rate = StatutoryRate::query()->where('code', 'NSSF')->sole();

        $this->actingAs($accountant)->get(route('pay-components.index'))->assertOk();
        $this->actingAs($accountant)->get(route('statutory-rates.index'))->assertOk()->assertDontSee('New version');
        $this->actingAs($accountant)->post(route('pay-components.store'), ['code' => 'X'])->assertForbidden();
        $this->actingAs($accountant)->put(route('statutory-rates.update', $rate), ['employee_rate' => '1', 'employer_rate' => '1'])->assertForbidden();
        $this->actingAs($this->userWithRole(SystemRole::GeneralEmployee))->get(route('statutory-rates.index'))->assertForbidden();
    }

    public function test_a_new_rate_version_closes_the_previous_one_and_bands_must_follow_on(): void
    {
        $this->actingAs($this->hr)->get(route('statutory-rates.create', ['code' => 'PAYE_RESIDENT']))->assertOk()->assertSee('Additional tax on pay above');

        $bands = [
            ['from' => '0', 'to' => '250000', 'rate' => '0'],
            ['from' => '250000', 'to' => '', 'rate' => '25'],
        ];

        $this->actingAs($this->hr)->post(route('statutory-rates.store'), [
            'code' => 'PAYE_RESIDENT', 'effective_from' => '2026-12-01',
            'bands' => [['from' => '0', 'to' => '250000', 'rate' => '0'], ['from' => '260000', 'to' => '', 'rate' => '25']],
        ])->assertSessionHas('error', 'Each band must start where the previous one ends (250000).');

        $this->actingAs($this->hr)->post(route('statutory-rates.store'), [
            'code' => 'PAYE_RESIDENT', 'effective_from' => '2026-12-01', 'bands' => $bands, 'source_reference' => 'Finance Act 2026',
        ])->assertSessionHas('success');

        $versions = StatutoryRate::query()->where('code', StatutoryRateCode::PayeResident->value)->orderBy('effective_from')->get();
        $this->assertCount(2, $versions);
        $this->assertSame('2026-11-30', $versions[0]->effective_to->toDateString());
        $this->assertNull($versions[1]->effective_to);
        $this->assertEquals([['from' => '0', 'to' => '250000', 'rate' => '0'], ['from' => '250000', 'to' => null, 'rate' => '25']], $versions[1]->rules['bands']);
        $this->assertSame([], $versions[1]->rules['surcharges']);

        $this->actingAs($this->hr)->post(route('statutory-rates.store'), [
            'code' => 'PAYE_RESIDENT', 'effective_from' => '2026-12-01', 'bands' => $bands,
        ])->assertSessionHas('error');
    }

    public function test_rates_are_verified_with_a_source_and_correcting_them_clears_the_verification(): void
    {
        $rate = StatutoryRate::query()->where('code', 'NSSF')->sole();

        $this->actingAs($this->hr)->post(route('statutory-rates.verify', $rate), ['source_reference' => ''])->assertSessionHasErrors('source_reference');
        $this->actingAs($this->hr)->post(route('statutory-rates.verify', $rate), ['source_reference' => 'NSSF Act s.11'])->assertSessionHas('success');

        $rate->refresh();
        $this->assertTrue($rate->isVerified());
        $this->assertSame($this->hr->id, $rate->verified_by);

        $this->actingAs($this->hr)->get(route('statutory-rates.edit', $rate))->assertOk()->assertSee('Employee contribution');
        $this->actingAs($this->hr)->put(route('statutory-rates.update', $rate), ['employee_rate' => '5', 'employer_rate' => '10', 'source_reference' => 'NSSF Act s.11'])
            ->assertSessionHas('success');
        $this->assertTrue($rate->refresh()->isVerified());

        $this->actingAs($this->hr)->put(route('statutory-rates.update', $rate), ['employee_rate' => '5', 'employer_rate' => '11'])->assertSessionHas('success');
        $this->assertFalse($rate->refresh()->isVerified());

        $paye = StatutoryRate::query()->where('code', 'PAYE_RESIDENT')->sole();
        $this->actingAs($this->hr)->post(route('statutory-rates.verify', $paye), ['source_reference' => 'URA PAYE guide']);
        $this->actingAs($this->hr)->put(route('statutory-rates.update', $paye), [
            'bands' => $paye->rules['bands'], 'surcharge_above' => '10000000', 'surcharge_rate' => '10', 'source_reference' => 'URA PAYE guide',
        ])->assertSessionHas('success');
        $this->assertTrue($paye->refresh()->isVerified(), 'Saving unchanged bands must keep the verification.');

        $lst = StatutoryRate::query()->where('code', 'LST')->sole();
        $this->actingAs($this->hr)->put(route('statutory-rates.update', $lst), ['bands' => $lst->rules['bands'], 'months' => []])
            ->assertSessionHas('error', 'Choose at least one month in which LST is deducted.');
    }

    public function test_salary_structures_are_effective_dated_and_never_overlap(): void
    {
        $employee = Employee::factory()->create(['hire_date' => '2025-01-06']);
        $transport = PayComponent::query()->where('code', 'TRANSPORT')->sole();

        $this->actingAs($this->hr)->post(route('employees.salary-structures.store', $employee), [
            'basic_salary' => '900000', 'effective_from' => '2025-01-01',
        ])->assertSessionHas('error');

        $this->actingAs($this->hr)->post(route('employees.salary-structures.store', $employee), [
            'basic_salary' => '900000', 'effective_from' => '2025-01-06', 'components' => [$transport->id => '50000'],
        ])->assertSessionHas('success');

        $this->actingAs($this->hr)->post(route('employees.salary-structures.store', $employee), [
            'basic_salary' => '1100000', 'effective_from' => '2025-01-06',
        ])->assertSessionHas('error');

        $this->actingAs($this->hr)->post(route('employees.salary-structures.store', $employee), [
            'basic_salary' => '1100000', 'effective_from' => '2026-01-01', 'components' => [$transport->id => '60000'],
        ])->assertSessionHas('success');

        $structures = SalaryStructure::query()->where('employee_id', $employee->id)->orderBy('effective_from')->with('components')->get();
        $this->assertSame('2025-12-31', $structures[0]->effective_to->toDateString());
        $this->assertNull($structures[1]->effective_to);
        $this->assertSame('60000.0000', (string) $structures[1]->components->sole()->value);
        $this->assertSame('1100000.00', (string) SalaryStructure::query()->where('employee_id', $employee->id)->effectiveOn('2026-03-31')->sole()->basic_salary);

        $this->actingAs($this->hr)->delete(route('employees.salary-structures.destroy', [$employee, $structures[0]]))->assertSessionHas('error');
        $this->actingAs($this->hr)->delete(route('employees.salary-structures.destroy', [$employee, $structures[1]]))->assertSessionHas('success');
        $this->assertNull($structures[0]->refresh()->effective_to);
        $this->assertStringNotContainsString('900000', (string) json_encode(AuditLog::query()->where('auditable_type', SalaryStructure::class)->pluck('new_values')));
    }

    public function test_salaries_of_approved_months_are_not_changed_retroactively(): void
    {
        $employee = $this->employeeWithSalary('600000');
        $this->approvedRun($this->hr, $this->userWithRole(SystemRole::Manager), 2026, 10);

        $this->actingAs($this->hr)->post(route('employees.salary-structures.store', $employee), [
            'basic_salary' => '700000', 'effective_from' => '2026-10-01',
        ])->assertSessionHas('error');

        $this->actingAs($this->hr)->post(route('employees.salary-structures.store', $employee), [
            'basic_salary' => '700000', 'effective_from' => '2026-11-01',
        ])->assertSessionHas('success');
    }

    public function test_salary_details_are_visible_only_with_salary_access(): void
    {
        $department = Department::factory()->create();
        $employee = $this->employeeWithSalary('2345678', [], ['department_id' => $department->id]);
        $head = $this->userWithRole(SystemRole::DepartmentHead);
        $headRecord = Employee::factory()->create(['department_id' => $department->id]);
        $head->forceFill(['employee_id' => $headRecord->id])->save();
        $department->forceFill(['head_employee_id' => $headRecord->id])->save();

        $this->actingAs($this->hr)->get(route('employees.show', $employee))->assertOk()->assertSee('2,345,678')->assertSee('Record a salary change');
        $this->actingAs($this->userWithRole(SystemRole::Manager))->get(route('employees.show', $employee))->assertOk()->assertDontSee('2,345,678');
        $this->actingAs($head)->get(route('employees.show', $employee))->assertOk()->assertDontSee('2,345,678');
        $this->actingAs($this->userWithRole(SystemRole::Manager))
            ->post(route('employees.salary-structures.store', $employee), ['basic_salary' => '1', 'effective_from' => '2027-01-01'])
            ->assertForbidden();
    }
}
