<?php

namespace App\Services\System;

use App\Enums\DocumentType;
use App\Enums\NumberingReset;
use App\Exceptions\BusinessRuleException;
use App\Models\DocumentSequence;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Issues unique, gap-free document numbers such as "INV-2026-00042".
 *
 * next() must run inside the caller's database transaction: the sequence row is locked with
 * SELECT ... FOR UPDATE, so concurrent requests wait for each other, and a rolled-back document
 * also rolls back its number.
 */
class NumberingService
{
    public function __construct(private SettingsService $settings, private AuditLogger $auditLogger) {}

    /**
     * Reserve and return the next number for a document type.
     */
    public function next(DocumentType $type, ?CarbonInterface $date = null): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Document numbers must be taken inside a database transaction.');
        }

        $date ??= now();
        $config = $this->config($type);
        $sequence = $this->lockSequence($type, $config['reset']->period($date));

        $number = $sequence->next_number;
        $sequence->update(['next_number' => $number + 1]);

        return $this->format($config, $date, $number);
    }

    /**
     * The number the next document of this type will receive, without reserving it.
     */
    public function preview(DocumentType $type, ?CarbonInterface $date = null): string
    {
        $date ??= now();
        $config = $this->config($type);

        return $this->format($config, $date, $this->nextNumber($type, $date));
    }

    /**
     * The next sequential number (without prefix) for the period the date falls in.
     */
    public function nextNumber(DocumentType $type, ?CarbonInterface $date = null): int
    {
        $period = $this->config($type)['reset']->period($date ?? now());

        return (int) (DocumentSequence::query()
            ->where('document_type', $type->value)
            ->where('period', $period)
            ->value('next_number') ?? 1);
    }

    /**
     * Move the current period's counter forward, e.g. to continue numbering from a previous system.
     * Moving it backwards is refused because it would reissue numbers already used.
     */
    public function setNextNumber(DocumentType $type, int $nextNumber, ?CarbonInterface $date = null): void
    {
        $period = $this->config($type)['reset']->period($date ?? now());

        DB::transaction(function () use ($type, $period, $nextNumber): void {
            $sequence = $this->lockSequence($type, $period);

            if ($nextNumber < $sequence->next_number) {
                throw new BusinessRuleException("The next {$type->label()} number cannot be lower than {$sequence->next_number}; lower numbers may already be in use.");
            }

            if ($nextNumber === $sequence->next_number) {
                return;
            }

            $this->auditLogger->log(
                'numbering_changed',
                null,
                "Next {$type->label()} number set for period {$period}",
                ['next_number' => $sequence->next_number],
                ['next_number' => $nextNumber],
            );

            $sequence->update(['next_number' => $nextNumber]);
        });
    }

    /**
     * The numbering configuration for a document type.
     *
     * @return array{prefix: string, padding: int, reset: NumberingReset}
     */
    public function config(DocumentType $type): array
    {
        $config = array_replace($type->defaultNumbering(), (array) ($this->settings->get('numbering', $type->value) ?? []));

        return [
            'prefix' => (string) $config['prefix'],
            'padding' => (int) $config['padding'],
            'reset' => NumberingReset::tryFrom((string) $config['reset']) ?? NumberingReset::Yearly,
        ];
    }

    /**
     * @param  array{prefix: string, padding: int, reset: NumberingReset}  $config
     */
    public function format(array $config, CarbonInterface $date, int $number): string
    {
        $parts = array_filter([
            $config['prefix'],
            $config['reset'] === NumberingReset::Never ? null : $date->format('Y'),
            $config['reset'] === NumberingReset::Monthly ? $date->format('m') : null,
            str_pad((string) $number, $config['padding'], '0', STR_PAD_LEFT),
        ], fn (?string $part): bool => $part !== null && $part !== '');

        return implode('-', $parts);
    }

    /**
     * Fetch the sequence row for a period, creating it first if needed, and lock it until the transaction ends.
     */
    private function lockSequence(DocumentType $type, string $period): DocumentSequence
    {
        DocumentSequence::query()->insertOrIgnore([
            'document_type' => $type->value,
            'period' => $period,
            'next_number' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DocumentSequence::query()
            ->where('document_type', $type->value)
            ->where('period', $period)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
