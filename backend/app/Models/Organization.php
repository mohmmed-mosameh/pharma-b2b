<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'type',
        'address',
        'phone',
        'logo',
        'is_verified',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_verified' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Users belonging to this organization.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * RFQs created by this organization (when acting as a pharmacy).
     */
    public function rfqs(): HasMany
    {
        return $this->hasMany(Rfq::class, 'pharmacy_id');
    }

    /**
     * Quotes submitted by this organization (when acting as a supplier).
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class, 'supplier_id');
    }

    /**
     * Purchase orders awarded to this organization (when acting as a supplier).
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'supplier_id');
    }

    /**
     * Products listed by this organization (when acting as a supplier).
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'supplier_id');
    }
}
