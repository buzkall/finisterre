<?php

namespace Buzkall\Finisterre\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetSequencesCommand extends Command
{
    public $signature = 'finisterre:reset-sequences';
    public $description = 'Reset PostgreSQL sequences to MAX(id) so inserts stop colliding after a database import.';

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->warn('This command only applies to PostgreSQL connections.');

            return self::SUCCESS;
        }

        $sequences = DB::select(<<<'SQL'
            SELECT
                c.relname AS table_name,
                a.attname AS column_name,
                pg_get_serial_sequence(quote_ident(n.nspname) || '.' || quote_ident(c.relname), a.attname) AS sequence_name
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            JOIN pg_attribute a ON a.attrelid = c.oid AND a.attnum > 0 AND NOT a.attisdropped
            WHERE c.relkind = 'r'
              AND n.nspname = 'public'
              AND pg_get_serial_sequence(quote_ident(n.nspname) || '.' || quote_ident(c.relname), a.attname) IS NOT NULL
        SQL);

        foreach ($sequences as $seq) {
            $table = '"' . str_replace('"', '""', $seq->table_name) . '"';
            $column = '"' . str_replace('"', '""', $seq->column_name) . '"';

            DB::statement(
                "SELECT setval(?, COALESCE((SELECT MAX($column) FROM $table), 1), (SELECT MAX($column) IS NOT NULL FROM $table))",
                [$seq->sequence_name]
            );

            $this->line("Reset {$seq->sequence_name}");
        }

        $this->info(count($sequences) . ' sequence(s) reset.');

        return self::SUCCESS;
    }
}
