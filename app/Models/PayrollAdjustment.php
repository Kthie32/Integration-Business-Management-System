<?php

namespace App\Models;

use App\Enums\PayComponentKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A one-off amount for one employee in one payroll run, e.g. arrears or the recovery of an overpayment. This is
 * how corrections to an approved run are made: in the next month's run (docs/07 D6).
 */
class PayrollAdjustment extends Model
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
            'kind' => PayComponentKind::class,
            'amount' => 'decimal:2',
            'is_taxable' => 'boolean',
            'is_nssf_applicable' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
