<?php

namespace Tests\Feature\Security;

use App\Enums\SystemRole;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\PayrollItem;
use App\Models\User;
use App\Services\Sales\InvoiceService;
use Database\Seeders\ApprovalWorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPayroll;
use Tests\Concerns\CreatesRequisitions;
use Tests\TestCase;

/**
 * Record-level rules (docs/05 §3): users holding a module permission still see only the records they are entitled to.
 * Each test requests another person's record directly by its address, as an attacker guessing IDs would.
 */
class RecordLevelAccessTest extends TestCase
{
    use CreatesPayroll, CreatesRequisitions, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_an_employee_opens_only_their_own_payslip_and_only_once_the_run_is_approved(): void
    {
        $this->seedPayroll();
        $alice = $this->employeeWithSalary('1200000');
        $bob = $this->employeeWithSalary('1500000');
        $aliceAccount = $this->userWithRole(SystemRole::GeneralEmployee);
        $aliceAccount->forceFill(['employee_id' => $alice->id])->save();
        $preparer = $this->userWithRole(SystemRole::HumanResourceOfficer);
        $draft = $this->calculatedRun($preparer, 2026, 1);
        $approved = $this->approvedRun($preparer, $this->userWithRole(SystemRole::FinanceOfficer), 2026, 2);

        $ownDraft = $this->actingAs($aliceAccount)->get(route('payslips.show', $this->payslip($draft->items(), $alice)));
        $ownApproved = $this->actingAs($aliceAccount)->get(route('payslips.show', $this->payslip($approved->items(), $alice)));
        $colleagues = $this->actingAs($aliceAccount)->get(route('payslips.pdf', $this->payslip($approved->items(), $bob)));

        $ownDraft->assertForbidden();
        $ownApproved->assertOk();
        $colleagues->assertForbidden();
    }

    /**
     * Uses the standard approval route installed with the system (department head first).
     */
    public function test_requisitions_are_visible_to_the_requester_their_department_head_and_company_wide_roles_only(): void
    {
        $this->seed(ApprovalWorkflowSeeder::class);
        [$stores, $storesHead] = $this->departmentWithHead();
        [, $otherHead] = $this->departmentWithHead();
        $requester = $this->userWithRole(SystemRole::GeneralEmployee);
        $requisition = $this->submittedRequisition($requester, $stores);
        $url = route('requisitions.show', $requisition);

        $this->actingAs($requester)->get($url)->assertOk();
        $this->actingAs($storesHead)->get($url)->assertOk();
        $this->actingAs($this->userWithRole(SystemRole::Accountant))->get($url)->assertOk();
        $this->actingAs($this->userWithRole(SystemRole::GeneralEmployee))->get($url)->assertForbidden();
        $this->actingAs($otherHead)->get($url)->assertForbidden();
        $this->actingAs($otherHead)->get(route('requisitions.pdf', $requisition))->assertForbidden();
    }

    public function test_a_requisition_list_never_includes_other_peoples_requests_for_an_employee(): void
    {
        $department = Department::factory()->create();
        $requester = $this->userWithRole(SystemRole::GeneralEmployee);
        $colleague = $this->userWithRole(SystemRole::GeneralEmployee);
        $own = $this->draftRequisition($requester, $department);
        $theirs = $this->draftRequisition($colleague, $department);

        $response = $this->actingAs($requester)->get(route('requisitions.index'));

        $response->assertOk();
        $response->assertSee($own->number);
        $response->assertDontSee($theirs->number);
    }

    public function test_a_department_head_sees_employees_of_their_own_department_only(): void
    {
        [$stores, $storesHead] = $this->departmentWithHead();
        $storesClerk = Employee::factory()->create(['department_id' => $stores->id]);
        $accountsClerk = Employee::factory()->create(['department_id' => Department::factory()->create()->id]);

        $this->actingAs($storesHead)->get(route('employees.show', $storesClerk))->assertOk();
        $this->actingAs($storesHead)->get(route('employees.show', $accountsClerk))->assertForbidden();
        $this->actingAs($storesHead)->get(route('employees.edit', $storesClerk))->assertForbidden();
    }

    public function test_identity_and_bank_details_are_shown_only_with_salary_access(): void
    {
        $employee = Employee::factory()->withSensitiveDetails()->create();

        $manager = $this->actingAs($this->userWithRole(SystemRole::Manager))->get(route('employees.show', $employee));
        $hr = $this->actingAs($this->userWithRole(SystemRole::HumanResourceOfficer))->get(route('employees.show', $employee));

        $manager->assertOk();
        $manager->assertDontSee($employee->tin);
        $manager->assertDontSee($employee->bank_account);
        $hr->assertOk();
        $hr->assertSee($employee->tin);
    }

    public function test_an_issued_invoice_cannot_be_edited_or_deleted_even_by_the_super_administrator(): void
    {
        $admin = $this->userWithRole(SystemRole::SuperAdministrator);
        $invoice = $this->issuedInvoice($admin);

        $this->actingAs($admin)->get(route('invoices.edit', $invoice))->assertForbidden();
        $this->actingAs($admin)->delete(route('invoices.destroy', $invoice))->assertForbidden();
        $this->assertModelExists($invoice);
    }

    private function payslip(mixed $items, Employee $employee): PayrollItem
    {
        return $items->where('employee_id', $employee->id)->sole();
    }

    private function issuedInvoice(User $actor): Invoice
    {
        $invoices = app(InvoiceService::class);

        return $invoices->issue($invoices->createDraft([
            'customer_id' => Customer::factory()->create()->id,
            'invoice_date' => today()->toDateString(),
            'lines' => [['description' => 'Consulting', 'quantity' => '1', 'unit_price' => '250000']],
        ], $actor), $actor);
    }
}
