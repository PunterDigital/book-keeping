<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ExpenseCategory;
use App\Models\Expense;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;

class BookkeepingSeeder extends Seeder
{
    public function run(): void
    {
        // Create expense categories
        $categories = [
            'Office Supplies',
            'Travel & Transportation',
            'Software & Subscriptions',
            'Marketing & Advertising',
            'Professional Services',
            'Equipment',
            'Utilities',
            'Meals & Entertainment'
        ];

        foreach ($categories as $categoryName) {
            ExpenseCategory::create(['name' => $categoryName]);
        }

        // Create sample clients
        $clients = [
            [
                'company_name' => 'ABC Technologies s.r.o.',
                'contact_name' => 'Pavel Novák',
                'address' => 'Wenceslas Square 1, 110 00 Prague 1, Czech Republic',
                'vat_id' => 'CZ12345678',
                'tax_id' => 'CZ12345678901'
            ],
            [
                'company_name' => 'Digital Solutions Czech Republic',
                'contact_name' => 'Marie Svobodová',
                'address' => 'Národní 25, 110 00 Prague 1, Czech Republic',
                'vat_id' => 'CZ87654321',
                'tax_id' => 'CZ87654321098'
            ],
            [
                'company_name' => 'Prague Startup Hub',
                'contact_name' => 'Tomáš Dvořák',
                'address' => 'Vinohrady 15, 120 00 Prague 2, Czech Republic',
                'vat_id' => 'CZ11223344',
                'tax_id' => null
            ]
        ];

        foreach ($clients as $clientData) {
            Client::create($clientData);
        }

        // Create sample expenses
        $officeCategory = ExpenseCategory::where('name', 'Office Supplies')->first();
        $travelCategory = ExpenseCategory::where('name', 'Travel & Transportation')->first();
        $softwareCategory = ExpenseCategory::where('name', 'Software & Subscriptions')->first();

        $expenses = [
            [
                'date' => now()->subDays(5),
                'amount' => 2500.00,
                'category_id' => $officeCategory->id,
                'description' => 'Office furniture and supplies',
                'vat_amount' => 525.00,
                'receipt_path' => null
            ],
            [
                'date' => now()->subDays(12),
                'amount' => 1200.00,
                'category_id' => $travelCategory->id,
                'description' => 'Business trip to Brno - train tickets and accommodation',
                'vat_amount' => 252.00,
                'receipt_path' => null
            ],
            [
                'date' => now()->subDays(8),
                'amount' => 890.00,
                'category_id' => $softwareCategory->id,
                'description' => 'Adobe Creative Suite annual subscription',
                'vat_amount' => 186.90,
                'receipt_path' => null
            ],
            [
                'date' => now()->subDays(3),
                'amount' => 450.00,
                'category_id' => $travelCategory->id,
                'description' => 'Taxi to client meeting',
                'vat_amount' => 94.50,
                'receipt_path' => null
            ]
        ];

        foreach ($expenses as $expenseData) {
            Expense::create($expenseData);
        }

        // Create sample invoices
        $client1 = Client::where('company_name', 'ABC Technologies s.r.o.')->first();
        $client2 = Client::where('company_name', 'Digital Solutions Czech Republic')->first();

        $invoices = [
            [
                'invoice_number' => 'INV-2024-001',
                'client_id' => $client1->id,
                'issue_date' => now()->subDays(10),
                'due_date' => now()->addDays(20),
                'status' => 'sent',
                'subtotal' => 25000.00,
                'vat_amount' => 5250.00,
                'total' => 30250.00,
                'notes' => 'Web application development - Phase 1',
                'pdf_path' => null
            ],
            [
                'invoice_number' => 'INV-2024-002',
                'client_id' => $client2->id,
                'issue_date' => now()->subDays(5),
                'due_date' => now()->addDays(25),
                'status' => 'draft',
                'subtotal' => 15000.00,
                'vat_amount' => 3150.00,
                'total' => 18150.00,
                'notes' => 'Digital marketing consultation',
                'pdf_path' => null
            ]
        ];

        foreach ($invoices as $invoiceData) {
            $invoice = Invoice::create($invoiceData);

            // Add items to the first invoice
            if ($invoice->invoice_number === 'INV-2024-001') {
                $items = [
                    [
                        'description' => 'Frontend Development (React.js)',
                        'quantity' => 60,
                        'unit_price' => 250.00,
                        'vat_rate' => 21.00
                    ],
                    [
                        'description' => 'Backend Development (Laravel)',
                        'quantity' => 40,
                        'unit_price' => 300.00,
                        'vat_rate' => 21.00
                    ],
                    [
                        'description' => 'Database Design & Setup',
                        'quantity' => 10,
                        'unit_price' => 350.00,
                        'vat_rate' => 21.00
                    ]
                ];

                foreach ($items as $itemData) {
                    $invoice->items()->create($itemData);
                }
            }

            // Add items to the second invoice
            if ($invoice->invoice_number === 'INV-2024-002') {
                $items = [
                    [
                        'description' => 'SEO Analysis & Strategy',
                        'quantity' => 20,
                        'unit_price' => 400.00,
                        'vat_rate' => 21.00
                    ],
                    [
                        'description' => 'Social Media Campaign Setup',
                        'quantity' => 15,
                        'unit_price' => 300.00,
                        'vat_rate' => 21.00
                    ],
                    [
                        'description' => 'Google Ads Configuration',
                        'quantity' => 10,
                        'unit_price' => 450.00,
                        'vat_rate' => 21.00
                    ]
                ];

                foreach ($items as $itemData) {
                    $invoice->items()->create($itemData);
                }
            }
        }
    }
}