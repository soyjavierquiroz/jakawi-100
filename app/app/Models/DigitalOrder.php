<?php

namespace App\Models;

use App\Support\PrelaunchOperations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DigitalOrder extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(function (self $order): void {
            PrelaunchOperations::assertDigitalAllowed($order);
            $mutable = ['status', 'confirmed_at', 'refunded_at', 'updated_at'];
            if (array_diff(array_keys($order->getDirty()), $mutable)) {
                throw new \DomainException('El precio, producto, comprador y condiciones aceptados son inmutables.');
            }
        });
    }

    protected $hidden = ['file_path', 'file_sha256', 'collection_snapshot'];

    protected function casts(): array
    {
        return ['collection_snapshot' => 'array', 'member_at_purchase' => 'boolean', 'amount' => 'decimal:2', 'public_price' => 'decimal:2', 'expires_at' => 'datetime', 'confirmed_at' => 'datetime', 'refunded_at' => 'datetime'];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(DigitalPayment::class);
    }

    public function access(): HasOne
    {
        return $this->hasOne(DigitalAccessGrant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(DigitalProduct::class, 'digital_product_id');
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }
}
