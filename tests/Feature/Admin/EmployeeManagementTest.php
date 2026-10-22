<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

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

        $this->post(route('admin.login.store'), [
            'email' => 'admin@pinetocalcio.test',
            'password' => 'CorrectAdminPassword123!',
        ]);

        $this->post(route('admin.employees.store'), [
            'name' => 'Mario Rossi',
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

        $this->get(route('admin.employees.index', ['search' => 'Mario', 'type' => 'athlete']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Employees/Index')
                ->has('employees.data', 1)
                ->where('employees.data.0.name', 'Mario Rossi'));

        $employee->update(['must_change_password' => false]);

        $this->post(route('admin.employees.update', $employee), [
            '_method' => 'PUT',
            'name' => 'Mario Rossi',
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
        Storage::disk('local')->assertExists($employee->fresh()->employeeProfile->work_contract_path);
        $this->get(route('admin.employees.contract', $employee))->assertOk();

        $this->delete(route('admin.employees.destroy', $employee))
            ->assertRedirect(route('admin.employees.index'));

        $this->assertDatabaseMissing('users', ['id' => $employee->id]);
    }
}
