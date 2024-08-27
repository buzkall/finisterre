<?php

namespace Buzkall\Finisterre\Database\Factories;

use Buzkall\Finisterre\Enums\TaskPriorityEnum;
use Buzkall\Finisterre\Enums\TaskStatusEnum;
use Buzkall\Finisterre\Models\FinisterreTask;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinisterreTaskFactory extends Factory
{
    protected $model = FinisterreTask::class;

    public function definition(): array
    {
        return [
            'title'        => fake()->sentence,
            'description'  => fake()->paragraph,
            'status'       => fake()->randomElement(TaskStatusEnum::values()),
            'priority'     => fake()->randomElement(TaskPriorityEnum::values()),
            'due_at'       => fake()->dateTimeThisMonth(),
            'completed_at' => fake()->dateTimeThisMonth(),
            //'created_by_user_id'  => User::inRandomOrder()->first() ?: User::factory(),
            //'assigned_to_user_id' => User::inRandomOrder()->first() ?: User::factory(),
        ];
    }
}
