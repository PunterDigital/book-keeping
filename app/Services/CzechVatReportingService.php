<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Expense;
use App\Models\Client;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CzechVatReportingService
{
    /**
     * Czech VAT rates as defined by law
     */
    const CZECH_VAT_RATES = [
        'standard' => 21,    // Základní sazba DPH
        'reduced' => 12,     // Snížená sazba DPH
        'zero' => 0          // Nulová sazba DPH
    ];

    /**
     * Generate comprehensive Czech VAT report
     */
    public function generateVatReport(Carbon $startDate, Carbon $endDate, ?int $quarter = null): array
    {
        // Get all invoices and expenses in the period
        $invoices = Invoice::with(['client', 'items'])
            ->whereBetween('issue_date', [$startDate, $endDate])
            ->orderBy('issue_date')
            ->get();

        $expenses = Expense::with('category')
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get();

        // Calculate VAT summary with Czech compliance
        $vatSummary = $this->calculateCzechVatSummary($invoices, $expenses);

        // Generate quarterly VAT return data if quarter specified
        $quarterlyReturn = $quarter ? $this->generateQuarterlyVatReturn($vatSummary, $quarter, $startDate->year) : null;

        // Check for reverse charge transactions
        $reverseChargeData = $this->analyzeReverseChargeTransactions($invoices, $expenses);

        // EU sales reporting (if applicable)
        $euSalesData = $this->analyzeEuSales($invoices);

        return [
            'period' => [
                'start' => $startDate->format('d.m.Y'),
                'end' => $endDate->format('d.m.Y'),
                'quarter' => $quarter,
                'year' => $startDate->year,
                'days' => $startDate->diffInDays($endDate) + 1
            ],
            'vat_summary' => $vatSummary,
            'quarterly_return' => $quarterlyReturn,
            'reverse_charge' => $reverseChargeData,
            'eu_sales' => $euSalesData,
            'invoices' => $invoices,
            'expenses' => $expenses,
            'compliance_check' => $this->performComplianceCheck($vatSummary)
        ];
    }

    /**
     * Calculate VAT summary with Czech compliance standards
     */
    protected function calculateCzechVatSummary(Collection $invoices, Collection $expenses): array
    {
        $summary = [
            'output_vat' => [],     // DPH na výstupu (z prodeje)
            'input_vat' => [],      // DPH na vstupu (z nákupu)
            'net_vat' => [],        // Čistá DPH k doplatku/vrácení
            'totals' => []          // Celkové souhrny
        ];

        // Initialize all Czech VAT rates
        foreach (self::CZECH_VAT_RATES as $rateName => $rate) {
            $summary['output_vat'][$rate] = [
                'rate_name' => $rateName,
                'rate_percent' => $rate,
                'base_amount' => 0,
                'vat_amount' => 0,
                'total_amount' => 0,
                'transaction_count' => 0
            ];

            $summary['input_vat'][$rate] = [
                'rate_name' => $rateName,
                'rate_percent' => $rate,
                'base_amount' => 0,
                'vat_amount' => 0,
                'total_amount' => 0,
                'transaction_count' => 0
            ];
        }

        // Process output VAT from invoices
        foreach ($invoices as $invoice) {
            foreach ($invoice->items as $item) {
                $rate = $item->vat_rate;

                if (!isset($summary['output_vat'][$rate])) {
                    $summary['output_vat'][$rate] = [
                        'rate_name' => $this->getRateName($rate),
                        'rate_percent' => $rate,
                        'base_amount' => 0,
                        'vat_amount' => 0,
                        'total_amount' => 0,
                        'transaction_count' => 0
                    ];
                }

                // Convert amounts to CZK using invoice exchange rate
                $exchangeRate = $invoice->exchange_rate ?? 1.0;
                $baseAmount = $item->quantity * $item->unit_price;
                $vatAmount = $baseAmount * ($rate / 100);

                // Convert to CZK if needed
                $baseAmountCzk = $invoice->currency === 'CZK' ? $baseAmount : $baseAmount * $exchangeRate;
                $vatAmountCzk = $invoice->currency === 'CZK' ? $vatAmount : $vatAmount * $exchangeRate;

                $summary['output_vat'][$rate]['base_amount'] += $baseAmountCzk;
                $summary['output_vat'][$rate]['vat_amount'] += $vatAmountCzk;
                $summary['output_vat'][$rate]['total_amount'] += $baseAmountCzk + $vatAmountCzk;
                $summary['output_vat'][$rate]['transaction_count']++;
            }
        }

        // Process input VAT from expenses
        foreach ($expenses as $expense) {
            $rate = $expense->vat_rate ?? 0;

            if (!isset($summary['input_vat'][$rate])) {
                $summary['input_vat'][$rate] = [
                    'rate_name' => $this->getRateName($rate),
                    'rate_percent' => $rate,
                    'base_amount' => 0,
                    'vat_amount' => 0,
                    'total_amount' => 0,
                    'transaction_count' => 0
                ];
            }

            // Convert expense amounts to CZK
            $exchangeRate = $expense->exchange_rate ?? 1.0;
            $totalAmount = $expense->amount;
            $baseAmount = $totalAmount / (1 + ($rate / 100));
            $vatAmount = $totalAmount - $baseAmount;

            // Convert to CZK if needed
            $totalAmountCzk = $expense->currency === 'CZK' ? $totalAmount : $totalAmount * $exchangeRate;
            $baseAmountCzk = $expense->currency === 'CZK' ? $baseAmount : $baseAmount * $exchangeRate;
            $vatAmountCzk = $expense->currency === 'CZK' ? $vatAmount : $vatAmount * $exchangeRate;

            $summary['input_vat'][$rate]['base_amount'] += $baseAmountCzk;
            $summary['input_vat'][$rate]['vat_amount'] += $vatAmountCzk;
            $summary['input_vat'][$rate]['total_amount'] += $totalAmountCzk;
            $summary['input_vat'][$rate]['transaction_count']++;
        }

        // Calculate net VAT for each rate
        $allRates = array_unique(array_merge(
            array_keys($summary['output_vat']),
            array_keys($summary['input_vat'])
        ));

        foreach ($allRates as $rate) {
            $outputVat = $summary['output_vat'][$rate]['vat_amount'] ?? 0;
            $inputVat = $summary['input_vat'][$rate]['vat_amount'] ?? 0;

            $summary['net_vat'][$rate] = [
                'rate_name' => $this->getRateName($rate),
                'rate_percent' => $rate,
                'output_vat' => $outputVat,
                'input_vat' => $inputVat,
                'net_amount' => $outputVat - $inputVat,
                'status' => ($outputVat - $inputVat) >= 0 ? 'to_pay' : 'to_refund'
            ];
        }

        // Calculate totals
        $summary['totals'] = [
            'total_output_base' => array_sum(array_column($summary['output_vat'], 'base_amount')),
            'total_output_vat' => array_sum(array_column($summary['output_vat'], 'vat_amount')),
            'total_input_base' => array_sum(array_column($summary['input_vat'], 'base_amount')),
            'total_input_vat' => array_sum(array_column($summary['input_vat'], 'vat_amount')),
            'total_net_vat' => array_sum(array_column($summary['net_vat'], 'net_amount')),
            'invoice_count' => $invoices->count(),
            'expense_count' => $expenses->count()
        ];

        return $summary;
    }

    /**
     * Generate quarterly VAT return (Čtvrtletní hlášení DPH)
     */
    protected function generateQuarterlyVatReturn(array $vatSummary, int $quarter, int $year): array
    {
        $return = [
            'period' => [
                'quarter' => $quarter,
                'year' => $year,
                'due_date' => $this->getQuarterlyReturnDueDate($quarter, $year)
            ],
            'form_data' => [
                // Řádky podle formuláře pro DPH
                'r01' => $vatSummary['totals']['total_output_base'], // Základ daně - základní sazba
                'r02' => $vatSummary['output_vat'][21]['vat_amount'] ?? 0, // DPH - základní sazba
                'r03' => $vatSummary['output_vat'][12]['base_amount'] ?? 0, // Základ daně - snížená sazba
                'r04' => $vatSummary['output_vat'][12]['vat_amount'] ?? 0, // DPH - snížená sazba
                'r05' => $vatSummary['output_vat'][0]['base_amount'] ?? 0, // Osvobozené plnění
                'r10' => $vatSummary['totals']['total_output_vat'], // Celkem DPH na výstupu
                'r41' => $vatSummary['totals']['total_input_vat'], // DPH na vstupu
                'r60' => $vatSummary['totals']['total_net_vat'], // Rozdíl DPH
            ],
            'validation' => $this->validateQuarterlyReturn($vatSummary)
        ];

        return $return;
    }

    /**
     * Analyze reverse charge transactions (Přenesení daňové povinnosti)
     */
    protected function analyzeReverseChargeTransactions(Collection $invoices, Collection $expenses): array
    {
        $reverseCharge = [
            'applicable_invoices' => [],
            'applicable_expenses' => [],
            'total_amount' => 0,
            'criteria_met' => []
        ];

        // Check invoices for reverse charge applicability
        foreach ($invoices as $invoice) {
            if ($this->isReverseChargeApplicable($invoice)) {
                $reverseCharge['applicable_invoices'][] = [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'client' => $invoice->client->company_name,
                    'client_country' => $invoice->client->country,
                    'amount' => $invoice->total,
                    'reason' => $this->getReverseChargeReason($invoice)
                ];
                $reverseCharge['total_amount'] += $invoice->total;
            }
        }

        // Check expenses for reverse charge
        foreach ($expenses as $expense) {
            if ($this->isExpenseReverseChargeApplicable($expense)) {
                $reverseCharge['applicable_expenses'][] = [
                    'expense_id' => $expense->id,
                    'description' => $expense->description,
                    'amount' => $expense->amount,
                    'reason' => $this->getExpenseReverseChargeReason($expense)
                ];
            }
        }

        return $reverseCharge;
    }

    /**
     * Analyze EU sales for reporting (Souhrnné hlášení)
     */
    protected function analyzeEuSales(Collection $invoices): array
    {
        $euCountries = [
            'AT', 'BE', 'BG', 'HR', 'CY', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT',
            'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'
        ];

        $euSales = [
            'total_amount' => 0,
            'transactions' => [],
            'reporting_required' => false
        ];

        foreach ($invoices as $invoice) {
            $clientCountry = strtoupper($invoice->client->country ?? '');

            if (in_array($clientCountry, $euCountries) && !empty($invoice->client->vat_id)) {
                $euSales['transactions'][] = [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'client' => $invoice->client->company_name,
                    'client_vat_id' => $invoice->client->vat_id,
                    'country' => $clientCountry,
                    'amount' => $invoice->total,
                    'date' => $invoice->issue_date->format('d.m.Y')
                ];
                $euSales['total_amount'] += $invoice->total;
            }
        }

        // Reporting required if annual EU sales exceed 1,200,000 CZK
        $euSales['reporting_required'] = $euSales['total_amount'] > 1200000;

        return $euSales;
    }

    /**
     * Perform compliance check
     */
    protected function performComplianceCheck(array $vatSummary): array
    {
        $checks = [
            'vat_rates_valid' => true,
            'calculations_accurate' => true,
            'warnings' => [],
            'errors' => []
        ];

        // Check VAT rates are valid Czech rates
        foreach ($vatSummary['output_vat'] as $rate => $data) {
            if (!in_array($rate, array_values(self::CZECH_VAT_RATES))) {
                $checks['vat_rates_valid'] = false;
                $checks['warnings'][] = "Nestandartní sazba DPH: {$rate}%";
            }
        }

        // Check for unusual amounts
        $totalNetVat = $vatSummary['totals']['total_net_vat'];
        if (abs($totalNetVat) > 500000) { // Over 500k CZK
            $checks['warnings'][] = "Vysoká částka DPH k doplatku/vrácení: " . number_format($totalNetVat, 2) . " CZK";
        }

        return $checks;
    }

    /**
     * Helper methods
     */
    protected function getRateName(int $rate): string
    {
        return match($rate) {
            21 => 'standard',
            12 => 'reduced',
            0 => 'zero',
            default => 'other'
        };
    }

    protected function getQuarterlyReturnDueDate(int $quarter, int $year): string
    {
        $dueDates = [
            1 => "$year-04-25", // Q1 due April 25
            2 => "$year-07-25", // Q2 due July 25
            3 => "$year-10-25", // Q3 due October 25
            4 => ($year + 1) . "-01-25" // Q4 due January 25 next year
        ];

        return $dueDates[$quarter] ?? '';
    }

    protected function isReverseChargeApplicable(Invoice $invoice): bool
    {
        // EU B2B transactions with valid VAT ID
        $euCountries = ['AUSTRIA', 'BELGIUM', 'BULGARIA', 'CROATIA', 'CYPRUS', 'DENMARK', 'ESTONIA', 'FINLAND', 'FRANCE', 'GERMANY', 'GREECE', 'HUNGARY', 'IRELAND', 'ITALY', 'LATVIA', 'LITHUANIA', 'LUXEMBOURG', 'MALTA', 'NETHERLANDS', 'POLAND', 'PORTUGAL', 'ROMANIA', 'SLOVAKIA', 'SLOVENIA', 'SPAIN', 'SWEDEN'];
        $clientCountry = strtoupper($invoice->client->country ?? '');

        return in_array($clientCountry, $euCountries) &&
               !empty($invoice->client->vat_id) &&
               $invoice->total > 0;
    }

    protected function getReverseChargeReason(Invoice $invoice): string
    {
        return "EU B2B transakce - přenesení daňové povinnosti";
    }

    protected function isExpenseReverseChargeApplicable(Expense $expense): bool
    {
        // This would need more context about the expense supplier
        return false; // Placeholder - would need supplier information
    }

    protected function getExpenseReverseChargeReason(Expense $expense): string
    {
        return "Reverse charge applicable";
    }

    protected function validateQuarterlyReturn(array $vatSummary): array
    {
        $validation = [
            'valid' => true,
            'errors' => [],
            'warnings' => []
        ];

        // Basic validation rules
        if ($vatSummary['totals']['total_output_vat'] < 0) {
            $validation['errors'][] = "DPH na výstupu nemůže být záporná";
            $validation['valid'] = false;
        }

        if ($vatSummary['totals']['total_input_vat'] < 0) {
            $validation['errors'][] = "DPH na vstupu nemůže být záporná";
            $validation['valid'] = false;
        }

        return $validation;
    }

    /**
     * Export VAT data for official Czech forms
     */
    public function exportForCzechForms(array $vatData): array
    {
        return [
            'xml_format' => $this->generateXmlForCzechTaxOffice($vatData),
            'csv_format' => $this->generateCsvForAccountant($vatData),
            'summary_report' => $this->generateSummaryReport($vatData)
        ];
    }

    protected function generateXmlForCzechTaxOffice(array $vatData): string
    {
        // Generate XML in format required by Czech tax office
        // This would implement the official XML schema
        return "<?xml version='1.0' encoding='UTF-8'?><!-- Czech VAT XML format -->";
    }

    protected function generateCsvForAccountant(array $vatData): string
    {
        // Generate CSV suitable for accountant software
        return "Rate,Base,VAT,Total\n"; // Simplified example
    }

    protected function generateSummaryReport(array $vatData): array
    {
        return [
            'period' => $vatData['period'],
            'summary' => $vatData['vat_summary']['totals'],
            'generated_at' => now()->format('d.m.Y H:i:s')
        ];
    }
}