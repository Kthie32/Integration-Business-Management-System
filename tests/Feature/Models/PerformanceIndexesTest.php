<?php

namespace Tests\Feature\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Indexes added after the Phase 14 volume check (docs/09): lists, reports and the dashboard sort and filter on these.
 */
class PerformanceIndexesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function indexes(): array
    {
        return [
            'invoice date' => ['invoices', ['invoice_date']],
            'invoice issued at' => ['invoices', ['issued_at']],
            'quotation date' => ['quotations', ['quote_date']],
        ];
    }

    /**
     * @param  list<string>  $columns
     */
    #[DataProvider('indexes')]
    public function test_the_column_is_indexed(string $table, array $columns): void
    {
        $indexed = array_map(fn (array $index): array => $index['columns'], Schema::getIndexes($table));

        $this->assertContains($columns, $indexed);
    }
}
