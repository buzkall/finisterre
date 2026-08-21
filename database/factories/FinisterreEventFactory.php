<?php

namespace Arzcode\Finisterre\Database\Factories;

use Arzcode\Finisterre\Enums\EventStatusEnum;
use Arzcode\Finisterre\Models\FinisterreEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinisterreEventFactory extends Factory
{
    protected $model = FinisterreEvent::class;

    public function definition(): array
    {
        $authenticatable = config('finisterre.authenticatable');

        return [
            'title'                 => fake()->sentence(3),
            'description'           => fake()->paragraph,
            'public_agenda'         => fake()->paragraph,
            'private_agenda'        => fake()->paragraph,
            'status'                => EventStatusEnum::Draft,
            'duration_minutes'      => 60,
            'requires_confirmation' => false,
            'open_registration'     => false,
            'creator_id'            => $authenticatable::inRandomOrder()->first() ?: $authenticatable::factory(),
        ];
    }

    public function scheduling(): static
    {
        return $this->state(['status' => EventStatusEnum::Scheduling]);
    }
}
