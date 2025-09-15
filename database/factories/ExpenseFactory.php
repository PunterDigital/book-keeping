<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        $amount = $this->faker->randomFloat(2, 50, 5000); // Between 50 and 5000 CZK
        $vatRate = $this->faker->randomElement([0, 0.12, 0.21]); // Czech VAT rates
        $vatAmount = $amount * $vatRate;

        return [
            'date' => $this->faker->dateTimeBetween('-3 months', 'now'),
            'amount' => $amount,
            'category_id' => ExpenseCategory::factory(),
            'description' => $this->faker->randomElement([
                'Office supplies and stationery',
                'Business lunch with client',
                'Train tickets to Prague',
                'Software subscription renewal',
                'Office rent payment',
                'Marketing materials printing',
                'Conference attendance fee',
                'Professional consultation',
                'Internet and phone bills',
                'Equipment maintenance',
                'Business insurance premium',
                'Accounting services',
                'Legal consultation',
                'Office furniture',
                'Computer accessories',
                'Travel accommodation',
                'Fuel for business travel',
                'Parking fees',
                'Professional development course',
                'Business book purchases'
            ]),
            'vat_amount' => round($vatAmount, 2),
            'receipt_path' => $this->faker->optional(0.7)->randomElement([
                'receipts/receipt_' . $this->faker->uuid . '.pdf',
                'receipts/receipt_' . $this->faker->uuid . '.jpg',
                'receipts/receipt_' . $this->faker->uuid . '.png'
            ])
        ];
    }

    public function withCategory(ExpenseCategory $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $category->id,
        ]);
    }

    public function withAmount(float $amount, float $vatRate = 0.21): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => $amount,
            'vat_amount' => round($amount * $vatRate, 2),
        ]);
    }

    public function withoutReceipt(): static
    {
        return $this->state(fn (array $attributes) => [
            'receipt_path' => null,
        ]);
    }

    public function withReceipt(string $path = null): static
    {
        return $this->state(fn (array $attributes) => [
            'receipt_path' => $path ?? 'receipts/receipt_' . $this->faker->uuid . '.pdf',
        ]);
    }

    public function fromDate(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => Carbon::parse($date),
        ]);
    }

    public function thisMonth(): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => $this->faker->dateTimeThisMonth(),
        ]);
    }

    public function lastMonth(): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => Carbon::now()->subMonth()->addDays($this->faker->numberBetween(1, 28)),
        ]);
    }

    public function highValue(): static
    {
        $amount = $this->faker->randomFloat(2, 5000, 20000);
        return $this->state(fn (array $attributes) => [
            'amount' => $amount,
            'vat_amount' => round($amount * 0.21, 2),
            'description' => $this->faker->randomElement([
                'High-end office equipment',
                'Professional software license',
                'Conference sponsorship',
                'Major office renovation',
                'Company vehicle purchase',
                'Professional consulting project'
            ])
        ]);
    }

    public function lowValue(): static
    {
        $amount = $this->faker->randomFloat(2, 10, 200);
        return $this->state(fn (array $attributes) => [
            'amount' => $amount,
            'vat_amount' => round($amount * 0.21, 2),
            'description' => $this->faker->randomElement([
                'Coffee and snacks',
                'Parking fee',
                'Public transport ticket',
                'Small office supplies',
                'Newspaper subscription',
                'Mobile phone top-up'
            ])
        ]);
    }

    public function standardVat(): static
    {
        return $this->state(function (array $attributes) {
            $amount = $attributes['amount'] ?? 1000;
            return [
                'vat_amount' => round($amount * 0.21, 2),
            ];
        });
    }

    public function reducedVat(): static
    {
        return $this->state(function (array $attributes) {
            $amount = $attributes['amount'] ?? 1000;
            return [
                'vat_amount' => round($amount * 0.12, 2),
            ];
        });
    }

    public function vatExempt(): static
    {
        return $this->state(fn (array $attributes) => [
            'vat_amount' => 0.00,
        ]);
    }
}