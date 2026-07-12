<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use App\Services\MailSettings;
use App\Services\RegistrationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PublicUserAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_disabled_by_default(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('Регистрация отключена');

        $this->post('/register', [
            'name' => 'player',
            'email' => 'player@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertForbidden();
    }

    public function test_user_can_register_without_email_verification(): void
    {
        app(RegistrationSettings::class)->update(true, false);

        $this->post('/register', [
            'name' => 'Player_One',
            'email' => 'PLAYER@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertRedirect(route('account'));

        $user = User::query()->firstOrFail();
        $this->assertSame('player_one', $user->name);
        $this->assertSame('player@example.com', $user->email);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_receives_verification_notification_when_required(): void
    {
        Notification::fake();
        $this->configureReadyMail();
        app(RegistrationSettings::class)->update(true, true);

        $this->post('/register', [
            'name' => 'player_two',
            'email' => 'player2@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertRedirect(route('verification.notice'));

        $user = User::query()->firstOrFail();
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmailNotification::class);

        $this->actingAs($user)->get('/account')->assertRedirect(route('verification.notice'));
    }

    public function test_user_can_verify_email_with_signed_link(): void
    {
        app(RegistrationSettings::class)->update(true, true);
        $user = User::query()->create([
            'name' => 'player',
            'email' => 'player@example.com',
            'password' => Hash::make('Password123'),
        ]);

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)->get($url)->assertRedirect(route('account'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_user_can_login_with_login_or_email_and_logout(): void
    {
        $user = User::query()->create([
            'name' => 'player',
            'email' => 'player@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('Password123'),
        ]);

        $this->post('/login', [
            'login' => 'PLAYER',
            'password' => 'Password123',
        ])->assertRedirect(route('account'));
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect(route('home'));
        $this->assertGuest();

        $this->post('/login', [
            'login' => 'PLAYER@example.com',
            'password' => 'Password123',
        ])->assertRedirect(route('account'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_password_reset_notification_can_be_requested_without_revealing_unknown_email(): void
    {
        Notification::fake();
        $this->configureReadyMail();
        $user = User::query()->create([
            'name' => 'player',
            'email' => 'player@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('Password123'),
        ]);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPasswordNotification::class);

        $this->post('/forgot-password', ['email' => 'unknown@example.com'])
            ->assertSessionHas('status');
    }

    private function configureReadyMail(): void
    {
        $mail = app(MailSettings::class);
        $mail->update([
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => '',
            'password' => null,
            'from_address' => 'no-reply@example.com',
            'from_name' => 'L2 Test',
            'admin_email' => 'admin@example.com',
        ]);
        $mail->markTested();
    }
}
