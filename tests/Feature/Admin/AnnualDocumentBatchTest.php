<?php

namespace Tests\Feature\Admin;

use App\Models\DocumentBatch;
use App\Models\EmployeeDocument;
use App\Models\User;
use App\Notifications\PayslipPublished;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use TCPDF;
use Tests\TestCase;

class AnnualDocumentBatchTest extends TestCase
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
        $this->post(route('admin.login.store'), [
            'email' => 'admin@pinetocalcio.test',
            'password' => 'CorrectAdminPassword123!',
        ]);
    }

    public function test_thirteenth_is_separate_from_december_and_has_its_own_corrections_and_notification(): void
    {
        Notification::fake();
        $employee = $this->employee('RSSMRA90A01A000A');
        $december = $this->document($employee, 'payslip', 12);

        $this->post(route('admin.payslips.store'), [
            'file' => UploadedFile::fake()->createWithContent('tredicesima.pdf', $this->pdf()),
            'period_year' => 2026,
            'period_month' => 13,
        ])->assertRedirect();
        $batch = DocumentBatch::where('document_type', 'thirteenth')->sole();
        $this->assertNull($batch->period_month);
        $this->post(route('admin.payslips.publish', $batch))
            ->assertRedirect(route('admin.payslips.index'));
        $thirteenth = $batch->documents()->sole();
        $this->assertSame('Tredicesima - 2026', $thirteenth->title);
        $this->assertTrue($december->fresh()->is_visible_to_employee);
        $this->assertSame(0, $batch->fresh()->revision_number);

        $this->get(route('admin.payslips.index', ['period_month' => 13]))
            ->assertInertia(fn ($page) => $page->has('batches.data', 1)->where('batches.data.0.document_type', 'thirteenth'));
        $this->postJson(route('admin.payslips.send-notification', $batch))
            ->assertOk()->assertJson(['sent_count' => 1, 'failed' => []]);
        Notification::assertSentTo($employee, PayslipPublished::class, function ($notification) use ($employee) {
            $mail = $notification->toMail($employee);

            return $notification->documentType === 'thirteenth'
                && $notification->month === null
                && str_contains($mail->subject, 'tredicesima')
                && str_contains($mail->introLines[0], '2026');
        });

        $this->post(route('admin.payslips.store'), [
            'file' => UploadedFile::fake()->createWithContent('tredicesima-rettifica.pdf', $this->pdf()),
            'period_year' => 2026,
            'period_month' => 13,
        ])->assertRedirect();
        $correction = DocumentBatch::where('document_type', 'thirteenth')->whereKeyNot($batch->id)->sole();
        $this->post(route('admin.payslips.publish', $correction))->assertRedirect();
        $this->assertSame(1, $correction->fresh()->revision_number);
        $this->assertFalse($thirteenth->fresh()->is_visible_to_employee);
        $this->assertTrue($december->fresh()->is_visible_to_employee);
        $this->actingAs($employee)->get(route('documents.index', ['type' => 'payslip']))
            ->assertInertia(fn ($page) => $page->has('documents.data', 2));
    }

    public function test_cud_batch_is_separate_and_replaces_only_previous_cud_for_same_income_year(): void
    {
        Notification::fake();
        $employee = $this->employee('RSSMRA90A01A000A');
        $other = $this->employee('VRDLGI88A01A000B');
        $payslip = $this->document($employee, 'payslip', 12);
        $thirteenth = $this->document($employee, 'thirteenth', null);
        $legacyCud = $this->document($employee, 'cud', null);
        $olderCud = $this->document($employee, 'cud', null);
        $olderCud->update(['period_year' => 2025, 'storage_path' => 'documents/older-cud.pdf']);

        $this->get(route('admin.cuds.create'))
            ->assertInertia(fn ($page) => $page->component('Admin/Payslips/Upload')->where('area', 'cuds'));
        $this->post(route('admin.cuds.store'), [
            'file' => UploadedFile::fake()->createWithContent('cu.pdf', $this->pdf()),
            'period_year' => 2026,
        ])->assertRedirect();
        $batch = DocumentBatch::where('document_type', 'cud')->sole();
        $this->assertNull($batch->period_month);
        $this->assertCount(2, $batch->metadata['segments']);
        $this->get(route('admin.payslips.show', $batch))->assertNotFound();
        $this->post(route('admin.payslips.publish', $batch))->assertNotFound();
        $this->post(route('admin.cuds.publish', $batch))
            ->assertRedirect(route('admin.cuds.index'));
        $this->assertSame(1, $batch->fresh()->revision_number);
        $this->assertFalse($legacyCud->fresh()->is_visible_to_employee);
        $this->assertTrue($payslip->fresh()->is_visible_to_employee);
        $this->assertTrue($thirteenth->fresh()->is_visible_to_employee);
        $this->assertTrue($olderCud->fresh()->is_visible_to_employee);
        $cud = $batch->documents()->where('employee_profile_id', $employee->employeeProfile->id)->sole();
        $this->assertSame('Certificazione Unica - redditi 2026 - Rettifica n. 1', $cud->title);
        $this->get(route('admin.payslips.documents.show', $cud))->assertNotFound();
        $this->get(route('admin.cuds.documents.show', $cud))->assertOk();
        $this->postJson(route('admin.cuds.send-notification', $batch))
            ->assertOk()->assertJson(['sent_count' => 2, 'failed' => []]);
        foreach ([$employee, $other] as $recipient) {
            Notification::assertSentTo($recipient, PayslipPublished::class, function ($notification) use ($recipient) {
                $mail = $notification->toMail($recipient);

                return $notification->documentType === 'cud'
                    && $notification->month === null
                    && str_contains($mail->subject, 'Certificazione Unica')
                    && str_contains($mail->introLines[0], 'redditi 2026');
            });
        }

        $this->post(route('admin.cuds.store'), [
            'file' => UploadedFile::fake()->createWithContent('cu-rettifica.pdf', $this->pdf()),
            'period_year' => 2026,
        ])->assertRedirect();
        $correction = DocumentBatch::where('document_type', 'cud')->whereKeyNot($batch->id)->sole();
        $this->post(route('admin.cuds.publish', $correction))->assertRedirect(route('admin.cuds.index'));
        $this->assertSame(2, $correction->fresh()->revision_number);
        $this->assertFalse($cud->fresh()->is_visible_to_employee);
        $this->assertTrue($payslip->fresh()->is_visible_to_employee);
        $this->assertTrue($thirteenth->fresh()->is_visible_to_employee);
        $this->assertTrue($olderCud->fresh()->is_visible_to_employee);
        $this->postJson(route('admin.cuds.send-notification', $correction))
            ->assertOk()->assertJson(['sent_count' => 2, 'failed' => []]);
        Notification::assertSentToTimes($employee, PayslipPublished::class, 2);
        $this->delete(route('admin.cuds.destroy', $correction))->assertRedirect(route('admin.cuds.index'));
        $this->assertTrue($cud->fresh()->is_visible_to_employee);
        $this->assertNotNull($cud->fresh()->notification_sent_at);
        $this->assertFalse($legacyCud->fresh()->is_visible_to_employee);

        $this->actingAs($employee)->get(route('documents.index', ['type' => 'cud']))
            ->assertInertia(fn ($page) => $page->has('documents.data', 2)->where('documents.data.0.id', $cud->id));
        $this->actingAs($other)->get(route('documents.show', $cud))->assertNotFound();
        $this->delete(route('admin.cuds.destroy', $batch))->assertRedirect(route('admin.cuds.index'));
        $this->assertTrue($legacyCud->fresh()->is_visible_to_employee);
        $this->assertNull($legacyCud->fresh()->replaced_by_document_id);
        $this->assertTrue($payslip->fresh()->is_visible_to_employee);
        $this->assertTrue($thirteenth->fresh()->is_visible_to_employee);
    }

    public function test_cud_requires_an_income_year_and_rejects_a_month(): void
    {
        $this->post(route('admin.cuds.store'), [
            'file' => UploadedFile::fake()->createWithContent('cu.pdf', $this->pdf()),
            'period_month' => 12,
        ])->assertSessionHasErrors(['period_year', 'period_month']);
        $this->post(route('admin.payslips.store'), [
            'file' => UploadedFile::fake()->createWithContent('buste.pdf', $this->pdf()),
            'period_year' => 2026,
            'period_month' => 14,
        ])->assertSessionHasErrors('period_month');
    }

    private function employee(string $taxCode): User
    {
        $user = User::factory()->create(['role' => 'employee']);
        $user->employeeProfile()->create([
            'type' => 'employee',
            'tax_code' => $taxCode,
            'phone' => '3331234567',
        ]);

        return $user;
    }

    private function document(User $employee, string $type, ?int $month): EmployeeDocument
    {
        return EmployeeDocument::create([
            'employee_profile_id' => $employee->employeeProfile->id,
            'document_type' => $type,
            'title' => 'Documento precedente',
            'tax_code' => $employee->employeeProfile->tax_code,
            'period_year' => 2026,
            'period_month' => $month,
            'storage_path' => 'documents/'.$employee->id.'/'.$type.'.pdf',
            'original_filename' => $type.'.pdf',
        ]);
    }

    private function pdf(): string
    {
        $pdf = new TCPDF;
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Write(0, 'Prima pagina');
        $pdf->AddPage();
        $pdf->Write(0, 'Seconda pagina');

        return $pdf->Output('', 'S');
    }
}
