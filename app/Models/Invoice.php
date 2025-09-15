<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\ExchangeRateService;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'client_id',
        'issue_date',
        'due_date',
        'status',
        'subtotal',
        'vat_amount',
        'total',
        'currency',
        'exchange_rate',
        'notes',
        'pdf_path',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'float',
        'vat_amount' => 'float',
        'total' => 'float',
        'exchange_rate' => 'float',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Check if reverse charge applies to this invoice
     */
    public function isReverseChargeApplicable(): bool
    {
        $euCountries = ['AUSTRIA', 'BELGIUM', 'BULGARIA', 'CROATIA', 'CYPRUS', 'DENMARK', 'ESTONIA', 'FINLAND', 'FRANCE', 'GERMANY', 'GREECE', 'HUNGARY', 'IRELAND', 'ITALY', 'LATVIA', 'LITHUANIA', 'LUXEMBOURG', 'MALTA', 'NETHERLANDS', 'POLAND', 'PORTUGAL', 'ROMANIA', 'SLOVAKIA', 'SLOVENIA', 'SPAIN', 'SWEDEN'];
        $clientCountry = strtoupper($this->client->country ?? '');

        return in_array($clientCountry, $euCountries) &&
               !empty($this->client->vat_id) &&
               $this->total > 0;
    }

    /**
     * Get reverse charge statement text
     */
    public function getReverseChargeStatement(): string
    {
        return "Přenesení daňové povinnosti - zákazník je povinen odvést DPH podle §92a zákona o DPH";
    }

    /**
     * Get the total amount in CZK (base currency)
     */
    public function getTotalInCzk(): float
    {
        if ($this->currency === 'CZK') {
            return $this->total;
        }

        $exchangeRateService = new ExchangeRateService();
        return $exchangeRateService->convert($this->total, $this->currency, 'CZK');
    }

    /**
     * Get formatted currency display
     */
    public function getFormattedTotal(): string
    {
        $symbol = $this->getCurrencySymbol();
        return number_format($this->total, 2) . ' ' . $symbol;
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