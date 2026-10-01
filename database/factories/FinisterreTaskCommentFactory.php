<?php

namespace Arzcode\Finisterre\Database\Factories;

use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Models\FinisterreTaskComment;
use Arzcode\Finisterre\Support\UserModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinisterreTaskComment>
 */
class FinisterreTaskCommentFactory extends Factory
{
    protected $model = FinisterreTaskComment::class;

    public function definition(): array
    {
        return [
            'task_id'    => FinisterreTask::inRandomOrder()->first() ?: FinisterreTask::factory(),
            'comment'    => fake()->paragraph(),
            'creator_id' => $this->user(),
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
