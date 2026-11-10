<?php

namespace App\Services\Parties;

use App\Enums\DocumentType;
use App\Exceptions\BusinessRuleException;
use App\Models\Supplier;
use App\Models\User;
use App\Services\System\NumberingService;
use Illuminate\Support\Facades\DB;

/**
 * Creates, edits and removes suppliers. Codes come from the numbering sequence ("SUP-0001").
 */
class SupplierService
{
    public function __construct(private NumberingService $numbering) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Supplier
    {
        return DB::transaction(function () use ($data, $actor): Supplier {
            $supplier = new Supplier($data);
            $supplier->forceFill([
                'code' => $this->numbering->next(DocumentType::Supplier),
                'created_by' => $actor->id,
            ])->save();

            return $supplier;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->update($data);

        return $supplier;
    }

    /**
     * Suppliers with transactions keep their history and can only be deactivated (FR-SUP-03).
     */
    public function delete(Supplier $supplier): void
    {
        if ($supplier->hasTransactions()) {
            throw new BusinessRuleException('This supplier has transactions and cannot be deleted. Deactivate it instead.');
        }

        $supplier->delete();
    }
}
