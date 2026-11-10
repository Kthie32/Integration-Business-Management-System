<?php

namespace Database\Factories;

use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_number' => 'EMP-'.fake()->unique()->numerify('#####'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'gender' => fake()->randomElement(Gender::cases()),
            'date_of_birth' => fake()->dateTimeBetween('-55 years', '-20 years'),
            'phone' => '+2567'.fake()->numerify('########'),
            'email' => fake()->unique()->safeEmail(),
            'department_id' => Department::factory(),
            'job_title' => fake()->jobTitle(),
            'employment_type' => EmploymentType::Permanent,
            'hire_date' => fake()->dateTimeBetween('-5 years', '-1 month'),
            'status' => EmployeeStatus::Active,
            'is_resident_for_tax' => true,
        ];
    }

    /**
     * Identity numbers and bank details filled in.
     */
    public function withSensitiveDetails(): static
    {
        return $this->state(fn (array $attributes) => [
            'nin' => 'CM'.fake()->unique()->bothify('############'),
            'tin' => fake()->numerify('10########'),
            'nssf_number' => fake()->numerify('NS#########'),
            'bank_name' => 'Stanbic Bank',
            'bank_account' => fake()->numerify('90300#######'),
            'mobile_money_number' => '+2567'.fake()->numerify('########'),
        ]);
    }

    public function terminated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EmployeeStatus::Terminated,
            'termination_date' => now()->subDay(),
            'termination_reason' => 'Resigned',
        ]);
    }
}
