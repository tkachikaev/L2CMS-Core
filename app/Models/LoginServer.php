<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $driver
 * @property string $database_host
 * @property int $database_port
 * @property string $database_name
 * @property string $database_username
 * @property string|null $database_password
 * @property string $database_charset
 */
class LoginServer extends Model
{
    protected $fillable = [
        'name',
        'driver',
        'database_host',
        'database_port',
        'database_name',
        'database_username',
        'database_password',
        'database_charset',
    ];

    protected $hidden = [
        'database_password',
    ];

    protected function casts(): array
    {
        return [
            'database_port' => 'integer',
            'database_password' => 'encrypted',
        ];
    }

    /** @return HasMany<GameServer, $this> */
    public function gameServers(): HasMany
    {
        return $this->hasMany(GameServer::class);
    }

    public function databasePassword(): ?string
    {
        try {
            return is_string($this->database_password) ? $this->database_password : null;
        } catch (DecryptException) {
            return null;
        }
    }

    public function hasDatabasePassword(): bool
    {
        $value = $this->getRawOriginal('database_password');

        return is_string($value) && $value !== '';
    }
}
