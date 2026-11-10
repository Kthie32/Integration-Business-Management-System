<?php

namespace Database\Factories;

use App\Enums\EmployeeDocumentType;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeDocument>
 */
class EmployeeDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'document_type' => EmployeeDocumentType::Contract,
            'title' => 'Employment contract',
            'file_path' => 'employees/'.fake()->uuid().'.pdf',
            'original_name' => 'contract.pdf',
            'mime_type' => 'application/pdf',
            'size' => 2048,
        ];
    }
}
