<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The next number for one document type within one period ("all", a year or a month).
 * Only App\Services\System\NumberingService changes these rows.
 */
#[Fillable(['document_type', 'period', 'next_number'])]
class DocumentSequence extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'next_number' => 'integer',
        ];
    }
}
