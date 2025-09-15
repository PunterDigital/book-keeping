<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_name',
        'contact_name',
        'email',
        'phone',
        'address',
        'city',
        'postal_code',
        'country',
        'vat_id',
        'company_id',
        'notes',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->postal_code,
            $this->country
        ]);
        
        return implode(', ', $parts);
    }

    public function getTotalRevenueAttribute(): float
    {
        return $this->invoices()->sum('total');
    }

    public function getUnpaidAmountAttribute(): float
    {
        return $this->invoices()
            ->whereIn('status', ['draft', 'sent', 'overdue'])
            ->sum('total');
    }
}