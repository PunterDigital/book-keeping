<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Services\ExchangeRateService;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'amount',
        'category_id',
        'description',
        'vat_amount',
        'currency',
        'exchange_rate',
        'receipt_path',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'amount' => 'float',
        'vat_amount' => 'float',
        'exchange_rate' => 'float',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    public function getVatRateAttribute(): float
    {
        if ($this->amount == 0) {
            return 0.0;
        }

        return round(($this->vat_amount / $this->amount) * 100, 1);
    }

    /**
     * Get the amount in CZK (base currency)
     */
    public function getAmountInCzk(): float
    {
        if ($this->currency === 'CZK') {
            return $this->amount;
        }

        $exchangeRateService = new ExchangeRateService();
        return $exchangeRateService->convert($this->amount, $this->currency, 'CZK');
    }

    /**
     * Get formatted currency display
     */
    public function getFormattedAmount(): string
    {
        $symbol = $this->getCurrencySymbol();
        return number_format($this->amount, 2) . ' ' . $symbol;
    }

    /**
     * Get currency symbol for display
     */
    public function getCurrencySymbol(): string
    {
        $symbols = [
            'CZK' => 'Kč',
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
        ];

        return $symbols[$this->currency] ?? $this->currency;
    }

    /**
     * Get exchange rate information for display
     */
    public function getExchangeRateInfo(): ?string
    {
        if ($this->currency === 'CZK' || $this->exchange_rate == 1.0) {
            return null;
        }

        return "Kurz: 1 {$this->currency} = {$this->exchange_rate} CZK";
    }
}