<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorAuthDisableTest extends TestCase
{
  use RefreshDatabase;

  private function createUserWithTwoFactorEnabled(): User
  {
    $user = User::factory()->create();
    $secret = $user->getGoogle2FASecret();
    $user->two_factor_enabled = true;
    $user->save();
    $user->generateRecoveryCodes();

    return $user->refresh();
  }

  public function test_disable_is_rejected_without_password_or_code(): void
  {
    $user = $this->createUserWithTwoFactorEnabled();

    $response = $this->actingAs($user)->postJson('/two-factor/disable');

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['password', 'code']);

    $this->assertTrue($user->refresh()->two_factor_enabled);
  }

  public function test_disable_is_rejected_with_incorrect_password(): void
  {
    $user = $this->createUserWithTwoFactorEnabled();

    $response = $this->actingAs($user)->postJson('/two-factor/disable', [
      'password' => 'wrong-password',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['password']);

    $this->assertTrue($user->refresh()->two_factor_enabled);
  }

  public function test_disable_is_rejected_with_invalid_code(): void
  {
    $user = $this->createUserWithTwoFactorEnabled();

    $response = $this->actingAs($user)->postJson('/two-factor/disable', [
      'code' => '000000',
    ]);

    $response->assertStatus(422);

    $this->assertTrue($user->refresh()->two_factor_enabled);
  }

  public function test_disable_succeeds_with_correct_password(): void
  {
    $user = $this->createUserWithTwoFactorEnabled();

    $response = $this->actingAs($user)->post('/two-factor/disable', [
      'password' => 'password',
    ]);

    $response->assertStatus(200);

    $user->refresh();
    $this->assertFalse($user->two_factor_enabled);
    $this->assertNull($user->google2fa_secret);
    $this->assertNull($user->recovery_codes);
  }

  public function test_disable_succeeds_with_valid_totp_code(): void
  {
    $user = $this->createUserWithTwoFactorEnabled();

    $google2fa = app('pragmarx.google2fa');
    $validCode = $google2fa->getCurrentOtp($user->google2fa_secret);

    $response = $this->actingAs($user)->post('/two-factor/disable', [
      'code' => $validCode,
    ]);

    $response->assertStatus(200);

    $user->refresh();
    $this->assertFalse($user->two_factor_enabled);
  }

  public function test_disable_succeeds_with_valid_recovery_code(): void
  {
    $user = $this->createUserWithTwoFactorEnabled();

    $recoveryCode = $user->getRecoveryCodes()[0]['code'];

    $response = $this->actingAs($user)->post('/two-factor/disable', [
      'code' => $recoveryCode,
    ]);

    $response->assertStatus(200);

    $user->refresh();
    $this->assertFalse($user->two_factor_enabled);
  }

  public function test_guests_cannot_disable_two_factor_authentication(): void
  {
    $response = $this->post('/two-factor/disable', [
      'password' => 'password',
    ]);

    $response->assertRedirect('/login');
  }
}
