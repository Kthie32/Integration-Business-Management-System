<?php

namespace App\Services\Parties;

use App\Enums\DocumentType;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\User;
use App\Services\System\NumberingService;
use Illuminate\Support\Facades\DB;

/**
 * Creates, edits and removes customers. Codes come from the numbering sequence ("CUS-0001").
 */
class CustomerService
{
    public function __construct(private NumberingService $numbering) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Customer
    {
        return DB::transaction(function () use ($data, $actor): Customer {
            $customer = new Customer($data);
            $customer->forceFill([
                'code' => $this->numbering->next(DocumentType::Customer),
                'created_by' => $actor->id,
            ])->save();

            return $customer;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($data);

        return $customer;
    }

    /**
     * Customers with transactions keep their history and can only be deactivated (FR-CUS-03).
     */
    public function delete(Customer $customer): void
    {
        if ($customer->hasTransactions()) {
            throw new BusinessRuleException('This customer has transactions and cannot be deleted. Deactivate it instead.');
        }

        $customer->delete();
    }
}
