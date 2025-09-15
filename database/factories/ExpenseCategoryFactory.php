<?php

namespace Database\Factories;

use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseCategoryFactory extends Factory
{
    protected $model = ExpenseCategory::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement([
                'Office Supplies',
                'Travel & Transportation',
                'Software & Subscriptions',
                'Marketing & Advertising',
                'Professional Services',
                'Equipment & Hardware',
                'Utilities & Phone',
                'Meals & Entertainment',
                'Insurance',
                'Legal & Accounting',
                'Training & Education',
                'Rent & Facilities',
                'Maintenance & Repairs',
                'Postage & Shipping',
                'Bank Fees'
            ])
        ];
    }
}