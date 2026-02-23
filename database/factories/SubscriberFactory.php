<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriberFactory extends Factory
{
    public function definition()
    {
        return [
            'email' => $this->faker->unique()->safeEmail(),
            'name' => $this->faker->name(),
            'status' => $this->faker->randomElement(['active', 'inactive', 'unsubscribed']),
            'source' => $this->faker->randomElement(['import', 'manual', 'api', 'webform']),
            'user_id' => User::factory(),
            'subscribed_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'unsubscribed_at' => $this->faker->optional(0.2)->dateTimeBetween('-6 months', 'now'),
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'updated_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
