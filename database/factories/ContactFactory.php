<?php

namespace Database\Factories;

use App\Enums\ContactRole;
use App\Enums\ContactType;
use App\Models\Contact;
use App\Models\GeneralBusinessPostingGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'full_name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'type' => ContactType::PERSON->value,
            'role' => ContactRole::PROSPECT->value,
            'address' => $this->faker->address(),
            'city' => $this->faker->city(),
            'general_business_posting_group_id' => GeneralBusinessPostingGroup::factory(),
            'vendor_posting_group_id' => null,
        ];
    }
}
