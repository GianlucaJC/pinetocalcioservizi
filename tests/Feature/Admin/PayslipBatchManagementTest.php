<?php

namespace Tests\Feature\Admin;

use App\Models\DocumentBatch;
use App\Models\EmployeeDocument;
use App\Models\User;
use App\Notifications\PayslipPublished;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
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
        Notification::fake();
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

        Notification::assertNothingSent();
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

    public function test_notifications_are_sent_once_to_each_recipient_of_visible_payslips(): void
    {
        Notification::fake();
        $employees = User::factory()->count(4)->create(['role' => 'employee']);
        foreach ($employees as $index => $employee) {
            $employee->employeeProfile()->create([
                'type' => 'employee',
                'tax_code' => 'TAXCODE'.str_pad((string) $index, 9, '0', STR_PAD_LEFT),
                'phone' => '3331234567',
            ]);
        }
        $batch = DocumentBatch::create([
            'original_filename' => 'buste.pdf',
            'storage_path' => 'payslips/batches/buste.pdf',
            'document_type' => 'payslip',
            'period_year' => 2026,
            'period_month' => 10,
            'status' => 'review',
        ]);
        $createDocument = function (User $employee, bool $visible = true) use ($batch) {
            return EmployeeDocument::create([
                'employee_profile_id' => $employee->employeeProfile->id,
                'document_batch_id' => $batch->id,
                'document_type' => 'payslip',
                'title' => 'Busta paga - 10/2026',
                'tax_code' => $employee->employeeProfile->tax_code,
                'period_year' => 2026,
                'period_month' => 10,
                'storage_path' => 'documents/busta.pdf',
                'original_filename' => 'busta.pdf',
                'first_page' => 1,
                'last_page' => 1,
                'is_visible_to_employee' => $visible,
            ]);
        };
        $first = $createDocument($employees[0]);
        $duplicate = $createDocument($employees[0]);
        $second = $createDocument($employees[1]);
        $replaced = $createDocument($employees[2], false);

        $this->postJson(route('admin.payslips.send-notification', $batch))->assertRedirect(route('admin.login'));
        $this->actingAs($employees[3])
            ->postJson(route('admin.payslips.send-notification', $batch))->assertRedirect(route('admin.login'));
        $this->post(route('admin.login.store'), [
            'email' => 'admin@pinetocalcio.test',
            'password' => 'CorrectAdminPassword123!',
        ]);
        $this->postJson(route('admin.payslips.send-notification', $batch))->assertStatus(409);
        Notification::assertNothingSent();

        $batch->update(['status' => 'completed']);
        $this->get(route('admin.payslips.show', $batch))
            ->assertInertia(fn ($page) => $page
                ->where('pending_notifications_count', 2)
                ->where('sent_notifications_count', 0)
                ->where('has_replaced_documents', true));
        $this->postJson(route('admin.payslips.send-notification', $batch))
            ->assertOk()->assertJson(['sent_count' => 2, 'failed' => []]);
        Notification::assertSentTo($employees[0], PayslipPublished::class, function ($notification, $channels) use ($employees) {
            $mail = $notification->toMail($employees[0]);

            return $channels === ['mail']
                && $notification->month === 10
                && $notification->year === 2026
                && str_contains($mail->introLines[0], '10/2026')
                && $mail->actionUrl === route('documents.index');
        });
        Notification::assertSentToTimes($employees[0], PayslipPublished::class, 1);
        Notification::assertSentToTimes($employees[1], PayslipPublished::class, 1);
        Notification::assertNotSentTo($employees[2], PayslipPublished::class);
        Notification::assertNotSentTo($employees[3], PayslipPublished::class);
        $this->assertNotNull($first->fresh()->notification_sent_at);
        $this->assertNotNull($duplicate->fresh()->notification_sent_at);
        $this->assertNotNull($second->fresh()->notification_sent_at);
        $this->assertNull($replaced->fresh()->notification_sent_at);
        $this->get(route('admin.payslips.show', $batch))
            ->assertInertia(fn ($page) => $page
                ->where('pending_notifications_count', 0)
                ->where('sent_notifications_count', 2)
                ->where('has_replaced_documents', true));
        $this->postJson(route('admin.payslips.send-notification', $batch))
            ->assertOk()->assertJson(['sent_count' => 0, 'failed' => []]);
        Notification::assertSentTimes(PayslipPublished::class, 2);

        $batch->documents()->update(['is_visible_to_employee' => false]);
        $this->get(route('admin.payslips.show', $batch))
            ->assertInertia(fn ($page) => $page
                ->where('pending_notifications_count', 0)
                ->where('sent_notifications_count', 0)
                ->where('has_replaced_documents', true));
    }

    public function test_failed_notifications_remain_pending_and_can_be_retried_without_resending_successes(): void
    {
        $employees = User::factory()->count(2)->create(['role' => 'employee']);
        $batch = DocumentBatch::create([
            'original_filename' => 'buste.pdf',
            'storage_path' => 'payslips/batches/buste.pdf',
            'document_type' => 'payslip',
            'period_year' => 2026,
            'period_month' => 10,
            'status' => 'completed',
        ]);
        foreach ($employees as $index => $employee) {
            $profile = $employee->employeeProfile()->create([
                'type' => 'employee',
                'tax_code' => 'TAXCODE'.str_pad((string) $index, 9, '0', STR_PAD_LEFT),
                'phone' => '3331234567',
            ]);
            EmployeeDocument::create([
                'employee_profile_id' => $profile->id,
                'document_batch_id' => $batch->id,
                'document_type' => 'payslip',
                'title' => 'Busta paga - 10/2026',
                'tax_code' => $profile->tax_code,
                'period_year' => 2026,
                'period_month' => 10,
                'storage_path' => 'documents/busta.pdf',
                'original_filename' => 'busta.pdf',
            ]);
        }
        $this->post(route('admin.login.store'), [
            'email' => 'admin@pinetocalcio.test',
            'password' => 'CorrectAdminPassword123!',
        ]);
        $dispatcher = \Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('send')->once()
            ->with(\Mockery::on(fn (User $user) => $user->id === $employees[0]->id), \Mockery::type(PayslipPublished::class))
            ->andThrow(new \RuntimeException('Mail transport unavailable'));
        $dispatcher->shouldReceive('send')->once()
            ->with(\Mockery::on(fn (User $user) => $user->id === $employees[1]->id), \Mockery::type(PayslipPublished::class));
        $this->app->instance(Dispatcher::class, $dispatcher);

        $this->postJson(route('admin.payslips.send-notification', $batch))
            ->assertOk()->assertJson([
                'sent_count' => 1,
                'failed' => [['name' => $employees[0]->full_name, 'email' => $employees[0]->email]],
            ]);
        $this->get(route('admin.payslips.show', $batch))
            ->assertInertia(fn ($page) => $page
                ->where('pending_notifications_count', 1)
                ->where('sent_notifications_count', 1)
                ->where('has_replaced_documents', false));

        $retryDispatcher = \Mockery::mock(Dispatcher::class);
        $retryDispatcher->shouldReceive('send')->once()
            ->with(\Mockery::on(fn (User $user) => $user->id === $employees[0]->id), \Mockery::type(PayslipPublished::class));
        $this->app->instance(Dispatcher::class, $retryDispatcher);
        $this->postJson(route('admin.payslips.send-notification', $batch))
            ->assertOk()->assertJson(['sent_count' => 1, 'failed' => []]);
        $this->get(route('admin.payslips.show', $batch))
            ->assertInertia(fn ($page) => $page
                ->where('pending_notifications_count', 0)
                ->where('sent_notifications_count', 2)
                ->where('has_replaced_documents', false));
    }

    public function test_notification_rejects_empty_or_non_payslip_batches(): void
    {
        Notification::fake();
        $this->post(route('admin.login.store'), [
            'email' => 'admin@pinetocalcio.test',
            'password' => 'CorrectAdminPassword123!',
        ]);
        $batch = DocumentBatch::create([
            'original_filename' => 'buste.pdf',
            'storage_path' => 'payslips/batches/buste.pdf',
            'document_type' => 'payslip',
            'period_year' => 2026,
            'period_month' => 10,
            'status' => 'completed',
        ]);

        $this->get(route('admin.payslips.show', $batch))
            ->assertInertia(fn ($page) => $page
                ->where('pending_notifications_count', 0)
                ->where('sent_notifications_count', 0)
                ->where('has_replaced_documents', false));
        $this->postJson(route('admin.payslips.send-notification', $batch))->assertStatus(409);
        $batch->update(['document_type' => 'contract']);
        $this->postJson(route('admin.payslips.send-notification', $batch))->assertNotFound();
        Notification::assertNothingSent();
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

    public function test_deleting_corrections_preserves_the_replacement_chain_and_restores_previous_payslips(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $profile = $employee->employeeProfile()->create([
            'type' => 'athlete',
            'tax_code' => 'RSSMRA90A01A000A',
            'phone' => '3331234567',
        ]);
        $createBatch = function (int $revision) {
            return DocumentBatch::create([
                'original_filename' => 'buste-'.$revision.'.pdf',
                'storage_path' => 'payslips/batches/buste-'.$revision.'.pdf',
                'document_type' => 'payslip',
                'period_year' => 2026,
                'period_month' => 10,
                'status' => 'completed',
                'revision_number' => $revision,
            ]);
        };
        $createDocument = function (DocumentBatch $batch, string $suffix) use ($profile) {
            return EmployeeDocument::create([
                'employee_profile_id' => $profile->id,
                'document_batch_id' => $batch->id,
                'document_type' => 'payslip',
                'title' => 'Busta paga - 10/2026',
                'tax_code' => $profile->tax_code,
                'period_year' => 2026,
                'period_month' => 10,
                'storage_path' => 'documents/'.$profile->id.'/busta-'.$suffix.'.pdf',
                'original_filename' => 'busta-'.$suffix.'.pdf',
            ]);
        };
        $originalBatch = $createBatch(0);
        $middleBatch = $createBatch(1);
        $latestBatch = $createBatch(2);
        $original = $createDocument($originalBatch, 'originale');
        $originalAdditional = $createDocument($originalBatch, 'aggiuntiva');
        $middle = $createDocument($middleBatch, 'rettifica-1');
        $latest = $createDocument($latestBatch, 'rettifica-2');
        $original->update([
            'is_visible_to_employee' => false,
            'replaced_by_document_id' => $middle->id,
            'viewed_at' => now(),
            'notification_sent_at' => now(),
        ]);
        $originalAdditional->update([
            'is_visible_to_employee' => false,
            'replaced_by_document_id' => $middle->id,
        ]);
        $middle->update(['is_visible_to_employee' => false, 'replaced_by_document_id' => $latest->id]);
        Storage::disk('local')->put($original->storage_path, 'original');
        Storage::disk('local')->put($originalAdditional->storage_path, 'original additional');

        $this->post(route('admin.login.store'), [
            'email' => 'admin@pinetocalcio.test',
            'password' => 'CorrectAdminPassword123!',
        ]);
        $this->delete(route('admin.payslips.destroy', $middleBatch))
            ->assertRedirect(route('admin.payslips.index'));
        $this->assertFalse($original->fresh()->is_visible_to_employee);
        $this->assertSame($latest->id, $original->fresh()->replaced_by_document_id);
        $this->assertFalse($originalAdditional->fresh()->is_visible_to_employee);
        $this->assertSame($latest->id, $originalAdditional->fresh()->replaced_by_document_id);
        $this->assertTrue($latest->fresh()->is_visible_to_employee);

        $latest->update(['viewed_at' => now()]);
        $this->delete(route('admin.payslips.destroy', $latestBatch))->assertStatus(409);
        $this->assertFalse($original->fresh()->is_visible_to_employee);
        $this->assertSame($latest->id, $original->fresh()->replaced_by_document_id);

        $latest->update(['viewed_at' => null]);
        $this->delete(route('admin.payslips.destroy', $latestBatch))
            ->assertRedirect(route('admin.payslips.index'));
        $this->assertTrue($original->fresh()->is_visible_to_employee);
        $this->assertNull($original->fresh()->replaced_by_document_id);
        $this->assertNotNull($original->fresh()->viewed_at);
        $this->assertNotNull($original->fresh()->notification_sent_at);
        $this->assertTrue($originalAdditional->fresh()->is_visible_to_employee);
        $this->assertNull($originalAdditional->fresh()->replaced_by_document_id);
        Storage::disk('local')->assertExists($original->storage_path);
        Storage::disk('local')->assertExists($originalAdditional->storage_path);
        $this->actingAs($employee)->get(route('documents.index'))
            ->assertInertia(fn ($page) => $page->has('documents.data', 2));
        $this->get(route('documents.show', $original))->assertOk();
        $this->get(route('documents.show', $originalAdditional))->assertOk();
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
        Storage::disk('local')->put($originalDocument->storage_path, 'original');

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

        $this->delete(route('admin.payslips.destroy', $correctionBatch))
            ->assertRedirect(route('admin.payslips.index'));
        $this->assertTrue($originalDocument->fresh()->is_visible_to_employee);
        $this->assertNull($originalDocument->fresh()->replaced_by_document_id);
        $this->get(route('documents.show', $originalDocument))->assertOk();
        $this->get(route('admin.payslips.show', $originalBatch))
            ->assertInertia(fn ($page) => $page->where('pending_notifications_count', 1));
    }

    private function payslipPdf(): string
    {
        $pdf = new TCPDF;
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Write(0, 'Busta paga - Codice fiscale RSSMRA90A01A000A');
        $pdf->AddPage();
        $pdf->Write(0, 'Busta paga - Codice fiscale VRDLGI88A01A000B');

        return $pdf->Output('', 'S');
    }
}
