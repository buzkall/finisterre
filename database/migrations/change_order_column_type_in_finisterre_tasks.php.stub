<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('finisterre.table_name', 'finisterre_tasks'), function(Blueprint $table): void {
            $table->decimal('order_column', 20, 10)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table(config('finisterre.table_name', 'finisterre_tasks'), function(Blueprint $table): void {
            $table->unsignedInteger('order_column')->nullable()->change();
        });
    }
};
