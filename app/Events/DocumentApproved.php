<?php

namespace App\Events;

use App\Models\Contracts\Approvable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired by the approval engine when the last step of a document is approved (docs/03 §3.5).
 */
class DocumentApproved
{
    use Dispatchable;

    public function __construct(public Approvable $document) {}
}
