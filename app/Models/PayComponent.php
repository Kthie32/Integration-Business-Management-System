<?php

namespace App\Models;

use App\Enums\PayComponentCalculation;
use App\Enums\PayComponentKind;
use App\Models\Concerns\Auditable;
use Database\Factories\PayComponentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An allowance or deduction that can be assigned to salary structures (FR-PRL-01). For allowances the flags say
 * whether the amount is taxable and counts towards NSSF; for deductions they say whether it is taken off before
 * PAYE or NSSF are worked out. A component in use is deactivated, not deleted.
 */
class PayComponent extends Model
{
    /** @use HasFactory<PayComponentFactory> */
    use Auditable, HasFactory;

    /**
     * Attributes are set by PayrollConfigurationService.
     *
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
            'calculation' => PayComponentCalculation::class,
            'default_value' => 'decimal:4',
            'is_taxable' => 'boolean',
            'is_nssf_applicable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<SalaryStructureComponent, $this>
     */
    public function structureComponents(): HasMany
    {
        return $this->hasMany(SalaryStructureComponent::class);
    }

    public function isAllowance(): bool
    {
        return $this->kind === PayComponentKind::Allowance;
    }

    public function isInUse(): bool
    {
        return $this->structureComponents()->exists()
            || PayrollItemLine::query()->where('pay_component_id', $this->id)->exists();
    }

    /**
     * @param  Builder<PayComponent>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
