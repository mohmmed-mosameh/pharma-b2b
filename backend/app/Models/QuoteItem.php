<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuoteItem extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'quote_id',
        'product_id',
        'unit_price',
        'discount_percentage',
        'available_qty',
        'delivery_days',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'unit_price' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'available_qty' => 'integer',
        'delivery_days' => 'integer',
    ];

    /**
     * السعر الفعلي بعد تطبيق نسبة الخصم، محسوب وليس مخزّنًا، لتفادي
     * تعارض البيانات لو تغيرت نسبة الخصم لاحقًا.
     */
    public function getNetUnitPriceAttribute(): string
    {
        $discountAmount = bcmul(
            (string) $this->unit_price,
            bcdiv((string) $this->discount_percentage, '100', 4),
            2
        );

        return bcsub((string) $this->unit_price, $discountAmount, 2);
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * The quote this line item belongs to.
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * The product being quoted.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
