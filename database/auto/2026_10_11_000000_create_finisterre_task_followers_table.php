<?php

use Arzcode\Finisterre\Support\TaskFollowers;
use Arzcode\Finisterre\Support\Typed;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $followers = TaskFollowers::table();
        $tasks = Typed::string(config('finisterre.table_name'), 'finisterre_tasks');
        $users = Typed::string(config('finisterre.authenticatable_table_name'), 'users');

        Schema::create($followers, function(Blueprint $table) use ($tasks, $users): void {
            $table->id();
            $table->foreignId('task_id')->constrained($tasks)->cascadeOnDelete();
            $table->foreignId('user_id')->constrained($users)->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'user_id']);
        });

        // Existing tasks start with the followers a new one gets: its creator and its
        // assignee, following since the task was created.
        foreach (['creator_id', 'assignee_id'] as $column) {
            DB::table($followers)->insertOrIgnoreUsing(
                ['task_id', 'user_id', 'created_at', 'updated_at'],
                DB::table($tasks)->select(['id', $column, 'created_at', 'created_at as updated_at'])->whereNotNull($column)
            );
        }

        // A comment scheduled before this version with nobody picked went to the
        // assignee once it was due. An empty list now means nobody, so the ones
        // still waiting get the assignee written down, as their author expected.
        $comments = Typed::string(config('finisterre.comments.table_name'), 'finisterre_task_comments');

        if (! Schema::hasColumns($comments, ['notify_user_ids', 'scheduled_for', 'sent_at'])) {
            return;
        }

        $waiting = DB::table($comments)
            ->join($tasks, "$tasks.id", '=', "$comments.task_id")
            ->whereNotNull("$comments.scheduled_for")
            ->whereNull("$comments.sent_at")
            ->whereNotNull("$comments.notify_user_ids")
            ->whereNotNull("$tasks.assignee_id")
            ->get(["$comments.id", "$comments.notify_user_ids", "$tasks.assignee_id"]);

        foreach ($waiting as $comment) {
            if (json_decode(Typed::string($comment->notify_user_ids), true) === []) {
                DB::table($comments)->where('id', $comment->id)->update([
                    'notify_user_ids' => json_encode([Typed::int($comment->assignee_id)]),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(TaskFollowers::table());
    }
};
