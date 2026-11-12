<?php

namespace App\Models;

use App\Enums\PayrollLineKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a payslip breakdown: basic salary, an allowance, a deduction, a statutory deduction or an employer
 * contribution. Stored, so a payslip is reproduced exactly as calculated (FR-PRL-05).
 */
class PayrollItemLine extends Model
{
    /**
     * @var list<string>
     */
    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => PayrollLineKind::class,
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<PayrollItem, $this>
     */
    public function payrollItem(): BelongsTo
    {
        return $this->belongsTo(PayrollItem::class);
    }
}
