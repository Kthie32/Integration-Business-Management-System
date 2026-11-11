<?php

namespace Tests\Feature\Finance;

use App\Enums\ExpenseStatus;
use App\Enums\SystemRole;
use App\Exceptions\BusinessRuleException;
use App\Models\Department;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Finance\ExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Expenses (FR-EXP-01..03): drafts, recording with a number, voiding with a reason, attachments and the
 * default role permissions.
 */
class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Expense::DISK);
        $this->seedRolesAndPermissions();
        $this->accountant = $this->userWithRole(SystemRole::Accountant);
        $this->category = ExpenseCategory::factory()->create(['name' => 'Transport and fuel']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'expense_date' => today()->toDateString(),
            'expense_category_id' => $this->category->id,
            'supplier_id' => Supplier::factory()->create(['name' => 'Total Energies Ntinda'])->id,
            'department_id' => Department::factory()->create()->id,
            'description' => 'Fuel for delivery van',
            'amount' => '118000',
            'tax_amount' => '18000',
            'payment_method' => 'mobile_money',
            'reference' => 'MM-778899',
            'action' => 'save',
            ...$overrides,
        ];
    }

    private function draft(array $overrides = []): Expense
    {
        $data = $this->payload($overrides);

        return app(ExpenseService::class)->createDraft(collect($data)->except('action')->all(), null, $this->accountant);
    }

    public function test_saving_creates_an_unnumbered_draft_with_its_attachment(): void
    {
        $this->actingAs($this->accountant)->get(route('expenses.create'))->assertOk()->assertSee('Transport and fuel');

        $response = $this->actingAs($this->accountant)->post(route('expenses.store'), $this->payload([
            'attachment' => UploadedFile::fake()->create('fuel-receipt.pdf', 200, 'application/pdf'),
        ]));

        $expense = Expense::query()->sole();
        $response->assertRedirect(route('expenses.show', $expense));

        $this->assertSame(ExpenseStatus::Draft, $expense->status);
        $this->assertNull($expense->number);
        $this->assertSame('118000.00', (string) $expense->amount);
        $this->assertSame('100000.00', $expense->netAmount());
        $this->assertSame('fuel-receipt.pdf', $expense->attachment_name);
        $this->assertStringNotContainsString('fuel-receipt', $expense->attachment_path);
        Storage::disk(Expense::DISK)->assertExists($expense->attachment_path);
    }

    public function test_save_and_record_numbers_and_locks_the_expense(): void
    {
        $this->actingAs($this->accountant)->post(route('expenses.store'), $this->payload(['action' => 'record']))
            ->assertSessionHas('success');

        $expense = Expense::query()->sole();
        $this->assertSame(ExpenseStatus::Recorded, $expense->status);
        $this->assertSame('EXP-'.now()->format('Y').'-00001', $expense->number);
        $this->assertSame($this->accountant->id, $expense->recorded_by);
        $this->assertSame(2, $expense->statusHistories()->count());

        $this->actingAs($this->accountant)->get(route('expenses.edit', $expense))->assertForbidden();
        $this->actingAs($this->accountant)->put(route('expenses.update', $expense), $this->payload())->assertForbidden();
        $this->actingAs($this->accountant)->delete(route('expenses.destroy', $expense))->assertForbidden();
    }

    public function test_a_draft_can_be_edited_and_its_attachment_replaced(): void
    {
        $this->actingAs($this->accountant)->post(route('expenses.store'), $this->payload([
            'attachment' => UploadedFile::fake()->image('first.jpg'),
        ]));
        $expense = Expense::query()->sole();
        $oldPath = $expense->attachment_path;

        $this->actingAs($this->accountant)->put(route('expenses.update', $expense), $this->payload([
            'description' => 'Fuel and parking',
            'attachment' => UploadedFile::fake()->image('second.png'),
        ]))->assertRedirect(route('expenses.show', $expense));

        $expense->refresh();
        $this->assertSame('Fuel and parking', $expense->description);
        $this->assertSame('second.png', $expense->attachment_name);
        Storage::disk(Expense::DISK)->assertMissing($oldPath);
        Storage::disk(Expense::DISK)->assertExists($expense->attachment_path);

        $this->actingAs($this->accountant)->put(route('expenses.update', $expense), $this->payload(['remove_attachment' => '1']));
        $this->assertNull($expense->fresh()->attachment_path);
    }

    public function test_a_draft_can_be_deleted_with_its_attachment(): void
    {
        $this->actingAs($this->accountant)->post(route('expenses.store'), $this->payload([
            'attachment' => UploadedFile::fake()->create('quote.pdf', 50, 'application/pdf'),
        ]));
        $expense = Expense::query()->sole();

        $this->actingAs($this->accountant)->delete(route('expenses.destroy', $expense))->assertRedirect(route('expenses.index'));

        $this->assertModelMissing($expense);
        Storage::disk(Expense::DISK)->assertMissing($expense->attachment_path);
    }

    public function test_validation_rejects_bad_amounts_dates_and_files(): void
    {
        $this->actingAs($this->accountant)->post(route('expenses.store'), $this->payload([
            'amount' => '1000',
            'tax_amount' => '2000',
            'expense_date' => today()->addDay()->toDateString(),
            'payment_method' => 'bank_transfer',
            'reference' => '',
            'attachment' => UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream'),
        ]))->assertSessionHasErrors(['tax_amount', 'expense_date', 'reference', 'attachment']);

        $this->assertSame(0, Expense::query()->count());
    }

    public function test_an_inactive_category_cannot_be_used_for_new_expenses_or_recording(): void
    {
        $inactive = ExpenseCategory::factory()->inactive()->create();

        $this->actingAs($this->accountant)->post(route('expenses.store'), $this->payload(['expense_category_id' => $inactive->id]))
            ->assertSessionHas('error');
        $this->assertSame(0, Expense::query()->count());

        $draft = $this->draft();
        $this->category->update(['is_active' => false]);

        $this->expectException(BusinessRuleException::class);
        app(ExpenseService::class)->record($draft, $this->accountant);
    }

    public function test_voiding_needs_a_reason_and_keeps_the_record(): void
    {
        $expense = app(ExpenseService::class)->record($this->draft(), $this->accountant);

        $this->actingAs($this->accountant)->post(route('expenses.void', $expense), ['reason' => ''])->assertSessionHasErrors('reason');

        $this->actingAs($this->accountant)->post(route('expenses.void', $expense), ['reason' => 'Paid twice by mistake'])
            ->assertRedirect(route('expenses.show', $expense));

        $expense->refresh();
        $this->assertSame(ExpenseStatus::Void, $expense->status);
        $this->assertSame('Paid twice by mistake', $expense->void_reason);
        $this->assertSame($this->accountant->id, $expense->voided_by);
        $this->assertDatabaseHas('audit_logs', ['event' => 'voided', 'auditable_id' => $expense->id, 'auditable_type' => $expense->getMorphClass()]);

        $this->actingAs($this->accountant)->post(route('expenses.void', $expense), ['reason' => 'Again please'])->assertForbidden();
    }

    public function test_drafts_cannot_be_voided_and_recorded_expenses_cannot_be_recorded_again(): void
    {
        $draft = $this->draft();
        $this->actingAs($this->accountant)->post(route('expenses.void', $draft), ['reason' => 'Not recorded yet'])->assertForbidden();

        $this->actingAs($this->accountant)->post(route('expenses.record', $draft))->assertRedirect(route('expenses.show', $draft));
        $this->actingAs($this->accountant)->post(route('expenses.record', $draft))->assertForbidden();
        $this->assertSame('EXP-'.now()->format('Y').'-00001', $draft->fresh()->number);
    }

    public function test_the_register_totals_only_recorded_expenses_and_filters(): void
    {
        $service = app(ExpenseService::class);
        $recorded = $service->record($this->draft(['description' => 'Recorded fuel']), $this->accountant);
        $this->draft(['description' => 'Draft stationery', 'amount' => '5000', 'tax_amount' => '0']);
        $voided = $service->void($service->record($this->draft(['description' => 'Voided rent', 'amount' => '7000', 'tax_amount' => '0']), $this->accountant), $this->accountant, 'Duplicate entry');

        $this->actingAs($this->accountant)->get(route('expenses.index'))
            ->assertOk()
            ->assertSee('Recorded fuel')
            ->assertSee('Draft stationery')
            ->assertViewHas('total', '118000.00');

        $this->actingAs($this->accountant)->get(route('expenses.index', ['status' => 'void']))
            ->assertOk()
            ->assertSee($voided->number)
            ->assertDontSee($recorded->number);
    }

    public function test_attachments_are_only_served_to_users_who_can_view_expenses(): void
    {
        $this->actingAs($this->accountant)->post(route('expenses.store'), $this->payload([
            'attachment' => UploadedFile::fake()->create('fuel.pdf', 20, 'application/pdf'),
        ]));
        $expense = Expense::query()->sole();

        $this->actingAs($this->accountant)->get(route('expenses.attachment', $expense))
            ->assertOk()
            ->assertDownload('fuel.pdf');

        $this->actingAs($this->userWithRole(SystemRole::InventoryOfficer))->get(route('expenses.attachment', $expense))->assertForbidden();
    }

    public function test_default_roles_follow_the_permission_matrix(): void
    {
        $expense = app(ExpenseService::class)->record($this->draft(), $this->accountant);

        $manager = $this->userWithRole(SystemRole::Manager);
        $this->actingAs($manager)->get(route('expenses.show', $expense))->assertOk();
        $this->actingAs($manager)->get(route('expenses.create'))->assertForbidden();

        $procurement = $this->userWithRole(SystemRole::ProcurementOfficer);
        $this->actingAs($procurement)->get(route('expenses.create'))->assertOk();
        $this->actingAs($procurement)->post(route('expenses.void', $expense), ['reason' => 'Not my call'])->assertForbidden();

        $employee = $this->userWithRole(SystemRole::GeneralEmployee);
        $this->actingAs($employee)->get(route('expenses.index'))->assertForbidden();
        $this->actingAs($employee)->get(route('expenses.show', $expense))->assertForbidden();
        $this->actingAs($employee)->post(route('expenses.store'), $this->payload())->assertForbidden();
    }

    public function test_the_dashboard_shows_expenses_recorded_this_month(): void
    {
        app(ExpenseService::class)->record($this->draft(), $this->accountant);
        $financeOfficer = $this->userWithRole(SystemRole::FinanceOfficer);

        $this->actingAs($financeOfficer)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Expenses this month')
            ->assertSee('UGX 118,000');
    }
}
