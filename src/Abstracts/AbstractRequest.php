<?php


namespace Laravel\Foundation\Abstracts;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Contracts\Validation\ValidatesWhenResolved;
use Illuminate\Http\Request;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidatesWhenResolvedTrait;
use Laravel\Foundation\Exceptions\ValidationException;
use Laravel\Foundation\Validation\Validator;

abstract class AbstractRequest extends Request implements ValidatesWhenResolved
{
    use ValidatesWhenResolvedTrait;

    /**Имя класс DTO
     * @var string|null
     */
    protected ?string $dtoClassName = null;

    /**
     * The container instance.
     *
     * @var \Illuminate\Contracts\Container\Container
     */
    protected $container;

    /**
     * The key to be used for the view error bag.
     *
     * @var string
     */
    protected $errorBag = 'default';

    /**
     * The validator instance.
     *
     * @var \Illuminate\Contracts\Validation\Validator
     */
    protected $validator;

    /**
     * Get the validated data from the request.
     *
     * @return array|AbstractDto
     * @throws ValidationException
     */
    public function validated(): array|AbstractDto
    {
        return $this->prepareValidatedData($this->getValidatorInstance()->validated());
    }


    public function all($keys = null)
    {
        //на фронте в объекте FormData пустой массив выглядит как массив с null внутри. Такое валидация не пропускает, поэтому фильтруем массивы
        return array_map(function ($item) {
            if (is_array($item) && array_is_list($item)) {
                return array_filter($item, static fn($v) => !is_null($v));
            }
            return $item;
        }, parent::all($keys));
    }

    protected function prepareValidatedData(array $data)
    {
        //если задан класс DTO, то возвращаем объект DTO, если нет, то массив
        $dtoClass = $this->getDtoClassName();
        if (!empty($dtoClass) && is_subclass_of($dtoClass, AbstractDto::class)) {
            return new $dtoClass($data);
        }
        return $data;
    }

    protected function getDtoClassName(): ?string
    {
        return $this->dtoClassName;
    }

    /**
     * Get the validator instance for the request.
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function getValidatorInstance(): Validator
    {
        if ($this->validator) {
            return $this->validator;
        }

        $factory = $this->container->make(ValidationFactory::class);

        if (method_exists($this, 'validator')) {
            $validator = $this->container->call([$this, 'validator'], compact('factory'));
        } else {
            $validator = $this->createDefaultValidator($factory);
        }

        if (method_exists($this, 'withValidator')) {
            $this->withValidator($validator);
        }

        $this->setValidator($validator);

        return $this->validator;
    }

    /**
     * Create the default validator instance.
     *
     * @param \Illuminate\Contracts\Validation\Factory $factory
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function createDefaultValidator(Factory $factory): Validator
    {
        $validator = new Validator($factory->getTranslator(),
            $this->validationData(), $this->container->call([$this, 'rules']),
            $this->messages(), $this->attributes()
        );
        $validator->setPresenceVerifier($factory->getPresenceVerifier());
        return $validator;
    }

    /**
     * Get data to be validated from the request.
     *
     * @return array
     */
    public function validationData(): array
    {
        return $this->all();
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [];
    }

    /**
     * Set the Validator instance.
     *
     * @param \Illuminate\Contracts\Validation\Validator $validator
     * @return $this
     */
    public function setValidator(Validator $validator): static
    {
        $this->validator = $validator;

        return $this;
    }

    /**
     * Set the container implementation.
     *
     * @param \Illuminate\Contracts\Container\Container $container
     * @return $this
     */
    public function setContainer(Container $container): static
    {
        $this->container = $container;

        return $this;
    }

    /**
     * @param string $param
     * @param null $default
     * @return string|object|null
     */
    public function getRouteParameter(string $param, $default = null): string|null|object
    {
        return $this->route()->parameter($param, $default);
    }

    public function sorted(bool $required = true): array
    {
        $isRequired = $this->isRequiredOrNullable($required);
        return [
            'sort' => "$isRequired|array",
            'sort.*' => "$isRequired|string|in:asc,desc",
        ];
    }

    private function isRequiredOrNullable(bool $required): string
    {
        return $required ? 'required' : 'nullable';
    }

    public function paginated(bool $required = true): array
    {
        $isRequired = $this->isRequiredOrNullable($required);

        return [
            'page' => "$isRequired|integer",
            'per_page' => "$isRequired|integer",
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param \Illuminate\Contracts\Validation\Validator $validator
     * @return void
     *
     * @throws ValidationException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new ValidationException($validator->errors()->messages());
    }

    /**
     * Determine if the request passes the authorization check.
     *
     * @return bool
     */
    protected function passesAuthorization()
    {
        if (method_exists($this, 'authorize')) {
            return $this->container->call([$this, 'authorize']);
        }

        return true;
    }
}
