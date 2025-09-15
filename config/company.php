<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Company Information
    |--------------------------------------------------------------------------
    |
    | This configuration file contains the company information used in 
    | invoices and reports. These values are used in Czech invoice PDFs
    | to comply with local tax requirements.
    |
    */

    'name' => env('COMPANY_NAME', 'Vaše společnost s.r.o.'),
    'address' => env('COMPANY_ADDRESS', 'Hlavní 123'),
    'city' => env('COMPANY_CITY', 'Praha 1, 110 00'),
    'country' => env('COMPANY_COUNTRY', 'Česká republika'),
    
    // Czech business identifiers
    'ico' => env('COMPANY_ICO', '12345678'),
    'dic' => env('COMPANY_DIC', 'CZ12345678'),
    'vat_number' => env('COMPANY_VAT_NUMBER', null), // Only if VAT registered
    
    // Banking information
    'bank_account' => env('COMPANY_BANK_ACCOUNT', '123456789/0100'),
    'iban' => env('COMPANY_BANK_IBAN', 'CZ6501000000123456789'),
    'swift' => env('COMPANY_BANK_SWIFT', 'KOMBCZPP'),
    
    // Contact information
    'phone' => env('COMPANY_PHONE', '+420 123 456 789'),
    'email' => env('COMPANY_EMAIL', 'info@vasespolecnost.cz'),
    'website' => env('COMPANY_WEBSITE', 'www.vasespolecnost.cz'),
    
    // Invoice settings
    'invoice_logo' => env('COMPANY_LOGO_PATH', null),
    'invoice_footer' => env('COMPANY_INVOICE_FOOTER', 'Děkujeme za vaši důvěru a těšíme se na další spolupráci.'),
];