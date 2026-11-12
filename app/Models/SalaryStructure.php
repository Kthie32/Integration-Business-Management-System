<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An employee's basic salary and assigned pay components from a given date (FR-PRL-02). Structures never
 * overlap: adding a newer one closes the previous one the day before. The list of structures is the
 * employee's salary history (FR-EMP-05) and is shown only with employees.view_salary.
 */
class SalaryStructure extends Model
{
    use Auditable;

    /**
     * @var list<string>
     */
    protected $guarded = ['*'];

    /**
     * Amounts are kept out of the audit log, which users without salary access can read.
     *
     * @return list<string>
     */
    public function auditExclude(): array
    {
        return ['basic_salary'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    /**
     * @return HasMany<SalaryStructureComponent, $this>
     */
    public function components(): HasMany
    {
        return $this->hasMany(SalaryStructureComponent::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isInUse(): bool
    {
        return PayrollItem::query()->where('salary_structure_id', $this->id)->exists();
    }

    /**
     * Structures in effect on the given date (Y-m-d).
     *
     * @param  Builder<SalaryStructure>  $query
     */
    public function scopeEffectiveOn(Builder $query, string $date): void
    {
        $query->where('effective_from', '<=', $date)
            ->where(fn (Builder $inner) => $inner->whereNull('effective_to')->orWhere('effective_to', '>=', $date));
    }
}
