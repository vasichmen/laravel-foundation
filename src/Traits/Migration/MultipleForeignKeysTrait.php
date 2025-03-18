<?php

namespace Laravel\Foundation\Traits\Migration;

use Illuminate\Support\Facades\DB;

trait MultipleForeignKeysTrait
{
    /**POSTGRES ONLY: создает триггер в таблице $table, проверяющий наличие ключа в поле $column на существование в любой из таблиц $tables
     * @param string $table название таблицы, на которую ставится проверка
     * @param string $column название столбца для проверки
     * @param array $tables список таблиц, в которых ищутся записи
     * @param string $keyColumn поле для поиска в таблицах $tables
     * @param string|null $errorMessage текст ошибки
     * @param bool $cascadeOnDelete если true, то записи из связующей таблицы будут удаляться вместе с записями, на которые ссылается поле $column
     * @return void
     */
    protected function makeMultipleForeignKey(
        string $table,
        string $column,
        array $tables,
        string $keyColumn = 'id',
        ?string $errorMessage = null,
        bool $cascadeOnDelete = false,
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


        //установка триггера на удаление
        if ($cascadeOnDelete) {
            $function = "CREATE OR REPLACE FUNCTION {$table}_cascade_delete() returns trigger as
                $$
                begin
                    delete from $table where $column = old.id;
                    return old;
                end;
                $$ LANGUAGE plpgsql;";

            $triggers = $tables->map(function ($relatedTable) use ($table) {
                return "
                    CREATE OR REPLACE TRIGGER delete_{$relatedTable}_trigger
                        before delete
                        on $relatedTable
                        for each row
                    execute procedure {$table}_cascade_delete();";
            })->join(' ');

            DB::unprepared($function . $triggers);
        }
    }
}
