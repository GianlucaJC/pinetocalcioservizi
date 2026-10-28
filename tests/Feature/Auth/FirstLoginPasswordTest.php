<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FirstLoginPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_login_requires_the_user_to_change_the_temporary_password(): void
    {
        $user = User::factory()->create([
            'password' => 'TemporaryPassword123!',
            'temporary_password' => 'TemporaryPassword123!',
            'must_change_password' => true,
        ]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'TemporaryPassword123!',
        ])->assertRedirect(route('password.first-login'));

        $this->get(route('profile.edit'))
            ->assertRedirect(route('password.first-login'));

        $this->get(route('password.first-login'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/FirstLoginPassword'));

        $this->put(route('password.first-login.update'), [
            'password' => 'PersonalPassword456!',
            'password_confirmation' => 'PersonalPassword456!',
        ])->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertNull($user->temporary_password);
        $this->assertTrue(Hash::check('PersonalPassword456!', $user->password));
        $this->assertFalse(Hash::check('TemporaryPassword123!', $user->password));
        $this->get(route('profile.edit'))->assertOk();
    }

    public function test_first_login_password_must_differ_from_temporary_password(): void
    {
        $user = User::factory()->create([
            'password' => 'TemporaryPassword123!',
            'must_change_password' => true,
        ]);
        $this->actingAs($user);

        $this->put(route('password.first-login.update'), [
            'password' => 'TemporaryPassword123!',
            'password_confirmation' => 'TemporaryPassword123!',
        ])->assertSessionHasErrors('password');

        $this->assertTrue($user->fresh()->must_change_password);
    }
}
