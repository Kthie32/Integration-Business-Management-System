<?php

namespace App\Http\Requests\HR;

use App\Enums\EmployeeDocumentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Employee documents: PDF, JPG, PNG or DOCX up to 5 MB (FR-EMP-04). Both the extension and the detected
 * content type must match.
 */
class StoreEmployeeDocumentRequest extends FormRequest
{
    public const MAX_KILOBYTES = 5120;

    public function authorize(): bool
    {
        return $this->user()->can('manageDocuments', $this->route('employee'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'document_type' => ['required', Rule::enum(EmployeeDocumentType::class)],
            'title' => ['required', 'string', 'max:150'],
            'file' => ['required', 'file', 'extensions:pdf,jpg,jpeg,png,docx', 'mimes:pdf,jpg,jpeg,png,docx', 'max:'.self::MAX_KILOBYTES],
            'expires_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.extensions' => 'Upload a PDF, JPG, PNG or Word (.docx) file.',
            'file.mimes' => 'Upload a PDF, JPG, PNG or Word (.docx) file.',
            'file.max' => 'The file may not be larger than 5 MB.',
        ];
    }
}
