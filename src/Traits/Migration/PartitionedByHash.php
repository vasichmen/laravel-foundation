<?php


namespace Laravel\Foundation\Traits\Migration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait PartitionedByHash
{
    protected function makePartitionedTable(string $tableName, int $modulus, \Closure $fields): void
    {
        DB::unprepared("create table $tableName (id uuid PRIMARY KEY not null) PARTITION BY HASH (id)");

        for ($i = 0; $i < $modulus; $i++) {
            DB::unprepared("CREATE TABLE {$tableName}_{$i} PARTITION OF $tableName
                                 FOR VALUES WITH (MODULUS {$modulus}, REMAINDER {$i})");
        }

        Schema::table($tableName, $fields);
    }

}
