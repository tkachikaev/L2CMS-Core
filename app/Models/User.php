<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'game_account', 'locale'];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'locale' => 'string',
            'password' => 'hashed',
        ];
    }

    /** @return HasMany<UserGameAccount, $this> */
    public function gameAccounts(): HasMany
    {
        return $this->hasMany(UserGameAccount::class);
    }

    /** @return HasMany<UserGameAccount, $this> */
    public function gameAccountsCountingTowardLimit(): HasMany
    {
        return $this->gameAccounts();
    }

    /** @return HasMany<UserGameAccount, $this> */
    public function availableGameAccounts(): HasMany
    {
        return $this->gameAccounts()
            ->whereNotNull('registration_game_server_id')
            ->whereHas('registrationGameServer');
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification((string) $token));
    }
}
