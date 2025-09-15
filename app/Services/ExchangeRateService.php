<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ExchangeRateService
{
    const CNB_BASE_URL = 'https://www.cnb.cz/en/financial-markets/foreign-exchange-market/central-bank-exchange-rate-fixing/central-bank-exchange-rate-fixing/selected.txt';
    const CNB_PUBLISH_TIME = '14:30'; // 2:30 PM when CNB publishes new rates
    const CACHE_KEY_PREFIX = 'exchange_rate_';
    const CACHE_DURATION = 3600; // 1 hour

    /**
     * Get current exchange rate from one currency to another
     *
     * @param string $from Source currency (CZK, EUR, USD, etc.)
     * @param string $to Target currency (CZK, EUR, USD, etc.)
     * @return float Exchange rate
     */
    public function getCurrentRate(string $from, string $to): float
    {
        // If same currency, rate is 1
        if ($from === $to) {
            return 1.0;
        }

        $cacheKey = self::CACHE_KEY_PREFIX . $from . '_' . $to;

        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($from, $to) {
            try {
                // If same currency, rate is 1
                if ($from === $to) {
                    return 1.0;
                }

                // For CNB, we can only get rates TO CZK from other currencies
                // or FROM CZK to other currencies
                if ($from === 'CZK') {
                    // Converting from CZK to another currency
                    $rate = $this->fetchCnbRate($to);
                    return $rate > 0 ? 1.0 / $rate : 1.0;
                } elseif ($to === 'CZK') {
                    // Converting to CZK from another currency
                    return $this->fetchCnbRate($from);
                } else {
                    // Converting between two non-CZK currencies
                    $fromRate = $this->fetchCnbRate($from);
                    $toRate = $this->fetchCnbRate($to);

                    if ($fromRate > 0 && $toRate > 0) {
                        return $fromRate / $toRate;
                    }
                }

                Log::warning("Exchange rate not found for {$from} to {$to}, using default rate 1.0");
                return 1.0;

            } catch (\Exception $e) {
                Log::error("Failed to fetch exchange rate for {$from} to {$to}: " . $e->getMessage());
                return 1.0;
            }
        });
    }

    /**
     * Fetch exchange rate for a specific currency from CNB
     *
     * @param string $currency Currency code (EUR, USD, etc.)
     * @return float Exchange rate to CZK
     * @throws \Exception
     */
    private function fetchCnbRate(string $currency): float
    {
        $cacheKey = 'cnb_rate_' . $currency . '_' . $this->getEffectiveRateDate()->format('Y-m-d');

        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($currency) {
            $effectiveDate = $this->getEffectiveRateDate();

            // Build URL for specific currency
            $url = self::CNB_BASE_URL . '?' . http_build_query([
                'from' => $effectiveDate->format('d.m.Y'),
                'to' => $effectiveDate->format('d.m.Y'),
                'currency' => $currency,
                'format' => 'txt'
            ]);

            $response = Http::timeout(10)->get($url);

            if (!$response->successful()) {
                throw new \Exception("Failed to fetch CNB rate for {$currency}: HTTP " . $response->status());
            }

            return $this->parseCnbResponse($response->body(), $currency);
        });
    }

    /**
     * Get the effective date for exchange rates based on CNB publishing time
     *
     * @return Carbon
     */
    private function getEffectiveRateDate(): Carbon
    {
        $now = now()->setTimezone('Europe/Prague');
        $publishTime = $now->copy()->setTimeFromTimeString(self::CNB_PUBLISH_TIME);

        // If current time is before 2:30 PM, use previous business day's rate
        if ($now->lt($publishTime)) {
            $effectiveDate = $now->subDay();
        } else {
            $effectiveDate = $now;
        }

        // Skip weekends - CNB doesn't publish rates on Saturday/Sunday
        // Go back to the most recent business day (Friday or earlier)
        while ($effectiveDate->isWeekend()) {
            $effectiveDate->subDay();
        }

        return $effectiveDate;
    }

    /**
     * Parse CNB response format
     *
     * @param string $content Response content from CNB
     * @param string $currency Currency code
     * @return float Exchange rate
     */
    private function parseCnbResponse(string $content, string $currency): float
    {
        $lines = explode("\n", trim($content));

        // Expected format:
        // Currency: EUR|Amount: 1
        // Date|Rate
        // 01.09.2025|24.440

        if (count($lines) < 3) {
            Log::warning("Invalid CNB response format for {$currency}");
            return 1.0;
        }

        // Parse the currency header to get amount
        $headerLine = $lines[0];
        preg_match('/Amount:\s*(\d+)/', $headerLine, $matches);
        $amount = isset($matches[1]) ? (float)$matches[1] : 1;

        // Get the most recent rate (last line with data)
        for ($i = count($lines) - 1; $i >= 2; $i--) {
            $line = trim($lines[$i]);
            if (empty($line)) continue;

            $parts = explode('|', $line);
            if (count($parts) === 2) {
                $rate = (float)str_replace(',', '.', $parts[1]);

                // CNB gives rate for the specified amount, normalize to 1 unit
                return $amount > 0 ? $rate / $amount : $rate;
            }
        }

        Log::warning("No valid rate found in CNB response for {$currency}");
        return 1.0;
    }

    /**
     * Get supported currencies from CNB
     *
     * @return array Array of supported currency codes
     */
    public function getSupportedCurrencies(): array
    {
        // CNB supports these major currencies - we could fetch dynamically but this is more reliable
        $supportedCurrencies = [
            'CZK', // Base currency
            'EUR', // Euro
            'USD', // US Dollar
            'GBP', // British Pound
            'CHF', // Swiss Franc
            'JPY', // Japanese Yen
            'CAD', // Canadian Dollar
            'AUD', // Australian Dollar
            'PLN', // Polish Zloty
            'HUF', // Hungarian Forint
        ];

        return $supportedCurrencies;
    }

    /**
     * Convert amount from one currency to another
     *
     * @param float $amount Amount to convert
     * @param string $from Source currency
     * @param string $to Target currency
     * @return float Converted amount
     */
    public function convert(float $amount, string $from, string $to): float
    {
        $rate = $this->getCurrentRate($from, $to);
        return round($amount * $rate, 2);
    }

    /**
     * Clear exchange rate cache
     */
    public function clearCache(): void
    {
        Cache::flush(); // This clears all cache, but for simplicity we use this
        // In production, you might want to be more selective
    }
}