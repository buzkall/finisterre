<?php

namespace Arzcode\Finisterre\Database\Factories;

use Arzcode\Finisterre\Models\FinisterreSubtask;
use Arzcode\Finisterre\Models\FinisterreTask;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinisterreSubtaskFactory extends Factory
{
    protected $model = FinisterreSubtask::class;

    public function definition(): array
    {
        return [
            'task_id'   => FinisterreTask::inRandomOrder()->first() ?: FinisterreTask::factory(),
            'title'     => fake()->sentence,
            'completed' => fake()->boolean(),
        ];
    }
}
