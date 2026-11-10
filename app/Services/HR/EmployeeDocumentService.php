<?php

namespace App\Services\HR;

use App\Enums\EmployeeDocumentType;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Stores employee documents on the private disk under a random name, never the uploaded file name.
 */
class EmployeeDocumentService
{
    public function store(
        Employee $employee,
        UploadedFile $file,
        EmployeeDocumentType $type,
        string $title,
        ?string $expiresAt,
        User $actor,
    ): EmployeeDocument {
        $path = $file->storeAs(
            "employees/{$employee->id}",
            Str::uuid()->toString().'.'.strtolower($file->guessExtension() ?? $file->getClientOriginalExtension()),
            EmployeeDocument::DISK,
        );

        try {
            return $employee->documents()->create([
                'document_type' => $type,
                'title' => $title,
                'file_path' => $path,
                'original_name' => Str::limit(basename($file->getClientOriginalName()), 250, ''),
                'mime_type' => (string) $file->getMimeType(),
                'size' => (int) $file->getSize(),
                'expires_at' => $expiresAt,
                'uploaded_by' => $actor->id,
            ]);
        } catch (Throwable $exception) {
            Storage::disk(EmployeeDocument::DISK)->delete($path);

            throw $exception;
        }
    }

    public function delete(EmployeeDocument $document): void
    {
        DB::transaction(fn () => $document->delete());

        Storage::disk(EmployeeDocument::DISK)->delete($document->file_path);
    }
}
