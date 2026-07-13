<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class GameServerTranslation extends Model
{
    protected $fillable = ['locale', 'name'];

    public function gameServer(): BelongsTo
    {
        return $this->belongsTo(GameServer::class);
    }
}
