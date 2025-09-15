<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseCategoryController extends Controller
{
    public function index(): Response
    {
        $categories = ExpenseCategory::withCount('expenses')
            ->orderBy('name')
            ->get();
        
        return Inertia::render('ExpenseCategories/Index', [
            'categories' => $categories
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('ExpenseCategories/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:expense_categories',
        ]);

        ExpenseCategory::create([
            'name' => $request->name,
        ]);

        return redirect()->route('expense-categories.index')
            ->with('success', 'Expense category created successfully.');
    }

    public function show(ExpenseCategory $expenseCategory): Response
    {
        return Inertia::render('ExpenseCategories/Show', [
            'category' => $expenseCategory
        ]);
    }

    public function edit(ExpenseCategory $expenseCategory): Response
    {
        return Inertia::render('ExpenseCategories/Edit', [
            'category' => $expenseCategory
        ]);
    }

    public function update(Request $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:expense_categories,name,' . $expenseCategory->id,
        ]);

        $expenseCategory->update([
            'name' => $request->name,
        ]);

        return redirect()->route('expense-categories.index')
            ->with('success', 'Expense category updated successfully.');
    }

    public function destroy(ExpenseCategory $expenseCategory): RedirectResponse
    {
        // Check if category has expenses
        if ($expenseCategory->expenses()->count() > 0) {
            return redirect()->route('expense-categories.index')
                ->with('error', 'Cannot delete category that has expenses.');
        }

        $expenseCategory->delete();

        return redirect()->route('expense-categories.index')
            ->with('success', 'Expense category deleted successfully.');
    }
}