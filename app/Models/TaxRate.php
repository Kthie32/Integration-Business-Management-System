<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\TaxRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A tax that can be applied to items and document lines, e.g. VAT at 18 %.
 * Documents store the rate value they used, so editing a rate never changes past documents.
 */
#[Fillable(['name', 'code', 'rate', 'is_active'])]
class TaxRate extends Model
{
    /** @use HasFactory<TaxRateFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<TaxRate>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * The rate for display, without trailing zeros: "18 %", "0 %", "7.5 %".
     */
    public function displayRate(): string
    {
        return rtrim(rtrim((string) $this->rate, '0'), '.').' %';
    }
}
