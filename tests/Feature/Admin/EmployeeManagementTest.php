<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Notifications\EmployeeCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_name_migration_splits_first_name_from_the_full_surname(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Mario',
            'last_name' => 'De Santis',
        ]);
        $migration = require database_path('migrations/2026_10_28_000001_split_user_names.php');

        $migration->down();
        DB::table('users')->where('id', $user->id)->update(['name' => 'Mario De Santis']);
        $migration->up();

        $user->refresh();
        $this->assertSame('Mario', $user->first_name);
        $this->assertSame('De Santis', $user->last_name);
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'admin.email' => 'admin@pinetocalcio.test',
            'admin.password' => 'CorrectAdminPassword123!',
        ]);
    }

    public function test_admin_routes_require_an_authenticated_admin_session(): void
    {
        $this->get(route('admin.employees.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_credentials_are_sent_only_when_admin_requests_them(): void
    {
        Notification::fake();
        $employee = User::factory()->create([
            'role' => 'employee',
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'password' => 'Alb-casa-42!',
            'temporary_password' => 'Alb-casa-42!',
            'must_change_password' => true,
        ]);
        $previousPasswordHash = $employee->password;

        $this->post(route('admin.login.store'), [
            'email' => 'admin@pinetocalcio.test',
            'password' => 'CorrectAdminPassword123!',
        ]);

        $this->post(route('admin.employees.send-credentials', $employee))
            ->assertRedirect(route('admin.employees.index'));

        $employee->refresh();
        $this->assertTrue($employee->must_change_password);
        $this->assertSame($previousPasswordHash, $employee->password);
        $this->assertFalse(Hash::check('password', $employee->password));
        Notification::assertSentTo($employee, EmployeeCredentials::class, function ($notification, $channels) use ($employee) {
            return in_array('mail', $channels, true)
                && $notification->temporaryPassword === 'Alb-casa-42!'
                && Hash::check($notification->temporaryPassword, $employee->password)
                && $employee->temporary_password === $notification->temporaryPassword;
        });
        $this->assertNotSame('Alb-casa-42!', DB::table('users')->where('id', $employee->id)->value('temporary_password'));
        $this->assertArrayNotHasKey('temporary_password', $employee->toArray());
    }

    public function test_credentials_email_has_no_english_action_footer(): void
    {
        $employee = User::factory()->create(['first_name' => 'Mario']);
        $mail = (new EmployeeCredentials('Alb-casa-42!'))->toMail($employee);
        $html = $mail->render();

        $this->assertStringContainsString('Alb-casa-42!', $html);
        $this->assertStringContainsString('Accedi al portale', $html);
        $this->assertStringContainsString('Non rispondere', $html);
        $this->assertStringNotContainsString('having trouble clicking', $html);
        $this->assertStringNotContainsString('copy and paste', $html);
    }

    public function test_missing_temporary_password_does_not_replace_password_or_send_email(): void
    {
        Notification::fake();
        $employee = User::factory()->create(['must_change_password' => true]);
        $previousPasswordHash = $employee->password;
        $this->post(route('admin.login.store'), [
            'email' => 'admin@pinetocalcio.test',
            'password' => 'CorrectAdminPassword123!',
        ]);

        $this->post(route('admin.employees.send-credentials', $employee))
            ->assertSessionHasErrors('credentials');

        $this->assertSame($previousPasswordHash, $employee->fresh()->password);
        Notification::assertNothingSent();
    }

    public function test_employee_list_is_ordered_by_last_name_then_first_name(): void
    {
        User::factory()->create(['role' => 'employee', 'first_name' => 'Zoe', 'last_name' => 'Alpha']);
        User::factory()->create(['role' => 'employee', 'first_name' => 'Anna', 'last_name' => 'Alpha']);
        User::factory()->create(['role' => 'employee', 'first_name' => 'Mario', 'last_name' => 'Beta']);

        $this->post(route('admin.login.store'), [
            'email' => 'admin@pinetocalcio.test',
            'password' => 'CorrectAdminPassword123!',
        ]);

        $this->get(route('admin.employees.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('employees.data.0.display_name', 'Alpha Anna')
                ->where('employees.data.1.display_name', 'Alpha Zoe')
                ->where('employees.data.2.display_name', 'Beta Mario'));
    }

    public function test_admin_and_user_sessions_use_different_cookies(): void
    {
        $userSessionCookie = config('session.cookie');
        $user = User::factory()->create(['role' => 'employee']);

        $this->get(route('login'))
            ->assertOk()
            ->assertCookie($userSessionCookie);

        $this->post(route('admin.login.store'), [
            'email' => 'admin@pinetocalcio.test',
            'password' => 'CorrectAdminPassword123!',
        ])
            ->assertRedirect(route('admin.employees.index'))
            ->assertCookie(config('admin.session_cookie'));

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertRedirect(route('dashboard'))
            ->assertCookie($userSessionCookie);
    }

    public function test_admin_can_sign_in_without_a_database_admin_user(): void
    {
        $this->assertDatabaseMissing('users', ['email' => 'admin@pinetocalcio.test']);

        $this->post(route('admin.login.store'), [
            'email' => 'admin@pinetocalcio.test',
            'password' => 'CorrectAdminPassword123!',
        ])->assertRedirect(route('admin.employees.index'));

        $this->get(route('admin.employees.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Employees/Index'));
    }

    public function test_admin_can_create_update_filter_and_delete_an_athlete(): void
    {
        Storage::fake('local');
        Notification::fake();

        $this->post(route('admin.login.store'), [
            'email' => 'admin@pinetocalcio.test',
            'password' => 'CorrectAdminPassword123!',
        ]);

        $this->post(route('admin.employees.store'), [
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'email' => 'mario.rossi@pinetocalcio.test',
            'password' => 'EmployeePassword123!',
            'type' => 'athlete',
            'tax_code' => 'RSSMRA90A01A000A',
            'phone' => '3331234567',
            'pec_email' => 'mario.rossi@pec.pinetocalcio.test',
            'birth_date' => '1990-01-01',
            'birth_place' => 'Pescara',
            'address' => 'Via Roma 1',
            'city' => 'Pineto',
            'province' => 'TE',
            'postal_code' => '64025',
            'position' => 'Centrocampista',
            'emergency_contact_name' => 'Luisa Rossi',
            'emergency_contact_phone' => '3337654321',
            'started_on' => '2026-07-01',
            'notes' => 'Tesseramento in corso.',
            'work_contract' => UploadedFile::fake()->create('contratto.pdf', 100, 'application/pdf'),
        ])->assertRedirect(route('admin.employees.index'));

        $employee = User::where('email', 'mario.rossi@pinetocalcio.test')->firstOrFail();

        $this->assertDatabaseHas('employee_profiles', [
            'user_id' => $employee->id,
            'type' => 'athlete',
            'tax_code' => 'RSSMRA90A01A000A',
        ]);
        $this->assertTrue($employee->must_change_password);
        $this->assertSame('EmployeePassword123!', $employee->temporary_password);
        $this->assertSame('Mario Rossi', $employee->full_name);
        Notification::assertNothingSent();

        $this->get(route('admin.employees.index', ['search' => 'Mario Rossi', 'type' => 'athlete']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Employees/Index')
                ->has('employees.data', 1)
                ->where('employees.data.0.full_name', 'Mario Rossi')
                ->where('employees.data.0.display_name', 'Rossi Mario')
                ->where('employees.data.0.first_name', 'Mario')
                ->where('employees.data.0.last_name', 'Rossi'));

        $employee->update(['must_change_password' => false]);

        $this->post(route('admin.employees.update', $employee), [
            '_method' => 'PUT',
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'email' => 'mario.rossi@pinetocalcio.test',
            'password' => 'NewTemporaryPassword456!',
            'type' => 'athlete',
            'tax_code' => 'RSSMRA90A01A000A',
            'pec_email' => 'mario.rossi@pec.pinetocalcio.test',
            'birth_date' => '1990-01-01',
            'birth_place' => 'Pescara',
            'address' => 'Via Roma 1',
            'city' => 'Pineto',
            'province' => 'TE',
            'postal_code' => '64025',
            'phone' => '3337654321',
            'position' => 'Capitano',
            'emergency_contact_name' => 'Luisa Rossi',
            'emergency_contact_phone' => '3337654321',
            'started_on' => '2026-07-01',
            'notes' => 'Tesseramento rinnovato.',
            'work_contract' => UploadedFile::fake()->create('contratto-aggiornato.pdf', 100, 'application/pdf'),
        ])->assertRedirect(route('admin.employees.index'));

        $this->assertDatabaseHas('employee_profiles', [
            'user_id' => $employee->id,
            'phone' => '3337654321',
            'position' => 'Capitano',
            'pec_email' => 'mario.rossi@pec.pinetocalcio.test',
            'birth_date' => '1990-01-01',
            'birth_place' => 'Pescara',
            'address' => 'Via Roma 1',
            'city' => 'Pineto',
            'province' => 'TE',
            'postal_code' => '64025',
            'emergency_contact_name' => 'Luisa Rossi',
            'emergency_contact_phone' => '3337654321',
            'notes' => 'Tesseramento rinnovato.',
        ]);
        $this->assertTrue($employee->fresh()->must_change_password);
        $this->assertSame('NewTemporaryPassword456!', $employee->fresh()->temporary_password);
        Notification::assertNothingSent();
        $this->post(route('admin.employees.send-credentials', $employee))
            ->assertRedirect(route('admin.employees.index'));
        Notification::assertSentTo($employee, EmployeeCredentials::class, function ($notification) use ($employee) {
            return $notification->temporaryPassword === 'NewTemporaryPassword456!'
                && Hash::check($notification->temporaryPassword, $employee->fresh()->password);
        });
        Storage::disk('local')->assertExists($employee->fresh()->employeeProfile->work_contract_path);
        $this->get(route('admin.employees.contract', $employee))->assertOk();

        $this->delete(route('admin.employees.destroy', $employee))
            ->assertRedirect(route('admin.employees.index'));

        $this->assertDatabaseMissing('users', ['id' => $employee->id]);
    }
}
