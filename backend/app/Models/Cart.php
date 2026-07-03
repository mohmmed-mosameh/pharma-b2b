<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cart extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'pharmacy_id',
    ];

    /**
     * The pharmacy organization this cart belongs to.
     */
    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'pharmacy_id');
    }

    /**
     * Line items currently in this cart.
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
