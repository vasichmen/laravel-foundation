<?php

namespace Laravel\Foundation\Validation;

interface CustomRule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value, $validationData, Validator $validator);

    /**
     * Get the validation error message.
     *
     * @return string|array
     */
    public function message();
}
