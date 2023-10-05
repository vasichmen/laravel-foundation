<?php

namespace Laravel\Foundation\Validation\Rules;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\Concerns\ValidatesAttributes;
use Laravel\Foundation\Validation\CustomRule;
use Laravel\Foundation\Validation\ImplicitRule;
use Laravel\Foundation\Validation\Validator;

class RequiredWithInRule implements CustomRule, ImplicitRule
{
    use ValidatesAttributes;

    private Collection $values;

    /**Правило выполняется, если массив значений $values пересекается с массивом строк в поле $field
     * @param string $field
     * @param string|array $values
     */
    public function __construct(private string $field, string|array $values)
    {
        $this->values = collect(Arr::wrap($values));
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value, $validationData, Validator $validator)
    {
        if (empty($validationData[$this->field]) || !is_array($validationData[$this->field])) {
            return true;
        }

        $isEmpty = $this->values->intersect($validationData[$this->field])->isEmpty();

        //если нет пересечений, то это поле не обязательное
        if ($isEmpty) {
            return true;
        }

        return $this->validateRequired($attribute, $value);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return "Поле :attribute обязательно, когда в $this->field присутствует одно из значений [" .
            implode(',', $this->values->toArray())
            . ']';
    }
}
