<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit_price',
        'vat_rate',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'float',
        'vat_rate' => 'float',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function getSubtotalAttribute(): float
    {
        return $this->quantity * $this->unit_price;
    }

    public function getVatAmountAttribute(): float
    {
        return $this->getSubtotalAttribute() * ($this->vat_rate / 100);
    }

    public function getTotalAttribute(): float
    {
        return $this->getSubtotalAttribute() + $this->getVatAmountAttribute();
    }
}