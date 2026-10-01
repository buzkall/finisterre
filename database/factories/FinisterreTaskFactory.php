<?php

namespace Arzcode\Finisterre\Database\Factories;

use Arzcode\Finisterre\Enums\TaskPriorityEnum;
use Arzcode\Finisterre\Enums\TaskStatusEnum;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Support\UserModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinisterreTask>
 */
class FinisterreTaskFactory extends Factory
{
    protected $model = FinisterreTask::class;

    public function definition(): array
    {
        return [
            'title'        => fake()->sentence(),
            'description'  => fake()->paragraph(),
            'status'       => fake()->randomElement(TaskStatusEnum::values()),
            'priority'     => fake()->randomElement(TaskPriorityEnum::values()),
            'due_at'       => fake()->dateTimeThisMonth(),
            'completed_at' => fake()->dateTimeThisMonth(),
            'creator_id'   => $this->user(),
            'assignee_id'  => $this->user(),
        ];
    }

    /**
     * A random existing user, or a factory for a new one.
     */
    protected function user(): mixed
    {
        $authenticatable = UserModel::class();

        return $authenticatable::query()->inRandomOrder()->first()
            ?? (method_exists($authenticatable, 'factory') ? $authenticatable::factory() : null);
    }
}
