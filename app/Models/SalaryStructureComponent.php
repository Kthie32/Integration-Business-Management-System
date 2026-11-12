<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pay component assigned to a salary structure with its amount or percentage.
 */
class SalaryStructureComponent extends Model
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
        return ['value' => 'decimal:4'];
    }

    /**
     * @return BelongsTo<SalaryStructure, $this>
     */
    public function salaryStructure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class);
    }

    /**
     * @return BelongsTo<PayComponent, $this>
     */
    public function payComponent(): BelongsTo
    {
        return $this->belongsTo(PayComponent::class);
    }
}
