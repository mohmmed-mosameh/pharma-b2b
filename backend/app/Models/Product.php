<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'supplier_id',
        'name',
        'generic_name',
        'company',
        'category',
        'form',
        'strength',
        'image',
        'price',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * The supplier organization that owns/listed this product.
     * Nullable: may be null for shared/admin-seeded catalog entries.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'supplier_id');
    }

    /**
     * RFQ line items referencing this product.
     */
    public function rfqItems(): HasMany
    {
        return $this->hasMany(RfqItem::class);
    }

    /**
     * Quote line items referencing this product.
     */
    public function quoteItems(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }
}
