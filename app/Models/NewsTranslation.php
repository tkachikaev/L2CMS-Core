<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class NewsTranslation extends Model
{
    protected $fillable = [
        'news_id',
        'locale',
        'title',
        'slug',
        'excerpt',
        'body',
    ];

    public function news(): BelongsTo
    {
        return $this->belongsTo(News::class);
    }
}
