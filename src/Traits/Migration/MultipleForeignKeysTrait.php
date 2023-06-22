<?php

namespace Laravel\Foundation\Traits\Migration;

use Illuminate\Support\Facades\DB;

trait MultipleForeignKeysTrait
{
    /**POSTGRES ONLY: создает триггер в таблице $table, проверяющий наличие ключа в поле $column на существование в любой из таблиц $tables
     * @param string $table
     * @param string $column
     * @param array $tables
     * @param string $keyColumn
     * @param string|null $errorMessage
     * @return void
     */
    protected function makeMultipleForeignKey(
        string $table,
        string $column,
        array $tables,
        string $keyColumn = 'id',
        ?string $errorMessage = null
    ): void {
        $functionName = 'check_' . $table . '_' . $column . '_value';
        $tables = collect($tables);
        if (empty($errorMessage)) {
            $errorMessage = "Поле $table.$column должно быть представлено в одной из таблиц: "
                . $tables->map(fn(string $table) => $table . ".$keyColumn")->implode(', ');
        }

        $firstPart =
            "
        CREATE OR REPLACE FUNCTION $functionName() RETURNS trigger
            LANGUAGE plpgsql
        AS $$
        BEGIN
            IF 
            ";

        $conditions = $tables
            ->map(fn(string $table) => "NOT EXISTS(SELECT $keyColumn FROM $table WHERE $keyColumn = new.$column)")
            ->implode(' AND ');

        $secondPart =
            "    THEN
                RAISE EXCEPTION '$errorMessage';
            end if;

            RETURN new;
        END $$;

        CREATE OR REPLACE TRIGGER insert_{$functionName}_trigger
            BEFORE INSERT
            ON $table
            FOR EACH ROW
        EXECUTE FUNCTION $functionName();

        CREATE OR REPLACE TRIGGER update_{$functionName}_trigger
            BEFORE UPDATE
            ON $table
            FOR EACH ROW
        EXECUTE FUNCTION $functionName();
        ";

        DB::unprepared($firstPart . $conditions . $secondPart);
    }
}