<?php

namespace Tests\Feature\Services\System;

use App\Enums\DocumentType;
use App\Enums\NumberingReset;
use App\Exceptions\BusinessRuleException;
use App\Models\AuditLog;
use App\Services\System\NumberingService;
use App\Services\System\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class NumberingServiceTest extends TestCase
{
    use RefreshDatabase;

    private NumberingService $numbering;

    protected function setUp(): void
    {
        parent::setUp();

        $this->numbering = app(NumberingService::class);
    }

    public function test_issues_sequential_numbers_with_the_default_yearly_format(): void
    {
        $date = CarbonImmutable::parse('2026-11-10');

        $numbers = DB::transaction(fn (): array => [
            $this->numbering->next(DocumentType::Invoice, $date),
            $this->numbering->next(DocumentType::Invoice, $date),
            $this->numbering->next(DocumentType::Invoice, $date),
        ]);

        $this->assertSame(['INV-2026-00001', 'INV-2026-00002', 'INV-2026-00003'], $numbers);
    }

    public function test_each_document_type_has_its_own_sequence(): void
    {
        $date = CarbonImmutable::parse('2026-11-10');

        DB::transaction(fn () => $this->numbering->next(DocumentType::Invoice, $date));
        $quotation = DB::transaction(fn () => $this->numbering->next(DocumentType::Quotation, $date));

        $this->assertSame('QT-2026-00001', $quotation);
    }

    public function test_yearly_sequence_restarts_in_a_new_year(): void
    {
        DB::transaction(fn () => $this->numbering->next(DocumentType::Receipt, CarbonImmutable::parse('2026-12-31')));
        DB::transaction(fn () => $this->numbering->next(DocumentType::Receipt, CarbonImmutable::parse('2026-12-31')));

        $first2027 = DB::transaction(fn () => $this->numbering->next(DocumentType::Receipt, CarbonImmutable::parse('2027-01-01')));

        $this->assertSame('RCT-2027-00001', $first2027);
    }

    public function test_applies_configured_prefix_padding_and_reset_period(): void
    {
        $settings = app(SettingsService::class);
        $settings->update('numbering', [
            DocumentType::Expense->value => ['prefix' => 'EX', 'padding' => 3, 'reset' => NumberingReset::Monthly->value],
            DocumentType::Requisition->value => ['prefix' => 'RQ', 'padding' => 4, 'reset' => NumberingReset::Never->value],
        ]);

        $november = DB::transaction(fn () => $this->numbering->next(DocumentType::Expense, CarbonImmutable::parse('2026-11-30')));
        $december = DB::transaction(fn () => $this->numbering->next(DocumentType::Expense, CarbonImmutable::parse('2026-12-01')));
        DB::transaction(fn () => $this->numbering->next(DocumentType::Requisition, CarbonImmutable::parse('2026-12-31')));
        $continuous = DB::transaction(fn () => $this->numbering->next(DocumentType::Requisition, CarbonImmutable::parse('2027-01-01')));

        $this->assertSame('EX-2026-11-001', $november);
        $this->assertSame('EX-2026-12-001', $december);
        $this->assertSame('RQ-0002', $continuous);
    }

    public function test_refuses_to_issue_numbers_outside_a_transaction(): void
    {
        $this->expectException(LogicException::class);

        // RefreshDatabase wraps each test in a transaction, so commit-level zero is simulated by committing it first.
        while (DB::transactionLevel() > 0) {
            DB::commit();
        }

        try {
            $this->numbering->next(DocumentType::Invoice);
        } finally {
            DB::beginTransaction();
        }
    }

    public function test_a_rolled_back_document_releases_its_number(): void
    {
        $date = CarbonImmutable::parse('2026-11-10');

        try {
            DB::transaction(function () use ($date): void {
                $this->numbering->next(DocumentType::Payment, $date);

                throw new \RuntimeException('Saving the payment failed.');
            });
        } catch (\RuntimeException) {
            // Expected: the payment was not saved.
        }

        $this->assertSame('PMT-2026-00001', DB::transaction(fn () => $this->numbering->next(DocumentType::Payment, $date)));
    }

    public function test_preview_shows_the_next_number_without_reserving_it(): void
    {
        $date = CarbonImmutable::parse('2026-11-10');

        $this->assertSame('GRN-2026-00001', $this->numbering->preview(DocumentType::GoodsReceipt, $date));
        $this->assertSame('GRN-2026-00001', $this->numbering->preview(DocumentType::GoodsReceipt, $date));
        $this->assertSame('GRN-2026-00001', DB::transaction(fn () => $this->numbering->next(DocumentType::GoodsReceipt, $date)));
    }

    public function test_next_number_can_move_forward_and_is_audited(): void
    {
        $date = CarbonImmutable::parse('2026-11-10');

        $this->numbering->setNextNumber(DocumentType::Invoice, 500, $date);

        $this->assertSame('INV-2026-00500', DB::transaction(fn () => $this->numbering->next(DocumentType::Invoice, $date)));
        $this->assertTrue(AuditLog::query()->where('event', 'numbering_changed')->exists());
    }

    public function test_next_number_cannot_move_backwards(): void
    {
        $date = CarbonImmutable::parse('2026-11-10');
        DB::transaction(fn () => $this->numbering->next(DocumentType::Invoice, $date));
        DB::transaction(fn () => $this->numbering->next(DocumentType::Invoice, $date));

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('cannot be lower than 3');

        $this->numbering->setNextNumber(DocumentType::Invoice, 2, $date);
    }
}
