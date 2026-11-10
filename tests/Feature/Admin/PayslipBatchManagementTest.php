<?php

namespace Tests\Feature\Admin;

use App\Models\DocumentBatch;
use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use TCPDF;
use Tests\TestCase;

class PayslipBatchManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'admin.email' => 'admin@pinetocalcio.test',
            'admin.password' => 'CorrectAdminPassword123!',
        ]);
        Storage::fake('local');
        Process::fake([
            '*' => Process::result("Codice fiscale RSSMRA90A01A000A\fCodice fiscale VRDLGI88A01A000B\f"),
        ]);
    }

    public function test_admin_can_review_and_publish_recognized_payslips_from_a_pdf_batch(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $employee->employeeProfile()->create([
            'type' => 'athlete',
            'tax_code' => 'RSSMRA90A01A000A',
            'phone' => '3331234567',
        ]);

        $this->post(route('admin.login.store'), [
            'email' => 'admin@pinetocalcio.test',
            'password' => 'CorrectAdminPassword123!',
        ]);

        $this->get(route('admin.payslips.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Payslips/Upload'));

        $this->post(route('admin.payslips.store'), [
            'file' => UploadedFile::fake()->createWithContent('buste.pdf', $this->payslipPdf()),
            'period_year' => 2026,
            'period_month' => 10,
        ])->assertRedirect();

        $batch = DocumentBatch::firstOrFail();

        $this->assertSame('review', $batch->status);
        $this->assertCount(2, $batch->metadata['segments']);
        $this->assertSame('RSSMRA90A01A000A', $batch->metadata['segments'][0]['tax_code']);
        $this->assertSame($employee->employeeProfile->id, $batch->metadata['segments'][0]['employee_profile_id']);
        $this->assertNull($batch->metadata['segments'][1]['employee_profile_id']);

        $this->get(route('admin.payslips.show', $batch))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Payslips/Review'));

        $this->patch(route('admin.payslips.update', $batch), [
            'assignments' => [
                0 => $employee->employeeProfile->id,
                1 => $employee->employeeProfile->id,
            ],
        ])->assertRedirect(route('admin.payslips.show', $batch));

        $this->post(route('admin.payslips.publish', $batch))
            ->assertRedirect(route('admin.payslips.index'));

        $this->assertDatabaseHas('employee_documents', [
            'employee_profile_id' => $employee->employeeProfile->id,
            'document_batch_id' => $batch->id,
            'document_type' => 'payslip',
            'tax_code' => 'RSSMRA90A01A000A',
            'period_year' => 2026,
            'period_month' => 10,
        ]);
        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertSame(2, $batch->fresh()->documents_count);
        $document = EmployeeDocument::firstOrFail();
        Storage::disk('local')->assertExists($document->storage_path);
        $this->assertNull($document->viewed_at);

        $this->actingAs($employee)
            ->get(route('documents.show', $document))
            ->assertOk();

        $this->assertNotNull($document->fresh()->viewed_at);
        $this->get(route('admin.payslips.documents.show', $document))->assertOk();
        $this->get(route('admin.payslips.show', $batch))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Payslips/Review')
                ->has('deliveries.data', 2));
    }

    public function test_admin_paginates_payslip_batches_and_delivery_lists(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $profile = $employee->employeeProfile()->create([
            'type' => 'employee',
            'tax_code' => 'RSSMRA90A01A000A',
            'phone' => '3331234567',
        ]);
        $batch = DocumentBatch::create([
            'original_filename' => 'buste-ottobre.pdf',
            'storage_path' => 'payslips/batches/buste-ottobre.pdf',
            'document_type' => 'payslip',
            'period_year' => 2026,
            'period_month' => 10,
            'status' => 'completed',
        ]);

        foreach (range(1, 15) as $index) {
            DocumentBatch::create([
                'original_filename' => 'buste-'.$index.'.pdf',
                'storage_path' => 'payslips/batches/buste-'.$index.'.pdf',
                'document_type' => 'payslip',
                'status' => 'completed',
                'period_year' => 2026,
                'period_month' => 10,
            ]);
        }

        foreach (range(1, 26) as $page) {
            EmployeeDocument::create([
                'employee_profile_id' => $profile->id,
                'document_batch_id' => $batch->id,
                'document_type' => 'payslip',
                'title' => 'Busta paga - Ottobre 2026',
                'tax_code' => $profile->tax_code,
                'period_year' => 2026,
                'period_month' => 10,
                'storage_path' => 'documents/'.$profile->id.'/busta-'.$page.'.pdf',
                'original_filename' => 'busta-'.$page.'.pdf',
                'first_page' => $page,
                'last_page' => $page,
            ]);
        }

        $this->post(route('admin.login.store'), [
            'email' => 'admin@pinetocalcio.test',
            'password' => 'CorrectAdminPassword123!',
        ]);

        $this->get(route('admin.payslips.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('batches.data', 10));

        $this->get(route('admin.payslips.index', [
            'period_year' => 2000,
            'period_month' => 1,
        ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('batches.data', 0)
                ->where('filters.period_year', '2000')
                ->where('filters.period_month', '1'));

        $this->get(route('admin.payslips.show', $batch))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('deliveries.data', 10));
    }

    public function test_admin_can_delete_an_unviewed_payslip_batch_and_its_files(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $profile = $employee->employeeProfile()->create([
            'type' => 'employee',
            'tax_code' => 'RSSMRA90A01A000A',
            'phone' => '3331234567',
        ]);
        $batch = DocumentBatch::create([
            'original_filename' => 'buste-ottobre.pdf',
            'storage_path' => 'payslips/batches/buste-ottobre.pdf',
            'document_type' => 'payslip',
            'period_year' => 2026,
            'period_month' => 10,
            'status' => 'completed',
        ]);
        $document = EmployeeDocument::create([
            'employee_profile_id' => $profile->id,
            'document_batch_id' => $batch->id,
            'document_type' => 'payslip',
            'title' => 'Busta paga - 10/2026',
            'tax_code' => $profile->tax_code,
            'period_year' => 2026,
            'period_month' => 10,
            'storage_path' => 'documents/'.$profile->id.'/busta-ottobre.pdf',
            'original_filename' => 'busta-ottobre.pdf',
            'first_page' => 1,
            'last_page' => 1,
        ]);
        Storage::disk('local')->put($batch->storage_path, 'source');
        Storage::disk('local')->put($document->storage_path, 'document');

        $this->post(route('admin.login.store'), [
            'email' => 'admin@pinetocalcio.test',
            'password' => 'CorrectAdminPassword123!',
        ]);

        $this->delete(route('admin.payslips.destroy', $batch))
            ->assertRedirect(route('admin.payslips.index'));

        $this->assertDatabaseMissing('document_batches', ['id' => $batch->id]);
        $this->assertDatabaseMissing('employee_documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing($batch->storage_path);
        Storage::disk('local')->assertMissing($document->storage_path);
    }

    public function test_admin_cannot_delete_a_payslip_batch_after_a_document_is_viewed(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $profile = $employee->employeeProfile()->create([
            'type' => 'employee',
            'tax_code' => 'RSSMRA90A01A000A',
            'phone' => '3331234567',
        ]);
        $batch = DocumentBatch::create([
            'original_filename' => 'buste-ottobre.pdf',
            'storage_path' => 'payslips/batches/buste-ottobre.pdf',
            'document_type' => 'payslip',
            'period_year' => 2026,
            'period_month' => 10,
            'status' => 'completed',
        ]);
        $document = EmployeeDocument::create([
            'employee_profile_id' => $profile->id,
            'document_batch_id' => $batch->id,
            'document_type' => 'payslip',
            'title' => 'Busta paga - 10/2026',
            'tax_code' => $profile->tax_code,
            'period_year' => 2026,
            'period_month' => 10,
            'storage_path' => 'documents/'.$profile->id.'/busta-ottobre.pdf',
            'original_filename' => 'busta-ottobre.pdf',
            'first_page' => 1,
            'last_page' => 1,
            'viewed_at' => now(),
        ]);

        $this->post(route('admin.login.store'), [
            'email' => 'admin@pinetocalcio.test',
            'password' => 'CorrectAdminPassword123!',
        ]);

        $this->delete(route('admin.payslips.destroy', $batch))
            ->assertStatus(409);

        $this->assertDatabaseHas('document_batches', ['id' => $batch->id]);
        $this->assertDatabaseHas('employee_documents', ['id' => $document->id]);
    }

    public function test_publishing_a_correction_hides_the_previous_payslip_from_the_employee(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $profile = $employee->employeeProfile()->create([
            'type' => 'employee',
            'tax_code' => 'RSSMRA90A01A000A',
            'phone' => '3331234567',
        ]);
        $originalBatch = DocumentBatch::create([
            'original_filename' => 'buste-ottobre-originale.pdf',
            'storage_path' => 'payslips/batches/buste-ottobre-originale.pdf',
            'document_type' => 'payslip',
            'period_year' => 2026,
            'period_month' => 10,
            'status' => 'completed',
        ]);
        $originalDocument = EmployeeDocument::create([
            'employee_profile_id' => $profile->id,
            'document_batch_id' => $originalBatch->id,
            'document_type' => 'payslip',
            'title' => 'Busta paga - 10/2026',
            'tax_code' => $profile->tax_code,
            'period_year' => 2026,
            'period_month' => 10,
            'storage_path' => 'documents/'.$profile->id.'/busta-ottobre-originale.pdf',
            'original_filename' => 'busta-ottobre-originale.pdf',
            'first_page' => 1,
            'last_page' => 1,
        ]);
        $correctionBatch = DocumentBatch::create([
            'original_filename' => 'buste-ottobre-rettifica.pdf',
            'storage_path' => 'payslips/batches/buste-ottobre-rettifica.pdf',
            'document_type' => 'payslip',
            'period_year' => 2026,
            'period_month' => 10,
            'status' => 'review',
            'metadata' => ['segments' => [[
                'tax_code' => $profile->tax_code,
                'employee_profile_id' => $profile->id,
                'first_page' => 1,
                'last_page' => 1,
                'issue' => null,
            ]]],
        ]);
        Storage::disk('local')->put($correctionBatch->storage_path, $this->payslipPdf());

        $this->post(route('admin.login.store'), [
            'email' => 'admin@pinetocalcio.test',
            'password' => 'CorrectAdminPassword123!',
        ]);

        $this->post(route('admin.payslips.publish', $correctionBatch))
            ->assertRedirect(route('admin.payslips.index'));

        $replacement = EmployeeDocument::query()
            ->where('document_batch_id', $correctionBatch->id)
            ->sole();

        $this->assertSame(1, $correctionBatch->fresh()->revision_number);
        $this->assertFalse($originalDocument->fresh()->is_visible_to_employee);
        $this->assertSame($replacement->id, $originalDocument->fresh()->replaced_by_document_id);
        $this->assertTrue($replacement->is_visible_to_employee);
        $this->assertStringContainsString('Rettifica n. 1', $replacement->title);

        $this->actingAs($employee)
            ->get(route('documents.index'))
            ->assertInertia(fn ($page) => $page->has('documents.data', 1));
        $this->actingAs($employee)
            ->get(route('documents.show', $originalDocument))
            ->assertNotFound();
    }

    private function payslipPdf(): string
    {
        $pdf = new TCPDF();
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Write(0, 'Busta paga - Codice fiscale RSSMRA90A01A000A');
        $pdf->AddPage();
        $pdf->Write(0, 'Busta paga - Codice fiscale VRDLGI88A01A000B');

        return $pdf->Output('', 'S');
    }
}
