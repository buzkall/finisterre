<?php

namespace Arzcode\Finisterre\Database\Factories;

use Arzcode\Finisterre\Models\FinisterreEvent;
use Arzcode\Finisterre\Models\FinisterreEventAttendee;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinisterreEventAttendeeFactory extends Factory
{
    protected $model = FinisterreEventAttendee::class;

    public function definition(): array
    {
        return [
            'event_id'    => FinisterreEvent::factory(),
            'user_id'     => null,
            'guest_name'  => fake()->name,
            'guest_email' => fake()->unique()->safeEmail,
        ];
    }

    public function forUser(int $userId): static
    {
        return $this->state(['user_id' => $userId, 'guest_name' => null, 'guest_email' => null]);
    }
}
