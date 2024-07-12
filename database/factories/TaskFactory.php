<?php

namespace Buzkall\Finisterre\Database\Factories;

//use App\Models\User;
use Buzkall\Finisterre\Enums\TaskPriorityEnum;
use Buzkall\Finisterre\Enums\TaskStateEnum;
use Illuminate\Console\View\Components\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence,
            'description' => fake()->paragraph,
            'state' => fake()->randomElement(TaskStateEnum::values()),
            'priority' => fake()->randomElement(TaskPriorityEnum::values()),
            'due_at' => fake()->dateTimeThisMonth(),
            'completed_at' => fake()->dateTimeThisMonth(),
            //'created_by_user_id'  => User::inRandomOrder()->first() ?: User::factory(),
            //'assigned_to_user_id' => User::inRandomOrder()->first() ?: User::factory(),
        ];
    }
}

