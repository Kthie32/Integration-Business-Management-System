<?php

namespace App\Services\Finance;

use App\Enums\DocumentType;
use App\Enums\ExpenseStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\System\AuditLogger;
use App\Services\System\NumberingService;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Expenses (FR-EXP-01..03): drafts are created and edited freely, recording numbers and locks them, and a
 * recorded expense can only be voided with a reason. Attachments live on the private disk under a random
 * name and are served only through ExpenseController after an authorization check.
 */
class ExpenseService
{
    public function __construct(private NumberingService $numbering, private AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDraft(array $data, ?UploadedFile $attachment, User $actor): Expense
    {
        $expense = new Expense;
        $expense->forceFill(['status' => ExpenseStatus::Draft, 'created_by' => $actor->id]);

        return $this->save($expense, $data, $attachment, false, function (Expense $expense): void {
            $expense->recordStatusChange(null, ExpenseStatus::Draft);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(Expense $expense, array $data, ?UploadedFile $attachment, bool $removeAttachment): Expense
    {
        $this->assertDraft($expense);

        return $this->save($expense, $data, $attachment, $removeAttachment);
    }

    /**
     * Number and lock a draft.
     */
    public function record(Expense $expense, User $actor): Expense
    {
        return DB::transaction(function () use ($expense, $actor): Expense {
            $expense = Expense::query()->lockForUpdate()->with('category')->findOrFail($expense->id);
            $this->assertDraft($expense);

            if (! $expense->category->is_active) {
                throw new BusinessRuleException("The category \"{$expense->category->name}\" is inactive. Choose another category before recording.");
            }

            if ($expense->expense_date->gt(today())) {
                throw new BusinessRuleException('An expense cannot be recorded with a future date.');
            }

            if ($expense->payment_method === null) {
                throw new BusinessRuleException('Choose how the expense was paid before recording it.');
            }

            if ($expense->payment_method->requiresReference() && blank($expense->reference)) {
                throw new BusinessRuleException("Enter the transaction reference for a {$expense->payment_method->label()} payment before recording.");
            }

            $expense->forceFill([
                'number' => $this->numbering->next(DocumentType::Expense, $expense->expense_date),
                'recorded_at' => now(),
                'recorded_by' => $actor->id,
            ]);
            $expense->transitionTo(ExpenseStatus::Recorded);

            return $expense;
        });
    }

    public function void(Expense $expense, User $actor, string $reason): Expense
    {
        return DB::transaction(function () use ($expense, $actor, $reason): Expense {
            $expense = Expense::query()->lockForUpdate()->findOrFail($expense->id);

            if (! $expense->isRecorded()) {
                throw new BusinessRuleException('Only a recorded expense can be voided.');
            }

            $expense->forceFill([
                'voided_at' => now(),
                'voided_by' => $actor->id,
                'void_reason' => $reason,
            ]);
            $expense->transitionTo(ExpenseStatus::Void, $reason);
            $this->auditLogger->log('voided', $expense, "Voided expense {$expense->number}: {$reason}");

            return $expense;
        });
    }

    /**
     * Drafts never received a number, so they can be deleted outright.
     */
    public function deleteDraft(Expense $expense): void
    {
        $this->assertDraft($expense);

        $path = $expense->attachment_path;

        DB::transaction(function () use ($expense): void {
            $expense->statusHistories()->delete();
            $expense->delete();
        });

        if ($path !== null) {
            Storage::disk(Expense::DISK)->delete($path);
        }
    }

    /**
     * @param  array{name: string, code?: string|null, description?: string|null, is_active?: bool}  $data
     */
    public function saveCategory(ExpenseCategory $category, array $data): ExpenseCategory
    {
        $category->fill($data)->save();

        return $category;
    }

    public function deleteCategory(ExpenseCategory $category): void
    {
        if ($category->expenses()->exists()) {
            throw new BusinessRuleException("The category \"{$category->name}\" has expenses and cannot be deleted. Deactivate it instead.");
        }

        $category->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  (callable(Expense): void)|null  $afterCreate
     */
    private function save(Expense $expense, array $data, ?UploadedFile $attachment, bool $removeAttachment, ?callable $afterCreate = null): Expense
    {
        $amount = Money::of((string) $data['amount']);
        $tax = Money::of((string) ($data['tax_amount'] ?? '0'));

        if (! $amount->isPositive()) {
            throw new BusinessRuleException('The amount must be greater than zero.');
        }

        if ($tax->isNegative() || $tax->greaterThan($amount)) {
            throw new BusinessRuleException('The tax amount must be between zero and the total amount.');
        }

        $category = ExpenseCategory::query()->findOrFail($data['expense_category_id']);

        if (! $category->is_active && (int) $expense->expense_category_id !== $category->id) {
            throw new BusinessRuleException("The category \"{$category->name}\" is inactive.");
        }

        $oldPath = $expense->attachment_path;
        $newPath = $attachment !== null ? $this->storeAttachment($attachment) : null;

        try {
            DB::transaction(function () use ($expense, $data, $amount, $tax, $attachment, $newPath, $removeAttachment, $afterCreate): void {
                $isNew = ! $expense->exists;

                $expense->forceFill([
                    'expense_date' => Carbon::parse($data['expense_date'])->toDateString(),
                    'expense_category_id' => (int) $data['expense_category_id'],
                    'supplier_id' => $data['supplier_id'] ?? null,
                    'department_id' => $data['department_id'] ?? null,
                    'payee_name' => filled($data['payee_name'] ?? null) ? trim((string) $data['payee_name']) : null,
                    'description' => trim((string) $data['description']),
                    'amount' => $amount->toDecimal(),
                    'tax_amount' => $tax->toDecimal(),
                    'payment_method' => filled($data['payment_method'] ?? null) ? PaymentMethod::from($data['payment_method']) : null,
                    'reference' => filled($data['reference'] ?? null) ? trim((string) $data['reference']) : null,
                    'notes' => $data['notes'] ?? null,
                ]);

                if ($newPath !== null && $attachment !== null) {
                    $expense->forceFill([
                        'attachment_path' => $newPath,
                        'attachment_name' => Str::limit(basename($attachment->getClientOriginalName()), 250, ''),
                        'attachment_mime' => (string) $attachment->getMimeType(),
                        'attachment_size' => (int) $attachment->getSize(),
                    ]);
                } elseif ($removeAttachment) {
                    $expense->forceFill(['attachment_path' => null, 'attachment_name' => null, 'attachment_mime' => null, 'attachment_size' => null]);
                }

                $expense->save();

                if ($isNew && $afterCreate !== null) {
                    $afterCreate($expense);
                }
            });
        } catch (Throwable $exception) {
            if ($newPath !== null) {
                Storage::disk(Expense::DISK)->delete($newPath);
            }

            throw $exception;
        }

        if ($oldPath !== null && $oldPath !== $expense->attachment_path) {
            Storage::disk(Expense::DISK)->delete($oldPath);
        }

        return $expense;
    }

    private function storeAttachment(UploadedFile $file): string
    {
        return $file->storeAs(
            'expenses/'.now()->format('Y/m'),
            Str::uuid()->toString().'.'.strtolower($file->guessExtension() ?? $file->getClientOriginalExtension()),
            Expense::DISK,
        );
    }

    private function assertDraft(Expense $expense): void
    {
        if (! $expense->isDraft()) {
            throw new BusinessRuleException("Expense {$expense->displayNumber()} is {$expense->status->label()} and can no longer be changed.");
        }
    }
}
