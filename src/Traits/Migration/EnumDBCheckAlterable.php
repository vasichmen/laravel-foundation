<?php


namespace Laravel\Foundation\Traits;


use Illuminate\Support\Facades\DB;

trait EnumDBCheckAlterable
{
    /**
     * @param                 $table
     * @param                 $field
     * @param  array<string>  $options
     * @return void
     */
    protected function alterEnum($table, $field, array $options): void
    {
        $check = "${table}_${field}_check";

        $enumList = [];

        foreach ($options as $option) {
            $enumList[] = sprintf("'%s'::CHARACTER VARYING", $option);
        }

        $enumString = implode(", ", $enumList);

        DB::transaction(function () use ($table, $field, $check, $options, $enumString) {
            DB::statement(sprintf('ALTER TABLE %s DROP CONSTRAINT %s;', $table, $check));
            DB::statement(sprintf('ALTER TABLE %s ADD CONSTRAINT %s CHECK (%s::TEXT = ANY (ARRAY[%s]::TEXT[]))', $table, $check, $field, $enumString));
        });
    }
}
