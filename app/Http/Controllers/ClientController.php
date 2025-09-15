<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(): Response
    {
        $clients = Client::withCount('invoices')
            ->withSum('invoices', 'total')
            ->orderBy('company_name')
            ->get()
            ->map(function ($client) {
                return [
                    'id' => $client->id,
                    'company_name' => $client->company_name,
                    'contact_name' => $client->contact_name,
                    'email' => $client->email ?? '',
                    'phone' => $client->phone ?? '',
                    'vat_id' => $client->vat_id,
                    'invoices_count' => $client->invoices_count,
                    'total_revenue' => $client->invoices_sum_total ?: 0,
                    'is_active' => $client->is_active ?? true,
                ];
            });

        return Inertia::render('Clients/Index', [
            'clients' => $clients
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Clients/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'required|string',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'vat_id' => 'nullable|string|max:50',
            'company_id' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        // Set default country if not provided
        if (empty($validated['country'])) {
            $validated['country'] = 'Czech Republic';
        }

        Client::create($validated);

        return redirect()->route('clients.index')
            ->with('success', 'Client created successfully.');
    }

    public function show(Client $client): Response
    {
        $client->load(['invoices' => function ($query) {
            $query->orderBy('issue_date', 'desc');
        }]);

        return Inertia::render('Clients/Show', [
            'client' => [
                'id' => $client->id,
                'company_name' => $client->company_name,
                'contact_name' => $client->contact_name,
                'email' => $client->email,
                'phone' => $client->phone,
                'address' => $client->address,
                'city' => $client->city,
                'postal_code' => $client->postal_code,
                'country' => $client->country,
                'vat_id' => $client->vat_id,
                'company_id' => $client->company_id,
                'notes' => $client->notes,
                'is_active' => $client->is_active ?? true,
                'invoices' => $client->invoices->map(function ($invoice) {
                    return [
                        'id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'issue_date' => $invoice->issue_date->format('Y-m-d'),
                        'due_date' => $invoice->due_date->format('Y-m-d'),
                        'total' => $invoice->total,
                        'status' => $invoice->status,
                    ];
                }),
            ]
        ]);
    }

    public function edit(Client $client): Response
    {
        return Inertia::render('Clients/Edit', [
            'client' => $client
        ]);
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'required|string',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'vat_id' => 'nullable|string|max:50',
            'company_id' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $client->update($validated);

        return redirect()->route('clients.index')
            ->with('success', 'Client updated successfully.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        // Check if client has invoices
        if ($client->invoices()->count() > 0) {
            return redirect()->route('clients.index')
                ->with('error', 'Cannot delete client with existing invoices.');
        }

        $client->delete();

        return redirect()->route('clients.index')
            ->with('success', 'Client deleted successfully.');
    }
}