<?php

namespace Tests\Feature\HR;

use App\Enums\SystemRole;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeeDocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $hrOfficer;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seedRolesAndPermissions();
        $this->hrOfficer = User::factory()->withRole(SystemRole::HumanResourceOfficer)->create();
        $this->employee = Employee::factory()->create();
    }

    public function test_uploads_document_to_private_disk_under_a_random_name(): void
    {
        $this->actingAs($this->hrOfficer)->post(route('employees.documents.store', $this->employee), [
            'document_type' => 'contract',
            'title' => 'Signed contract',
            'file' => UploadedFile::fake()->create('my contract.pdf', 200, 'application/pdf'),
        ])->assertRedirect(route('employees.show', $this->employee).'#documents');

        $document = EmployeeDocument::query()->sole();
        $this->assertSame('my contract.pdf', $document->original_name);
        $this->assertStringStartsWith("employees/{$this->employee->id}/", $document->file_path);
        $this->assertStringNotContainsString('contract', basename($document->file_path));
        Storage::disk('local')->assertExists($document->file_path);
    }

    public function test_rejects_disallowed_file_types(): void
    {
        $this->actingAs($this->hrOfficer)->post(route('employees.documents.store', $this->employee), [
            'document_type' => 'other',
            'title' => 'Script',
            'file' => UploadedFile::fake()->create('payload.php', 10, 'application/x-php'),
        ])->assertSessionHasErrors(['file' => 'Upload a PDF, JPG, PNG or Word (.docx) file.']);

        $this->assertDatabaseCount('employee_documents', 0);
    }

    public function test_rejects_files_over_five_megabytes(): void
    {
        $this->actingAs($this->hrOfficer)->post(route('employees.documents.store', $this->employee), [
            'document_type' => 'cv',
            'title' => 'CV',
            'file' => UploadedFile::fake()->create('cv.pdf', 5121, 'application/pdf'),
        ])->assertSessionHasErrors(['file' => 'The file may not be larger than 5 MB.']);
    }

    public function test_downloads_document_for_authorized_user(): void
    {
        Storage::disk('local')->put('employees/1/abc.pdf', 'pdf-bytes');
        $document = EmployeeDocument::factory()->create(['employee_id' => $this->employee->id, 'file_path' => 'employees/1/abc.pdf', 'original_name' => 'contract.pdf']);

        $this->actingAs($this->hrOfficer)->get(route('employees.documents.show', [$this->employee, $document]))
            ->assertOk()
            ->assertDownload('contract.pdf');
    }

    public function test_user_without_document_permission_cannot_download(): void
    {
        $manager = User::factory()->withRole(SystemRole::Manager)->create();
        $document = EmployeeDocument::factory()->create(['employee_id' => $this->employee->id]);

        $this->actingAs($manager)->get(route('employees.documents.show', [$this->employee, $document]))->assertForbidden();
        $this->actingAs($manager)->get(route('employees.show', $this->employee))->assertOk()->assertDontSee($document->title);
    }

    public function test_document_of_another_employee_is_not_found(): void
    {
        $document = EmployeeDocument::factory()->create();

        $this->actingAs($this->hrOfficer)->get(route('employees.documents.show', [$this->employee, $document]))->assertNotFound();
    }

    public function test_removes_document_and_file(): void
    {
        Storage::disk('local')->put('employees/1/abc.pdf', 'pdf-bytes');
        $document = EmployeeDocument::factory()->create(['employee_id' => $this->employee->id, 'file_path' => 'employees/1/abc.pdf']);

        $this->actingAs($this->hrOfficer)->delete(route('employees.documents.destroy', [$this->employee, $document]))
            ->assertRedirect(route('employees.show', $this->employee).'#documents');

        $this->assertModelMissing($document);
        Storage::disk('local')->assertMissing('employees/1/abc.pdf');
    }
}
