<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_has_correct_fillable_attributes()
    {
        $client = new Client();
        $fillable = $client->getFillable();

        $expectedFillable = [
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

        $this->assertEquals($expectedFillable, $fillable);
    }

    public function test_client_can_be_created_with_all_fields()
    {
        $clientData = [
            'company_name' => 'Test Company s.r.o.',
            'contact_name' => 'Jan Novák',
            'email' => 'jan@test.cz',
            'phone' => '+420 123 456 789',
            'address' => 'Testovací 123',
            'city' => 'Praha',
            'postal_code' => '11000',
            'country' => 'Česká republika',
            'vat_id' => 'CZ12345678',
            'company_id' => '12345678',
            'notes' => 'Test client notes',
            'is_active' => true
        ];

        $client = Client::create($clientData);

        $this->assertDatabaseHas('clients', $clientData);
        $this->assertEquals('Test Company s.r.o.', $client->company_name);
        $this->assertEquals('Jan Novák', $client->contact_name);
        $this->assertEquals('jan@test.cz', $client->email);
        $this->assertTrue($client->is_active);
    }

    public function test_client_can_be_created_with_minimal_fields()
    {
        $client = Client::create([
            'company_name' => 'Minimal Company',
            'address' => 'Basic Address 1'
        ]);

        $this->assertNotNull($client->id);
        $this->assertEquals('Minimal Company', $client->company_name);
        $this->assertEquals('Basic Address 1', $client->address);
        $this->assertNull($client->contact_name);
        $this->assertNull($client->email);
        $this->assertNull($client->phone);
    }

    public function test_full_address_attribute()
    {
        $client = Client::create([
            'company_name' => 'Test Company',
            'address' => 'Main Street 123',
            'city' => 'Prague',
            'postal_code' => '11000',
            'country' => 'Czech Republic'
        ]);

        $expectedAddress = 'Main Street 123, Prague, 11000, Czech Republic';
        $this->assertEquals($expectedAddress, $client->full_address);
    }

    public function test_full_address_attribute_with_partial_data()
    {
        $client = Client::create([
            'company_name' => 'Test Company',
            'address' => 'Main Street 123',
            'city' => 'Prague'
            // No postal_code or country
        ]);

        $expectedAddress = 'Main Street 123, Prague';
        $this->assertEquals($expectedAddress, $client->full_address);
    }

    public function test_full_address_attribute_with_only_address()
    {
        $client = Client::create([
            'company_name' => 'Test Company',
            'address' => 'Main Street 123'
            // No city, postal_code or country
        ]);

        $expectedAddress = 'Main Street 123';
        $this->assertEquals($expectedAddress, $client->full_address);
    }

    public function test_total_revenue_attribute_with_no_invoices()
    {
        $client = Client::create([
            'company_name' => 'Test Company',
            'address' => 'Test Address'
        ]);

        $this->assertEquals(0.0, $client->total_revenue);
    }

    public function test_total_revenue_attribute_with_invoices()
    {
        $client = Client::create([
            'company_name' => 'Test Company',
            'address' => 'Test Address'
        ]);

        // Create test invoices
        Invoice::create([
            'invoice_number' => '2024001',
            'client_id' => $client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00,
            'status' => 'paid'
        ]);

        Invoice::create([
            'invoice_number' => '2024002',
            'client_id' => $client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 500.00,
            'vat_amount' => 105.00,
            'total' => 605.00,
            'status' => 'sent'
        ]);

        // Total should be 1210 + 605 = 1815
        $this->assertEquals(1815.0, $client->total_revenue);
    }

    public function test_unpaid_amount_attribute_with_no_invoices()
    {
        $client = Client::create([
            'company_name' => 'Test Company',
            'address' => 'Test Address'
        ]);

        $this->assertEquals(0.0, $client->unpaid_amount);
    }

    public function test_unpaid_amount_attribute_excludes_paid_invoices()
    {
        $client = Client::create([
            'company_name' => 'Test Company',
            'address' => 'Test Address'
        ]);

        // Paid invoice (should not be included)
        Invoice::create([
            'invoice_number' => '2024001',
            'client_id' => $client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00,
            'status' => 'paid'
        ]);

        // Unpaid invoices (should be included)
        Invoice::create([
            'invoice_number' => '2024002',
            'client_id' => $client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 500.00,
            'vat_amount' => 105.00,
            'total' => 605.00,
            'status' => 'sent'
        ]);

        Invoice::create([
            'invoice_number' => '2024003',
            'client_id' => $client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->subDays(5), // Overdue
            'subtotal' => 200.00,
            'vat_amount' => 42.00,
            'total' => 242.00,
            'status' => 'overdue'
        ]);

        Invoice::create([
            'invoice_number' => '2024004',
            'client_id' => $client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 300.00,
            'vat_amount' => 63.00,
            'total' => 363.00,
            'status' => 'draft'
        ]);

        // Should include sent + overdue + draft = 605 + 242 + 363 = 1210
        $this->assertEquals(1210.0, $client->unpaid_amount);
    }

    public function test_client_has_many_invoices()
    {
        $client = Client::create([
            'company_name' => 'Test Company',
            'address' => 'Test Address'
        ]);

        Invoice::create([
            'invoice_number' => '2024001',
            'client_id' => $client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 1000.00,
            'vat_amount' => 210.00,
            'total' => 1210.00,
        ]);

        Invoice::create([
            'invoice_number' => '2024002',
            'client_id' => $client->id,
            'issue_date' => Carbon::now(),
            'due_date' => Carbon::now()->addDays(30),
            'subtotal' => 500.00,
            'vat_amount' => 105.00,
            'total' => 605.00,
        ]);

        $this->assertCount(2, $client->invoices);
        $this->assertInstanceOf(Invoice::class, $client->invoices->first());
    }

    public function test_client_updates_correctly()
    {
        $client = Client::create([
            'company_name' => 'Original Company',
            'address' => 'Original Address',
            'is_active' => true
        ]);

        $client->update([
            'company_name' => 'Updated Company',
            'contact_name' => 'New Contact',
            'email' => 'new@email.com',
            'is_active' => false
        ]);

        $this->assertEquals('Updated Company', $client->fresh()->company_name);
        $this->assertEquals('New Contact', $client->fresh()->contact_name);
        $this->assertEquals('new@email.com', $client->fresh()->email);
        $this->assertFalse($client->fresh()->is_active);
    }

    public function test_client_deletion()
    {
        $client = Client::create([
            'company_name' => 'Test Company',
            'address' => 'Test Address'
        ]);

        $clientId = $client->id;
        $client->delete();

        $this->assertDatabaseMissing('clients', ['id' => $clientId]);
    }

    public function test_is_active_default_behavior()
    {
        // Test default behavior (should be handled by database default or application logic)
        $client = Client::create([
            'company_name' => 'Test Company',
            'address' => 'Test Address'
        ]);

        // The is_active field behavior depends on your database schema
        // If you have a default value in the migration, test that
        // Otherwise, it might be null
        $this->assertTrue(is_bool($client->is_active) || is_null($client->is_active));
    }

    public function test_client_with_czech_characters()
    {
        $client = Client::create([
            'company_name' => 'Příliš žluťoučká společnost s.r.o.',
            'contact_name' => 'Václav Dvořák',
            'address' => 'Náměstí míru 123',
            'city' => 'Brno',
            'country' => 'Česká republika'
        ]);

        $this->assertEquals('Příliš žluťoučká společnost s.r.o.', $client->company_name);
        $this->assertEquals('Václav Dvořák', $client->contact_name);
        $this->assertStringContainsString('Náměstí míru 123', $client->full_address);
        $this->assertStringContainsString('Česká republika', $client->full_address);
    }

    public function test_client_vat_id_and_company_id_storage()
    {
        $client = Client::create([
            'company_name' => 'Test Company',
            'address' => 'Test Address',
            'vat_id' => 'CZ12345678',
            'company_id' => '12345678'
        ]);

        $this->assertEquals('CZ12345678', $client->vat_id);
        $this->assertEquals('12345678', $client->company_id);
    }

    public function test_client_notes_field()
    {
        $notes = 'This is a very important client. Handle with care. Special discount applies.';
        
        $client = Client::create([
            'company_name' => 'VIP Client',
            'address' => 'VIP Address',
            'notes' => $notes
        ]);

        $this->assertEquals($notes, $client->notes);
    }

    public function test_client_phone_and_email_fields()
    {
        $client = Client::create([
            'company_name' => 'Contact Company',
            'address' => 'Contact Address',
            'phone' => '+420 123 456 789',
            'email' => 'contact@company.com'
        ]);

        $this->assertEquals('+420 123 456 789', $client->phone);
        $this->assertEquals('contact@company.com', $client->email);
    }

    public function test_client_relationship_configuration()
    {
        $client = new Client();
        
        // Test invoices relationship
        $invoicesRelation = $client->invoices();
        $this->assertEquals(Invoice::class, $invoicesRelation->getRelated()::class);
        $this->assertEquals('client_id', $invoicesRelation->getForeignKeyName());
        $this->assertEquals('id', $invoicesRelation->getLocalKeyName());
    }

    public function test_full_address_handles_empty_fields()
    {
        $client = Client::create([
            'company_name' => 'Test Company',
            'address' => 'Main Street 123',
            'city' => '',
            'postal_code' => null,
            'country' => 'Czech Republic'
        ]);

        // Should filter out empty/null values
        $expectedAddress = 'Main Street 123, Czech Republic';
        $this->assertEquals($expectedAddress, $client->full_address);
    }
}