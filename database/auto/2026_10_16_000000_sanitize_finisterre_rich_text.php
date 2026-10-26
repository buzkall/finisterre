<?php

use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Models\FinisterreTaskComment;
use Arzcode\Finisterre\Support\SafeHtml;
use Arzcode\Finisterre\Support\Typed;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Descriptions and comments are sanitized as they are saved. This cleans the ones
     * saved before, through the query builder: nobody is notified and the tasks do not
     * look changed.
     */
    public function up(): void
    {
        foreach ([[new FinisterreTask, 'description'], [new FinisterreTaskComment, 'comment']] as [$model, $column]) {
            DB::table($model->getTable())
                ->whereNotNull($column)
                ->select([$model->getKeyName(), $column])
                ->chunkById(500, function($rows) use ($model, $column): void {
                    foreach ($rows as $row) {
                        $clean = SafeHtml::clean(Typed::nullableString($row->{$column}));

                        if ($clean !== $row->{$column}) {
                            DB::table($model->getTable())
                                ->where($model->getKeyName(), $row->{$model->getKeyName()})
                                ->update([$column => $clean]);
                        }
                    }
                }, $model->getKeyName());
        }
    }
};
