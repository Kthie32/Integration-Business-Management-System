<?php

namespace App\Services\Settings;

use App\Exceptions\BusinessRuleException;
use App\Models\TaxRate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates and edits tax rates, keeping exactly one active default rate.
 */
class TaxRateService
{
    /**
     * @param  array{name: string, code: string, rate: string, is_active?: bool, is_default?: bool}  $data
     */
    public function create(array $data): TaxRate
    {
        return DB::transaction(function () use ($data): TaxRate {
            $taxRate = new TaxRate($this->attributes($data));
            $taxRate->save();

            $this->applyDefault($taxRate, (bool) ($data['is_default'] ?? false));

            return $taxRate;
        });
    }

    /**
     * @param  array{name: string, code: string, rate: string, is_active?: bool, is_default?: bool}  $data
     */
    public function update(TaxRate $taxRate, array $data): TaxRate
    {
        $attributes = $this->attributes($data);
        $makeDefault = (bool) ($data['is_default'] ?? false);

        if ($taxRate->is_default && ! $makeDefault) {
            throw new BusinessRuleException('Choose another default tax rate first; there must always be one default.');
        }

        if ($taxRate->is_default && ! $attributes['is_active']) {
            throw new BusinessRuleException('The default tax rate cannot be deactivated.');
        }

        return DB::transaction(function () use ($taxRate, $attributes, $makeDefault): TaxRate {
            $taxRate->update($attributes);
            $this->applyDefault($taxRate, $makeDefault);

            return $taxRate;
        });
    }

    /**
     * Make the rate the only default when asked to, or when no other default exists yet.
     */
    private function applyDefault(TaxRate $taxRate, bool $makeDefault): void
    {
        $hasOtherDefault = TaxRate::query()->whereKeyNot($taxRate->id)->where('is_default', true)->lockForUpdate()->exists();

        if ($taxRate->is_default || (! $makeDefault && $hasOtherDefault)) {
            return;
        }

        if (! $taxRate->is_active) {
            if ($makeDefault) {
                throw new BusinessRuleException('An inactive tax rate cannot be the default.');
            }

            return;
        }

        TaxRate::query()->whereKeyNot($taxRate->id)->where('is_default', true)->get()
            ->each(fn (TaxRate $other) => $other->forceFill(['is_default' => false])->save());

        $taxRate->forceFill(['is_default' => true])->save();
    }

    /**
     * @param  array{name: string, code: string, rate: string, is_active?: bool}  $data
     * @return array{name: string, code: string, rate: string, is_active: bool}
     */
    private function attributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'code' => Str::upper($data['code']),
            'rate' => $data['rate'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }
}
