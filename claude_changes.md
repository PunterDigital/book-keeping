# Claude Changes Log - Multi-Currency Support Implementation

## Overview
Implementation of Euro support and multi-currency functionality for the Czech bookkeeping system as per improvements-roadmap.md section 3.1.

## Changes Made

### 1. Database Migrations
- **File**: `database/migrations/2025_09_15_093240_add_currency_support_to_invoices_table.php`
  - Added `currency` field (VARCHAR(3), default 'CZK')
  - Added `exchange_rate` field (DECIMAL(10,4), default 1.0000)

- **File**: `database/migrations/2025_09_15_093256_add_currency_support_to_expenses_table.php`
  - Added `currency` field (VARCHAR(3), default 'CZK')
  - Added `exchange_rate` field (DECIMAL(10,4), default 1.0000)

### 2. Exchange Rate Service
- **File**: `app/Services/ExchangeRateService.php` (NEW)
  - Implements Czech National Bank API integration
  - Provides current exchange rates for supported currencies
  - Includes caching mechanism (1 hour cache duration)
  - Supports currency conversion and rate retrieval
  - Fallback handling for API failures

### 3. Model Updates

#### Invoice Model (`app/Models/Invoice.php`)
- Added `currency` and `exchange_rate` to fillable array
- Added `exchange_rate` to casts array
- Imported `ExchangeRateService`
- Added helper methods:
  - `getTotalInCzk()` - Convert total to base currency
  - `getFormattedTotal()` - Display formatted amount with currency symbol
  - `getCurrencySymbol()` - Get appropriate currency symbol
  - `getExchangeRateInfo()` - Display exchange rate information

#### Expense Model (`app/Models/Expense.php`)
- Added `currency` and `exchange_rate` to fillable array
- Added `exchange_rate` to casts array
- Imported `ExchangeRateService`
- Added helper methods:
  - `getAmountInCzk()` - Convert amount to base currency
  - `getFormattedAmount()` - Display formatted amount with currency symbol
  - `getCurrencySymbol()` - Get appropriate currency symbol
  - `getExchangeRateInfo()` - Display exchange rate information

### 4. Controller Updates

#### InvoiceController (`app/Http/Controllers/InvoiceController.php`)
- Imported `ExchangeRateService`
- Updated `create()` method to inject `ExchangeRateService` and provide available currencies
- Updated validation in `store()` method to include `currency` and `exchange_rate` fields
- Updated invoice creation to store currency and exchange rate
- Modified `index()` method to include currency information in response data

#### ExpenseController (`app/Http\Controllers\ExpenseController.php`)
- Imported `ExchangeRateService`
- Updated `create()` method to provide available currencies
- Updated validation in `store()` method to include `currency` and `exchange_rate` fields
- Updated expense creation to store currency and exchange rate

### 5. Frontend Updates

#### Invoice Creation Form (`resources/js/pages/Invoices/Create.vue`)
- Changed grid layout from 2 columns to 3 columns to accommodate currency selection
- Added currency selection dropdown with exchange rate fetching
- Added form fields for `currency` and `exchange_rate`
- Added reactive variables and functions:
  - `exchangeRateInfo` for displaying current rates
  - `getCurrencySymbol()` for currency display
  - `fetchExchangeRate()` for API integration
- Updated `formatCurrency()` to use selected currency
- Updated form data to include currency fields

#### Expense Creation Form (`resources/js/pages/Expenses/Create.vue`)
- Added currency selection section with exchange rate display
- Updated amount labels to show selected currency
- Added TypeScript interface for `availableCurrencies` prop
- Added form fields for `currency` and `exchange_rate`
- Added currency-related functions matching invoice implementation
- Updated VAT amount label to show selected currency

#### Invoice Index Page (`resources/js/pages/Invoices/Index.vue`)
- Updated invoice list to display formatted totals with currency symbols
- Added exchange rate information display in invoice listings
- Modified summary calculations to convert amounts to CZK for accurate totals
- Enhanced invoice data structure to include currency formatting

### 7. PDF Template Updates

#### Invoice PDF Template (`resources/views/invoices/pdf.blade.php`)
- Added PHP variables to handle currency and exchange rate display
- Enhanced styling with exchange rate information section
- Updated all currency displays to use actual currency instead of hardcoded "Kč"
- Added currency codes to table headers for clarity
- Implemented exchange rate information box for non-CZK invoices
- Added CZK equivalent display in totals section for foreign currency invoices
- Enhanced invoice details section with currency and exchange rate information
- Added currency notes for accounting and VAT purposes
- **Separated client identification display**:
  - Shows "IČ DPH:" for Czech VAT numbers (starting with "CZ")
  - Shows "DIČ:" for other tax identification numbers
  - Maintains separate "IČO:" for company registration numbers

#### Invoice PDF Service (`app/Services/InvoicePdfService.php`)
- Enhanced `savePdf()` method to support S3 storage with local fallback
- Improved error handling for PDF storage operations
- Maintained compatibility with existing storage systems

### 8. Multi-Currency Reporting System Updates

#### Reports Controller (`app/Http/Controllers/ReportsController.php`)
- **Updated VAT calculations** to convert invoice amounts to CZK using exchange rates
- **Enhanced expense VAT processing** to use `getAmountInCzk()` method
- **Fixed client statement summaries** to show accurate CZK totals
- **Added currency conversion notes** for transparency in reports

#### Expense Report Service (`app/Services/ExpenseReportService.php`)
- **Modified summary calculations** to use `getAmountInCzk()` for accurate totals
- **Updated category breakdown** to convert all amounts to CZK
- **Enhanced VAT breakdown** to handle multi-currency expense reporting
- **Added currency notation** to indicate all amounts are in CZK

#### Czech VAT Reporting Service (`app/Services/CzechVatReportingService.php`)
- **Updated output VAT calculations** from invoices with proper currency conversion
- **Enhanced input VAT processing** from expenses using exchange rates
- **Fixed base amount calculations** to ensure VAT compliance in CZK
- **Maintained transaction counts** while converting monetary values

#### Invoice Index Display (`resources/js/pages/Invoices/Index.vue`)
- **Fixed summary calculations** to use proper CZK conversion via `total_czk`
- **Updated summary card labels** to clearly indicate CZK totals
- **Improved data flow** using server-side calculated CZK equivalents
- **Enhanced user experience** with clear currency labeling

#### Invoice Controller Data Enhancement (`app/Http/Controllers/InvoiceController.php`)
- **Added `total_czk` field** to invoice index data for accurate reporting
- **Integrated model methods** for consistent currency conversion
- **Ensured proper data structure** for multi-currency invoice listings
- **Enhanced invoice show method** to include currency and exchange rate data

#### Invoice Show/View Page (`resources/js/pages/Invoices/Show.vue`)
- **Fixed formatCurrency function** to use actual invoice currency instead of hardcoded CZK
- **Added currency information display** in invoice details section
- **Enhanced table headers** to show currency codes (e.g., "Unit Price (EUR)")
- **Added exchange rate information** in invoice details and payment sections
- **Conditional currency display** only shows for non-CZK invoices
- **Improved payment information** with exchange rate context

### 6. Route Updates
- **File**: `routes/web.php`
  - Added API endpoint: `GET api/exchange-rates/{from}/{to}` for real-time exchange rate fetching

## Technical Implementation Details

### Exchange Rate Integration
- Uses Czech National Bank (CNB) official API with correct URL structure
- Handles CNB publishing schedule (2:30 PM daily, no weekends)
- Uses previous business day's rate before 2:30 PM publishing time
- Format: `selected.txt?from=DD.MM.YYYY&to=DD.MM.YYYY&currency=EUR&format=txt`
- Automatic caching prevents excessive API calls (per currency per day)
- Fallback to 1.0 exchange rate if API is unavailable
- Real-time rate fetching in frontend forms

#### CNB API Implementation Details
- **URL Structure**: Uses the correct CNB English API endpoint
- **Date Format**: DD.MM.YYYY format as required by CNB
- **Time Logic**: Requests previous day's rate before 2:30 PM Czech time
- **Weekend Handling**: Skips weekends automatically to get last business day rate
- **Response Parsing**: Handles CNB's specific format (Currency: EUR|Amount: 1, Date|Rate)
- **Amount Normalization**: Correctly normalizes rates to per-unit basis

### Currency Support
- Primary currencies: CZK (base), EUR, USD, GBP
- Extensible to support additional currencies through CNB API
- Currency symbols properly displayed in UI
- Exchange rate information shown on invoices and expenses

### Data Consistency
- All amounts stored in original currency with exchange rate
- Base currency (CZK) calculations for reporting and summaries
- Historical exchange rates preserved with each transaction

### User Experience
- Intuitive currency selection in forms
- Real-time exchange rate display
- Clear indication of foreign currency transactions
- Automatic currency symbol formatting

## Business Impact
- Supports international clients requiring EUR invoicing
- Maintains Czech compliance while expanding currency options
- Preserves audit trail with historical exchange rates
- Enables accurate financial reporting in base currency (CZK)

## Future Enhancements
- Additional currency support as needed
- Exchange rate history tracking
- Currency-specific reporting options
- Automated exchange rate updates

## Testing Status
Implementation completed. Ready for testing with:
1. Invoice creation in multiple currencies
2. Expense recording in foreign currencies
3. Exchange rate API functionality
4. Currency display and formatting
5. Summary calculations accuracy