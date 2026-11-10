<?php

namespace App\Http\Controllers\HR;

use App\Enums\EmployeeDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\HR\StoreEmployeeDocumentRequest;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Services\HR\EmployeeDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Upload, download and removal of employee documents. Files live on the private disk and are only ever
 * served through this controller after an authorization check.
 */
class EmployeeDocumentController extends Controller
{
    public function __construct(private EmployeeDocumentService $documents) {}

    public function store(StoreEmployeeDocumentRequest $request, Employee $employee): RedirectResponse
    {
        $this->documents->store(
            $employee,
            $request->file('file'),
            EmployeeDocumentType::from($request->validated('document_type')),
            $request->validated('title'),
            $request->validated('expires_at'),
            $request->user(),
        );

        return redirect()->to(route('employees.show', $employee).'#documents')->with('success', 'Document uploaded.');
    }

    public function show(Employee $employee, EmployeeDocument $document): StreamedResponse
    {
        Gate::authorize('viewDocuments', $employee);

        $disk = Storage::disk(EmployeeDocument::DISK);
        abort_unless($disk->exists($document->file_path), 404);

        return $disk->download($document->file_path, $document->original_name, [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Employee $employee, EmployeeDocument $document): RedirectResponse
    {
        Gate::authorize('manageDocuments', $employee);

        $this->documents->delete($document);

        return redirect()->to(route('employees.show', $employee).'#documents')->with('success', 'Document removed.');
    }
}
