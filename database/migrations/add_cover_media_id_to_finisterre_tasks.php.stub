<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('finisterre.table_name', 'finisterre_tasks'), function(Blueprint $table) {
            // No foreign key on purpose: the media table belongs to
            // spatie/laravel-medialibrary and is published separately, so it is not
            // guaranteed to exist when this runs. A dangling id resolves to null
            // through the relation, and the media observer clears it on delete.
            $table->unsignedBigInteger('cover_media_id')->nullable()->after('assignee_id');
        });
    }

    public function down(): void
    {
        Schema::table(config('finisterre.table_name', 'finisterre_tasks'), function(Blueprint $table) {
            $table->dropColumn('cover_media_id');
        });
    }
};
