<?php

namespace Tests\Fixtures;

use App\Models\Concerns\HasStatusHistory;
use Illuminate\Database\Eloquent\Model;

/**
 * A stand-in document stored in a temporary table, for testing HasStatusHistory before real documents exist.
 *
 * @property FixtureStatus $status
 */
class FixtureDocument extends Model
{
    use HasStatusHistory;

    protected $table = 'fixture_documents';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => FixtureStatus::class];
    }
}
