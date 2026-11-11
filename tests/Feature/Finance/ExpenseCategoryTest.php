<?php

namespace Tests\Feature\Finance;

use App\Enums\SystemRole;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Database\Seeders\ExpenseCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Expense categories are finance configuration (settings.finance); those in use are deactivated, not deleted.
 */
class ExpenseCategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $financeOfficer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->financeOfficer = $this->userWithRole(SystemRole::FinanceOfficer);
    }

    public function test_a_finance_officer_creates_and_edits_categories(): void
    {
        $this->actingAs($this->financeOfficer)->post(route('expense-categories.store'), [
            'name' => 'Rent', 'code' => 'rent', 'description' => 'Office rent', 'is_active' => '1',
        ])->assertRedirect(route('expense-categories.index'));

        $category = ExpenseCategory::query()->sole();
        $this->assertSame('RENT', $category->code);
        $this->assertTrue($category->is_active);

        $this->actingAs($this->financeOfficer)->put(route('expense-categories.update', $category), [
            'name' => 'Rent and service charge', 'code' => 'RENT', 'is_active' => '0',
        ])->assertRedirect(route('expense-categories.index'));

        $category->refresh();
        $this->assertSame('Rent and service charge', $category->name);
        $this->assertFalse($category->is_active);

        $this->actingAs($this->financeOfficer)->post(route('expense-categories.store'), ['name' => 'Rent and service charge', 'is_active' => '1'])
            ->assertSessionHasErrors('name');
    }

    public function test_a_category_with_expenses_cannot_be_deleted(): void
    {
        $used = ExpenseCategory::factory()->create();
        Expense::factory()->create(['expense_category_id' => $used->id]);
        $unused = ExpenseCategory::factory()->create();

        $this->actingAs($this->financeOfficer)->delete(route('expense-categories.destroy', $used))->assertSessionHas('error');
        $this->assertModelExists($used);

        $this->actingAs($this->financeOfficer)->delete(route('expense-categories.destroy', $unused))->assertSessionHas('success');
        $this->assertModelMissing($unused);
    }

    public function test_only_finance_settings_holders_manage_categories(): void
    {
        $accountant = $this->userWithRole(SystemRole::Accountant);
        $category = ExpenseCategory::factory()->create();

        $this->actingAs($accountant)->get(route('expense-categories.index'))->assertOk()->assertDontSee('New category');
        $this->actingAs($accountant)->post(route('expense-categories.store'), ['name' => 'Travel', 'is_active' => '1'])->assertForbidden();
        $this->actingAs($accountant)->put(route('expense-categories.update', $category), ['name' => 'X', 'is_active' => '1'])->assertForbidden();
        $this->actingAs($accountant)->delete(route('expense-categories.destroy', $category))->assertForbidden();

        $this->actingAs($this->userWithRole(SystemRole::GeneralEmployee))->get(route('expense-categories.index'))->assertForbidden();
    }

    public function test_the_seeder_adds_defaults_only_once(): void
    {
        $this->seed(ExpenseCategorySeeder::class);
        $this->assertSame(count(ExpenseCategorySeeder::DEFAULTS), ExpenseCategory::query()->count());

        ExpenseCategory::query()->first()->update(['name' => 'Renamed']);
        $this->seed(ExpenseCategorySeeder::class);

        $this->assertSame(count(ExpenseCategorySeeder::DEFAULTS), ExpenseCategory::query()->count());
        $this->assertDatabaseHas('expense_categories', ['name' => 'Renamed']);
    }
}
