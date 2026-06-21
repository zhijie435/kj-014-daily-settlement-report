<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_settlement_reports', function (Blueprint $table) {
            $driver = DB::getDriverName();
            $indexes = [];

            if ($driver === 'sqlite') {
                $indexRows = DB::select("PRAGMA index_list(daily_settlement_reports)");
                foreach ($indexRows as $indexRow) {
                    $indexName = $indexRow->name;
                    $columns = DB::select("PRAGMA index_info({$indexName})");
                    $columnNames = array_column($columns, 'name');
                    sort($columnNames);
                    $indexes[] = [
                        'name' => $indexName,
                        'columns' => $columnNames,
                        'unique' => (bool) $indexRow->unique,
                    ];
                }
            } elseif ($driver === 'mysql') {
                $indexRows = DB::select("SHOW INDEX FROM daily_settlement_reports WHERE Key_name != 'PRIMARY'");
                $indexGroups = [];
                foreach ($indexRows as $indexRow) {
                    $indexGroups[$indexRow->Key_name][] = $indexRow->Column_name;
                }
                foreach ($indexGroups as $name => $columns) {
                    sort($columns);
                    $indexes[] = [
                        'name' => $name,
                        'columns' => $columns,
                        'unique' => $indexRows[0]->Non_unique == 0,
                    ];
                }
            } elseif ($driver === 'pgsql') {
                $indexRows = DB::select("
                    SELECT 
                        i.relname as index_name,
                        array_agg(a.attname ORDER BY array_position(ind.indkey, a.attnum)) as columns,
                        ix.indisunique as is_unique
                    FROM pg_class t
                    JOIN pg_index ix ON t.oid = ix.indrelid
                    JOIN pg_class i ON i.oid = ix.indexrelid
                    JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY(ix.indkey)
                    WHERE t.relname = 'daily_settlement_reports'
                    GROUP BY i.relname, ix.indisunique
                ");
                foreach ($indexRows as $indexRow) {
                    $columns = $indexRow->columns;
                    if (is_string($columns)) {
                        $columns = explode(',', trim($columns, '{}'));
                    }
                    sort($columns);
                    $indexes[] = [
                        'name' => $indexRow->index_name,
                        'columns' => $columns,
                        'unique' => (bool) $indexRow->is_unique,
                    ];
                }
            }

            $singleColumnUnique = null;
            $combinedUniqueExists = false;

            foreach ($indexes as $index) {
                if ($index['unique']) {
                    if (count($index['columns']) === 1 && $index['columns'][0] === 'report_date') {
                        $singleColumnUnique = $index['name'];
                    }
                    if (count($index['columns']) === 2 
                        && in_array('report_date', $index['columns']) 
                        && in_array('type', $index['columns'])) {
                        $combinedUniqueExists = true;
                    }
                }
            }

            if ($singleColumnUnique) {
                $table->dropUnique($singleColumnUnique);
            }

            if (!$combinedUniqueExists) {
                $table->unique(['report_date', 'type']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('daily_settlement_reports', function (Blueprint $table) {
            $driver = DB::getDriverName();
            $indexes = [];

            if ($driver === 'sqlite') {
                $indexRows = DB::select("PRAGMA index_list(daily_settlement_reports)");
                foreach ($indexRows as $indexRow) {
                    $indexName = $indexRow->name;
                    $columns = DB::select("PRAGMA index_info({$indexName})");
                    $columnNames = array_column($columns, 'name');
                    sort($columnNames);
                    $indexes[] = [
                        'name' => $indexName,
                        'columns' => $columnNames,
                        'unique' => (bool) $indexRow->unique,
                    ];
                }
            }

            $combinedUnique = null;

            foreach ($indexes as $index) {
                if ($index['unique'] && count($index['columns']) === 2 
                    && in_array('report_date', $index['columns']) 
                    && in_array('type', $index['columns'])) {
                    $combinedUnique = $index['name'];
                    break;
                }
            }

            if ($combinedUnique) {
                $table->dropUnique($combinedUnique);
            }

            $table->unique('report_date');
        });
    }
};
