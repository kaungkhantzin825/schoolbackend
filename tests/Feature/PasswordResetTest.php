<?php

namespace Tests\Feature;

use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Password reset flow.
 *
 * Deliberately does NOT use RefreshDatabase: this suite runs against the
 * development database, and wiping it to test a password reset would be a
 * spectacularly bad trade. Each test creates and removes its own account.
 */
class PasswordResetTest extends TestCase
{
    private string $email;

    protected function setUp(): void
    {
        parent::setUp();

        $this->email = 'pwreset-' . uniqid() . '@example.test';

        User::create([
            'name' => 'Password Reset Test',
            'email' => $this->email,
            'password' => Hash::make('original-password'),
            'role' => 'verifier',
        ]);
    }

    protected function tearDown(): void
    {
        User::where('email', $this->email)->delete();

        // The broker leaves a hashed token row behind; remove it too so the
        // suite does not slowly litter the development database.
        DB::table('password_reset_tokens')->where('email', $this->email)->delete();

        parent::tearDown();
    }

    public function test_unknown_email_gets_the_same_answer_and_no_mail(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/forgot-password', [
            'email' => 'nobody-' . uniqid() . '@example.test',
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'If an account exists for that email, a reset link is on its way.']);

        Mail::assertNothingSent();
    }

    public function test_known_email_receives_a_reset_link(): void
    {
        Mail::fake();

        $this->postJson('/api/forgot-password', ['email' => $this->email])
            ->assertOk()
            ->assertJson(['message' => 'If an account exists for that email, a reset link is on its way.']);

        Mail::assertSent(ResetPasswordMail::class, function (ResetPasswordMail $mail) {
            $html = $mail->render();

            return $mail->hasTo($this->email)
                && $mail->token !== ''
                && str_contains($html, '/reset-password?token=')
                && str_contains($html, urlencode($this->email));
        });
    }

    public function test_valid_token_changes_the_password_and_revokes_sessions(): void
    {
        $user = User::where('email', $this->email)->firstOrFail();
        $user->createToken('existing-session');

        $token = Password::broker()->createToken($user);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $this->email,
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('brand-new-password', $user->password));
        $this->assertSame(0, $user->tokens()->count(), 'Existing sessions must be revoked.');

        // The new password is the one that works now.
        $this->postJson('/api/login', [
            'email' => $this->email,
            'password' => 'brand-new-password',
        ])->assertOk()->assertJsonStructure(['token', 'user']);

        $this->postJson('/api/login', [
            'email' => $this->email,
            'password' => 'original-password',
        ])->assertStatus(422);
    }

    public function test_token_is_single_use(): void
    {
        $user = User::where('email', $this->email)->firstOrFail();
        $token = Password::broker()->createToken($user);

        $payload = [
            'token' => $token,
            'email' => $this->email,
            'password' => 'first-new-password',
            'password_confirmation' => 'first-new-password',
        ];

        $this->postJson('/api/reset-password', $payload)->assertOk();
        $this->postJson('/api/reset-password', $payload)->assertStatus(422);
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->postJson('/api/reset-password', [
            'token' => 'not-a-real-token',
            'email' => $this->email,
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertStatus(422)
            ->assertJson(['message' => 'This reset link is invalid or has expired. Please request a new one.']);
    }

    public function test_short_password_is_rejected(): void
    {
        $user = User::where('email', $this->email)->firstOrFail();
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $this->email,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_mismatched_confirmation_is_rejected(): void
    {
        $user = User::where('email', $this->email)->firstOrFail();
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $this->email,
            'password' => 'brand-new-password',
            'password_confirmation' => 'something-else',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }
}
