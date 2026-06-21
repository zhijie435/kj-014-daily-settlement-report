<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_settlement_reports', function (Blueprint $table) {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = $sm->listTableIndexes('daily_settlement_reports');

            foreach ($indexes as $index) {
                $columns = $index->getColumns();
                if (count($columns) === 1 && $columns[0] === 'report_date' && $index->isUnique()) {
                    $table->dropUnique($index->getName());
                }
            }

            $existingCombinedUnique = false;
            foreach ($indexes as $index) {
                $columns = $index->getColumns();
                if (count($columns) === 2
                    && in_array('report_date', $columns)
                    && in_array('type', $columns)
                    && $index->isUnique()) {
                    $existingCombinedUnique = true;
                    break;
                }
            }

            if (!$existingCombinedUnique) {
                $table->unique(['report_date', 'type']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('daily_settlement_reports', function (Blueprint $table) {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = $sm->listTableIndexes('daily_settlement_reports');

            foreach ($indexes as $index) {
                $columns = $index->getColumns();
                if (count($columns) === 2
                    && in_array('report_date', $columns)
                    && in_array('type', $columns)
                    && $index->isUnique()) {
                    $table->dropUnique($index->getName());
                }
            }

            $table->unique('report_date');
        });
    }
};
