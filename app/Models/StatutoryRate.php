<?php

namespace App\Models;

use App\Enums\PayrollRunStatus;
use App\Enums\StatutoryRateCode;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An effective-dated statutory rule set (FR-PRL-03): PAYE bands, NSSF rates or the LST schedule, stored as JSON.
 * Seeded values stay "unverified" until an authorised user confirms them against URA / NSSF publications
 * (docs/07 D2). A rule set used by an approved or paid payroll run can no longer be changed.
 */
class StatutoryRate extends Model
{
    use Auditable;

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
            'code' => StatutoryRateCode::class,
            'rules' => 'array',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Used by an approved or paid payroll run, so it must stay as it is.
     */
    public function isLocked(): bool
    {
        return PayrollRun::query()
            ->whereIn('status', [PayrollRunStatus::Approved->value, PayrollRunStatus::Paid->value])
            ->whereJsonContains('statutory_rate_ids', $this->id)
            ->exists();
    }

    /**
     * @param  Builder<StatutoryRate>  $query
     */
    public function scopeEffectiveOn(Builder $query, string $date): void
    {
        $query->where('effective_from', '<=', $date)
            ->where(fn (Builder $inner) => $inner->whereNull('effective_to')->orWhere('effective_to', '>=', $date));
    }
}
