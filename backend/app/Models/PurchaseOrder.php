<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'rfq_id',
        'quote_id',
        'supplier_id',
        'total_amount',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * The RFQ this purchase order originated from.
     */
    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    /**
     * The quote that was awarded into this purchase order.
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * The supplier organization fulfilling this purchase order.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'supplier_id');
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'pharmacy_id');
    }

    public function rating(): HasOne
    {
        return $this->hasOne(SupplierRating::class);
    }
}
