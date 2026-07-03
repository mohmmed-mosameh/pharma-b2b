<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rfq extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'pharmacy_id',
        'created_by',
        'status',
        'quotes_deadline_at',
        'opening_at',
        'score_weight_price',
        'score_weight_delivery',
        'score_weight_rating',
        'score_weight_verified',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'quotes_deadline_at' => 'datetime',
        'opening_at'         => 'datetime',
        'score_weight_price'    => 'integer',
        'score_weight_delivery' => 'integer',
        'score_weight_rating'   => 'integer',
        'score_weight_verified' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * The pharmacy organization that created this RFQ.
     */
    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'pharmacy_id');
    }

    /**
     * The user who created this RFQ.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Line items requested in this RFQ.
     */
    public function rfqItems(): HasMany
    {
        return $this->hasMany(RfqItem::class);
    }

    /**
     * Quotes submitted against this RFQ.
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /**
     * Purchase orders generated from this RFQ.
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
