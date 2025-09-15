<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Client;
use Carbon\Carbon;

class CzechInvoiceValidationService
{
    /**
     * Validate invoice against Czech legal requirements (§13 DPH law and ISDOC standards)
     */
    public function validateInvoiceFormat(Invoice $invoice): array
    {
        $errors = [];
        $warnings = [];

        // Load the client relationship if not already loaded
        if (!$invoice->relationLoaded('client')) {
            $invoice->load('client');
        }

        // Load items relationship if not already loaded
        if (!$invoice->relationLoaded('items')) {
            $invoice->load('items');
        }

        // 1. Invoice Number Format Validation (YYYY-NNNN)
        $numberValidation = $this->validateInvoiceNumber($invoice->invoice_number);
        if (!$numberValidation['valid']) {
            $errors[] = $numberValidation['message'];
        }

        // 2. Mandatory Fields per §13 DPH Law
        $mandatoryFieldsValidation = $this->validateMandatoryFields($invoice);
        $errors = array_merge($errors, $mandatoryFieldsValidation['errors']);
        $warnings = array_merge($warnings, $mandatoryFieldsValidation['warnings']);

        // 3. VAT Calculation Verification
        $vatValidation = $this->validateVatCalculation($invoice);
        if (!$vatValidation['valid']) {
            $errors[] = $vatValidation['message'];
        }

        // 4. Due Date Rules (standard 14 days from issue)
        $dueDateValidation = $this->validateDueDate($invoice);
        if (!$dueDateValidation['valid']) {
            $warnings[] = $dueDateValidation['message'];
        }

        // 5. ISDOC Compliance Checks
        $isdocValidation = $this->validateIsdocCompliance($invoice);
        $warnings = array_merge($warnings, $isdocValidation['warnings']);

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'compliance_level' => $this->getComplianceLevel($errors, $warnings)
        ];
    }

    /**
     * Validate invoice number format according to Czech standards (YYYY-NNNN)
     */
    public function validateInvoiceNumber(string $invoiceNumber): array
    {
        // Czech standard format: YYYY-NNNN (e.g., 2024-0001)
        $pattern = '/^(\d{4})-(\d{4})$/';

        if (!preg_match($pattern, $invoiceNumber, $matches)) {
            return [
                'valid' => false,
                'message' => 'Číslo faktury musí být ve formátu YYYY-NNNN (např. 2024-0001)'
            ];
        }

        $year = (int) $matches[1];
        $number = (int) $matches[2];

        // Validate year (should be reasonable range)
        $currentYear = Carbon::now()->year;
        if ($year < 2020 || $year > ($currentYear + 1)) {
            return [
                'valid' => false,
                'message' => "Rok v čísle faktury ({$year}) je mimo očekávaný rozsah (2020-" . ($currentYear + 1) . ")"
            ];
        }

        // Validate sequential numbering (should be > 0)
        if ($number < 1) {
            return [
                'valid' => false,
                'message' => 'Pořadové číslo faktury musí být větší než 0'
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate mandatory fields according to §13 DPH law
     */
    protected function validateMandatoryFields(Invoice $invoice): array
    {
        $errors = [];
        $warnings = [];

        // Required by §13 DPH law:
        // 1. Označení, že se jedná o daňový doklad
        // 2. Evidenční číslo daňového dokladu
        // 3. Datum vystavení a datum uskutečnění zdanitelného plnění
        // 4. Jméno a sídlo/adresa dodavatele a odběratele
        // 5. DIČ dodavatele (pokud je plátcem DPH)
        // 6. Rozsah a předmět zdanitelného plnění
        // 7. Datum splatnosti
        // 8. Základ daně, sazba a výše daně
        // 9. Celková výše úhrady

        // Check invoice number (already checked above)
        if (empty($invoice->invoice_number)) {
            $errors[] = 'Číslo faktury je povinné (§13 odst. 4 písm. b) zákona o DPH)';
        }

        // Check issue date
        if (empty($invoice->issue_date)) {
            $errors[] = 'Datum vystavení faktury je povinné (§13 odst. 4 písm. c) zákona o DPH)';
        }

        // Check due date
        if (empty($invoice->due_date)) {
            $errors[] = 'Datum splatnosti je povinné (§13 odst. 4 písm. h) zákona o DPH)';
        }

        // Check client information
        if (!$invoice->client) {
            $errors[] = 'Údaje o odběrateli jsou povinné (§13 odst. 4 písm. d) zákona o DPH)';
        } else {
            // Client company name
            if (empty($invoice->client->company_name)) {
                $errors[] = 'Název společnosti odběratele je povinný';
            }

            // Client address
            if (empty($invoice->client->address) || empty($invoice->client->city)) {
                $errors[] = 'Úplná adresa odběratele je povinná (§13 odst. 4 písm. d) zákona o DPH)';
            }

            // VAT ID validation for Czech companies
            if (!empty($invoice->client->vat_id)) {
                $vatValidation = $this->validateVatId($invoice->client->vat_id);
                if (!$vatValidation['valid']) {
                    $warnings[] = $vatValidation['message'];
                }
            }
        }

        // Check invoice items (rozsah a předmět zdanitelného plnění)
        if ($invoice->items->isEmpty()) {
            $errors[] = 'Faktura musí obsahovat alespoň jednu položku (§13 odst. 4 písm. f) zákona o DPH)';
        } else {
            foreach ($invoice->items as $index => $item) {
                if (empty($item->description)) {
                    $errors[] = "Popis položky č. " . ($index + 1) . " je povinný";
                }
                if ($item->quantity <= 0) {
                    $errors[] = "Množství položky č. " . ($index + 1) . " musí být větší než 0";
                }
                if ($item->unit_price < 0) {
                    $errors[] = "Jednotková cena položky č. " . ($index + 1) . " nesmí být záporná";
                }
            }
        }

        // Check totals
        if ($invoice->subtotal < 0) {
            $errors[] = 'Základ daně nesmí být záporný';
        }
        if ($invoice->vat_amount < 0) {
            $errors[] = 'Výše DPH nesmí být záporná';
        }
        if ($invoice->total < 0) {
            $errors[] = 'Celková částka nesmí být záporná';
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate VAT calculation
     */
    protected function validateVatCalculation(Invoice $invoice): array
    {
        if ($invoice->items->isEmpty()) {
            return ['valid' => false, 'message' => 'Nelze ověřit výpočet DPH bez položek faktury'];
        }

        $calculatedSubtotal = 0;
        $calculatedVatAmount = 0;

        foreach ($invoice->items as $item) {
            $itemSubtotal = $item->quantity * $item->unit_price;
            $itemVatAmount = $itemSubtotal * ($item->vat_rate / 100);

            $calculatedSubtotal += $itemSubtotal;
            $calculatedVatAmount += $itemVatAmount;
        }

        // Allow small rounding differences (1 haléř)
        $tolerance = 0.01;

        if (abs($invoice->subtotal - $calculatedSubtotal) > $tolerance) {
            return [
                'valid' => false,
                'message' => "Chybný výpočet základu daně. Očekáváno: " . number_format($calculatedSubtotal, 2) . " CZK, aktuálně: " . number_format($invoice->subtotal, 2) . " CZK"
            ];
        }

        if (abs($invoice->vat_amount - $calculatedVatAmount) > $tolerance) {
            return [
                'valid' => false,
                'message' => "Chybný výpočet DPH. Očekáváno: " . number_format($calculatedVatAmount, 2) . " CZK, aktuálně: " . number_format($invoice->vat_amount, 2) . " CZK"
            ];
        }

        $calculatedTotal = $calculatedSubtotal + $calculatedVatAmount;
        if (abs($invoice->total - $calculatedTotal) > $tolerance) {
            return [
                'valid' => false,
                'message' => "Chybný výpočet celkové částky. Očekáváno: " . number_format($calculatedTotal, 2) . " CZK, aktuálně: " . number_format($invoice->total, 2) . " CZK"
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate due date (Czech standard is 14 days)
     */
    protected function validateDueDate(Invoice $invoice): array
    {
        if (!$invoice->issue_date || !$invoice->due_date) {
            return ['valid' => false, 'message' => 'Nelze ověřit lhůtu splatnosti bez data vystavení nebo splatnosti'];
        }

        $issueDate = Carbon::parse($invoice->issue_date);
        $dueDate = Carbon::parse($invoice->due_date);

        // Due date should be after issue date
        if ($dueDate->lte($issueDate)) {
            return ['valid' => false, 'message' => 'Datum splatnosti musí být po datu vystavení faktury'];
        }

        // Warn if not standard 14 days
        $standardDueDate = $issueDate->copy()->addDays(14);
        if (!$dueDate->isSameDay($standardDueDate)) {
            $daysDiff = $issueDate->diffInDays($dueDate);
            return [
                'valid' => true,
                'message' => "Nestandartní lhůta splatnosti: {$daysDiff} dní (standard je 14 dní)"
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate Czech VAT ID format
     */
    protected function validateVatId(string $vatId): array
    {
        // Czech VAT ID format: CZ + 8-10 digits
        $pattern = '/^CZ\d{8,10}$/';

        if (!preg_match($pattern, $vatId)) {
            return [
                'valid' => false,
                'message' => 'České DIČ musí být ve formátu CZ + 8-10 číslic (např. CZ12345678)'
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate ISDOC compliance
     */
    protected function validateIsdocCompliance(Invoice $invoice): array
    {
        $warnings = [];

        // ISDOC recommendations
        if (empty($invoice->notes)) {
            $warnings[] = 'ISDOC doporučuje včetně poznámek nebo platebních podmínek';
        }

        // Check for reasonable invoice amounts (ISDOC validation)
        if ($invoice->total > 1000000) { // 1M CZK
            $warnings[] = 'Vysoká částka faktury - ověřte správnost (nad 1 000 000 CZK)';
        }

        // Check for future dates
        $issueDate = Carbon::parse($invoice->issue_date);
        if ($issueDate->isFuture()) {
            $warnings[] = 'Datum vystavení je v budoucnosti - může způsobit problémy v ISDOC validaci';
        }

        return ['warnings' => $warnings];
    }

    /**
     * Get compliance level based on errors and warnings
     */
    protected function getComplianceLevel(array $errors, array $warnings): string
    {
        if (!empty($errors)) {
            return 'non_compliant';
        }

        if (!empty($warnings)) {
            return 'compliant_with_warnings';
        }

        return 'fully_compliant';
    }

    /**
     * Get recommended invoice number for given year
     */
    public function getRecommendedInvoiceNumber(int $year = null): string
    {
        $year = $year ?? Carbon::now()->year;

        // Find the highest number for this year
        $lastInvoice = Invoice::where('invoice_number', 'LIKE', $year . '-%')
            ->orderBy('invoice_number', 'desc')
            ->first();

        if (!$lastInvoice) {
            return $year . '-0001';
        }

        // Extract number from last invoice
        if (preg_match('/^' . $year . '-(\d+)$/', $lastInvoice->invoice_number, $matches)) {
            $nextNumber = (int) $matches[1] + 1;
            return $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        }

        return $year . '-0001';
    }

    /**
     * Validate invoice before saving
     */
    public function validateForSave(array $invoiceData): array
    {
        $errors = [];

        // Basic required fields
        if (empty($invoiceData['invoice_number'])) {
            $errors[] = 'Číslo faktury je povinné';
        } else {
            $numberValidation = $this->validateInvoiceNumber($invoiceData['invoice_number']);
            if (!$numberValidation['valid']) {
                $errors[] = $numberValidation['message'];
            }
        }

        if (empty($invoiceData['client_id'])) {
            $errors[] = 'Odběratel je povinný';
        }

        if (empty($invoiceData['issue_date'])) {
            $errors[] = 'Datum vystavení je povinné';
        }

        if (empty($invoiceData['due_date'])) {
            $errors[] = 'Datum splatnosti je povinné';
        }

        if (empty($invoiceData['items']) || !is_array($invoiceData['items'])) {
            $errors[] = 'Faktura musí obsahovat alespoň jednu položku';
        } else {
            foreach ($invoiceData['items'] as $index => $item) {
                if (empty($item['description'])) {
                    $errors[] = "Popis položky č. " . ($index + 1) . " je povinný";
                }
                if (!isset($item['quantity']) || $item['quantity'] <= 0) {
                    $errors[] = "Množství položky č. " . ($index + 1) . " musí být větší než 0";
                }
                if (!isset($item['unit_price']) || $item['unit_price'] < 0) {
                    $errors[] = "Jednotková cena položky č. " . ($index + 1) . " nesmí být záporná";
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}