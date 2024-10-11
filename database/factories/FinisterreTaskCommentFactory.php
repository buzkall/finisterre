<?php

namespace Buzkall\Finisterre\Database\Factories;

use Buzkall\Finisterre\Models\FinisterreTask;
use Buzkall\Finisterre\Models\FinisterreTaskComment;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinisterreTaskCommentFactory extends Factory
{
    protected $model = FinisterreTaskComment::class;

    public function definition(): array
    {
        $authenticatable = config('finisterre.authenticatable');

        return [
            'task_id'    => FinisterreTask::inRandomOrder()->first() ?: FinisterreTask::factory(),
            'comment'    => fake()->paragraph,
            'creator_id' => $authenticatable::inRandomOrder()->first() ?: $authenticatable::factory(),
        ];
    }
}
