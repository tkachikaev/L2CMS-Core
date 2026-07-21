<?php

namespace KaevCMS\Modules\PromoCodes\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $promo_code_id
 * @property int $item_id
 * @property int $amount
 * @property int $sort_order
 * @property-read PromoCode $promoCode
 */
final class PromoCodeReward extends Model
{
    protected $table = 'module_promo_code_rewards';

    protected $fillable = ['promo_code_id', 'item_id', 'amount', 'sort_order'];

    protected function casts(): array
    {
        return [
            'promo_code_id' => 'integer',
            'item_id' => 'integer',
            'amount' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<PromoCode, $this> */
    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }
}
