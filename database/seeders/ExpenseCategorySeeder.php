<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

/**
 * Seeds a starter set of expense categories. Does nothing once any category exists, so the organization's
 * own list is never changed.
 */
class ExpenseCategorySeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    public const DEFAULTS = [
        'RENT' => 'Rent',
        'UTIL' => 'Utilities (power and water)',
        'COMM' => 'Airtime, data and internet',
        'TRANS' => 'Transport and fuel',
        'OFFICE' => 'Office supplies',
        'REPAIR' => 'Repairs and maintenance',
        'PROF' => 'Professional fees',
        'BANK' => 'Bank and mobile money charges',
        'TAXLIC' => 'Taxes, licences and permits',
        'MKTG' => 'Marketing and advertising',
        'OTHER' => 'Other expenses',
    ];

    public function run(): void
    {
        if (ExpenseCategory::query()->exists()) {
            return;
        }

        foreach (self::DEFAULTS as $code => $name) {
            ExpenseCategory::query()->create(['code' => $code, 'name' => $name, 'is_active' => true]);
        }
    }
}
