<?php

namespace App\Services\Sales\Concerns;

use App\Exceptions\BusinessRuleException;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\TaxRate;
use App\Services\Sales\DocumentCalculator;

/**
 * Replaces the lines of a sales document (quotation or invoice) and stores the recalculated totals.
 *
 * The tax rate value used is copied onto each line, so later changes to tax rates never alter a saved
 * document. A line may carry an explicit `tax_rate` value (used when an invoice is converted from a
 * quotation, so the customer is invoiced at the rate they accepted).
 *
 * @property DocumentCalculator $calculator
 */
trait WritesDocumentLines
{
    /**
     * @param  list<array<string, mixed>>  $lines
     */
    protected function writeLines(Quotation|Invoice $document, array $lines): void
    {
        $lines = array_values($lines);

        if ($lines === []) {
            throw new BusinessRuleException('Add at least one line.');
        }

        $taxRates = TaxRate::query()->whereIn('id', array_filter(array_column($lines, 'tax_rate_id')))->get()->keyBy('id');

        $prepared = array_map(function (array $line) use ($taxRates): array {
            $taxRate = filled($line['tax_rate_id'] ?? null) ? $taxRates->get((int) $line['tax_rate_id']) : null;
            $rate = match (true) {
                $taxRate === null => '0',
                array_key_exists('tax_rate', $line) && $line['tax_rate'] !== null => (string) $line['tax_rate'],
                default => (string) $taxRate->rate,
            };

            return [
                'item_id' => filled($line['item_id'] ?? null) ? (int) $line['item_id'] : null,
                'description' => trim((string) $line['description']),
                'quantity' => (string) $line['quantity'],
                'unit' => filled($line['unit'] ?? null) ? (string) $line['unit'] : null,
                'unit_price' => (string) $line['unit_price'],
                'discount_amount' => filled($line['discount_amount'] ?? null) ? (string) $line['discount_amount'] : '0',
                'tax_rate_id' => $taxRate?->id,
                'tax_rate' => $rate,
            ];
        }, $lines);

        $result = $this->calculator->calculate(
            $prepared,
            $document->document_discount_type,
            (string) ($document->document_discount_value ?? '0'),
        );

        $document->items()->delete();

        foreach ($prepared as $index => $line) {
            $document->items()->create([...$line, ...$result['lines'][$index], 'sort_order' => $index]);
        }

        $document->forceFill([
            'subtotal' => $result['subtotal'],
            'discount_total' => $result['discount_total'],
            'document_discount_amount' => $result['document_discount_amount'],
            'tax_total' => $result['tax_total'],
            'total' => $result['total'],
        ])->save();
    }

    /**
     * The editable form of a document's saved lines.
     *
     * @return list<array{item_id: int|null, description: string, quantity: string, unit: string|null, unit_price: string, discount_amount: string, tax_rate_id: int|null}>
     */
    public static function editableLines(Quotation|Invoice $document): array
    {
        return $document->items->map(fn ($line): array => [
            'item_id' => $line->item_id,
            'description' => $line->description,
            'quantity' => (string) $line->quantity,
            'unit' => $line->unit,
            'unit_price' => (string) $line->unit_price,
            'discount_amount' => (string) $line->discount_amount,
            'tax_rate_id' => $line->tax_rate_id,
        ])->values()->all();
    }
}
