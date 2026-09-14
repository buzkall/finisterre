<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tasks = config('finisterre.table_name', 'finisterre_tasks');

        Schema::table($tasks, function(Blueprint $table) {
            // The images pasted into the task's description and comments that belong
            // to it, so a private disk serves them only to users who can see the task.
            $table->json('editor_files')->nullable()->after('cover_media_id');
        });

        // The images already in descriptions and comments were uploaded before
        // anything recorded where. Each one goes to every task loading it now, the
        // access it had until this migration; from here on, loading it from
        // somewhere else grants nothing.
        $sources = [
            [$tasks, 'description', 'id'],
            [config('finisterre.comments.table_name', 'finisterre_task_comments'), 'comment', 'task_id'],
        ];

        $files = [];

        foreach ($sources as [$source, $column, $taskKey]) {
            DB::table($source)
                ->select(array_unique(['id', $taskKey, $column]))
                ->where($column, 'like', '%finisterre-files/%')
                ->chunkById(200, function($rows) use (&$files, $column, $taskKey) {
                    foreach ($rows as $row) {
                        if ($row->{$taskKey} === null) {
                            continue;
                        }

                        preg_match_all('~finisterre-files/([^/"\'?#]+)(?=["\'?#])~', (string)$row->{$column}, $matches);

                        $files[$row->{$taskKey}] = [...$files[$row->{$taskKey}] ?? [], ...$matches[1]];
                    }
                });
        }

        foreach ($files as $taskId => $paths) {
            if ($paths !== []) {
                DB::table($tasks)->where('id', $taskId)->update(['editor_files' => json_encode(array_values(array_unique($paths)))]);
            }
        }
    }

    public function down(): void
    {
        Schema::table(config('finisterre.table_name', 'finisterre_tasks'), function(Blueprint $table) {
            $table->dropColumn('editor_files');
        });
    }
};
