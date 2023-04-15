+ [Описание](#описание)
+ [Установка](#установка)
+ [Примеры](#примеры-использования)
  * [Сервис-провайдер](#appserviceprovider)
  * [Базовая авторизация](#базовая-авторизация)
  * [Полезные трейты](#полезные-трейты)
    * [Первод Enum](#enumtranslatable)
    * [Значения Enum](#enumvalues)
    * [Обновление полей enum в миграциях](#enumdbcheckalterable)
    * [Партиционирование таблиц](#partitionedbyhash)
    * [Логирование](#logger)
  * [Реквест и DTO](#реквест-и-dto)
  * [Модель](#модель)
  * [Репозиторий](#репозиторий)
  * [Ресурс](#ресурс)
  * [Презентер](#презентер)


## Описание

Пакет с базовыми классами репозиториев, сервисов, реквестов для приложений на laravel. Базовый функционал включает в себя наиболее часто требующиеся методы и классы в любом приложении. 

## Установка

* Установить пакет

```shell
composer require vasichmen/laravel-foundation
```

## Примеры использования

### AppServiceProvider

```php
use Laravel\Foundation\Abstracts\AbstractAppServiceProvider;

class AppServiceProvider extends AbstractAppServiceProvider
{
    protected array $modelList = [
        User::class, //репозитории подключатся по классам моделей из App\Repositories как singleton, будут доступны через app(UserRepository::class)
    ];

    protected array $serviceList = [
        AuthService::class,  //сервисы подключаются как singleton и доступны через app(AuthService::class)
    ];

}
```

### Базовая авторизация

Создаем новый мидлвар, наследуясь
от [AbstractBasicAuthMiddleware.php](src%2FAbstracts%2FAbstractBasicAuthMiddleware.php)

```php
use Laravel\Foundation\Abstracts\AbstractBasicAuthMiddleware;

class ExternalApiAuthMiddleware extends AbstractBasicAuthMiddleware
{
    protected string $key = 'external_api';

}
```

Регистрируем в Http\Kernel.php в поле $routeMiddleware:

```php
'external-auth' => ExternalBasicAuthMiddleware::class,
```

После чего надо добавить в конфиг auth.php секцию с настройками логинов и паролей:

```php
 'basic' => [
        'external_api' => [
            'user' => env('EXTERNAL_API_USER', 'user'),
            'password' => env('EXTERNAL_API_PASSWORD', 'password'),
        ],
    ],
```

Используем как обычный мидлвар на любых роутах или группах:

```php
Route::prefix('external')
    ->middleware('external-auth')
    ->group(function () {
        Route::get('test-user-token', [AuthController::class, 'testUserToken']);
    });

```


### Полезные трейты

#### [EnumTranslatable](src%2FTraits%2FEnum%2FEnumTranslatable.php)

Трейт, который можно подключить в любой enum, он добавляет метод trans() в элемент перечисления.
Чтобы заработал перевод надо добавить файл `/lang/<lang>/enums.php` с содержимым примерно такого вида:

```php
return [
    SystemStereotypeEnum::class => [
        SystemStereotypeEnum::Typical->value => 'Типовая система',
        SystemStereotypeEnum::Real->value => 'Реальная система',
    ],
]
```

Получить перевод можно так:

```php
 SystemStereotypeEnum::Typical->trans($args);
```

Параметром `$args` можно передать массив аргументов для функции laravel trans().
Этот метод используется в методе AbstractResource->getEnum()

#### [EnumValues](src%2FTraits%2FEnum%2FEnumValues.php)

Добавляет к перечислениям статический метод values(), который возвращает массив всех значений этого перечисления

#### [EnumDBCheckAlterable](src%2FTraits%2FMigration%2FEnumDBCheckAlterable.php)

Добавляет в миграцию метод alterEnum() для изменения поля с ограничением значений.

```php
$this->alterEnum('subscriptions', 'type', ['tag','space','user']);
```

#### [PartitionedByHash](src%2FTraits%2FMigration%2FPartitionedByHash.php)
Добавляет в миграцию метод `makePartitionedTable`, создающий таблицу, разделенную на партиции (только для Postgres)

```php
$this->makePartitionedTable('materials', 100, function (Blueprint $table) {
            $table->string('name');
            $table->uuid('owner_id');
            $table->uuid('author_id');
            $table->timestamps();

            $table->foreign('owner_id')->references('id')->on('users');
            $table->foreign('author_id')->references('id')->on('users');
        });
```

#### [Logger](src%2FTraits%2FLogger.php)

Добавляет метод formatLogMessage, который работает по принципу sprintf, но может красиво форматировать эксепшены,
массивы, коллекции итд.

### Реквест и DTO

Реквест определяет правила валидации и (при необходимости) сообщения об ошибках, наследуется
от [AbstractRequest](src%2FAbstracts%2FAbstractRequest.php).
DTO определяет структуру данных для прозрачности передачи данных внутри приложения, наследуется
от [AbstractDto](src%2FAbstracts%2FAbstractDto.php).

Для подключения валидации надо добавить `\Laravel\Foundation\ServiceProviders\RequestServiceProvider::class` в конфиг `app.php`

Чтоб подключить DTO к реквесту надо в реквесте переопределить поле $dtoClassName - имя класса DTO.
При вызове метода `$request->validated()` проверяется существование класса DTO, его принадлежность к базовому классу и в
конструктор передается массив параметров из реквеста.
Внутри DTO в конструкторе ключи массива из реквеста приводятся в camelCase и данные из них записываются в
соответствующие поля класса DTO.
Таким образом для создания DTO достаточно только определить список полей с такими же именами, как в реквесте, но в
camelCase.
Для обратного преобразования в массив в DTO есть метод toArray().

#### Пример реквеста с сортировкой и постраничкой

```php
use App\DTO\Requests\RefreshTokenRequestDTO;
use Laravel\Foundation\Abstracts\AbstractRequest;


class RefreshTokenRequest extends AbstractRequest
{
    use \Laravel\Foundation\Traits\RequestSortable;

    protected ?string $dtoClassName = RefreshTokenRequestDTO::class;
    
    public function rules()
    {
        return [
            'some_long_parameter' => 'required',
            ...$this->sorted(),
            ...$this->paginated(),
        ];
    }
}
```

#### Пример DTO

```php
use Laravel\Foundation\Abstracts\AbstractDto;

class RefreshTokenRequestDTO extends AbstractDto
{
    public string $someLongParameter;
    public array $sort;
    public int $page;
    public int $perPage;
}
```

Такой реквест при вызове у него метода validated() вернет объект DTO. Чтобы возвращался массив, надо убрать указание DTO
из реквеста. В поле `sort` в DTO вернется массив ключ=>значение, где ключ - название столбца, значение - направление сортировки

Если в классе DTO поле не будет найдено, то сгенерируется
исключение [DTOPropertyNotExists](src%2FExceptions%2FDTOPropertyNotExists.php).
Такое поведение задано по умолчанию для самопроверки, его можно изменить, переопределив метод parseData(), передав
вторым параметром false, например вот так:

```php
class RefreshTokenRequestDTO extends AbstractDto
{
    protected function parseData(array $data, bool $throwIfNoProperty = true): void
    {
        parent::parseData($data, false); 
    }
}
```

### Модель

```php
class User extends \Laravel\Foundation\Abstracts\AbstractModel
{
 ...
 
   // Можно переопределить метод сброса кастомного кэша
   // Метод вызывается при обновлении/удалении модели, в нем надо определить сброс кэша по кастомным тегам 
   public static function invalidateCustomCache(AbstractModel $user): void 
    {
        /** @var User $user */
        Cache::tags(['tag_1','tag_2'])
            ->forget(self::getCacheKey('some key data'));
    }
}
```

### Репозиторий

Все репозитории наследуются от [AbstractRepository](src%2FAbstracts%2FAbstractRepository.php).
Основной метод реквеста - getList. В него передаются параметры фильтрации, кэширования, сортировки и пагинации.
Если передан параметр paginate:true, то возвращается LengthAwarePaginator, в остальных случаях возвращается коллекция
моделей.

Помимо этого в репозитории определены основные CRUD операции с моделями, принимающие как id записи, так и модель.

Подробное описание в phpDoc

#### Использование кэширования при работе с моделями

Подключить провайдер `\Laravel\Foundation\ServiceProviders\CacheServiceProvider::class` в конфиг app.php

Простое кэширование с автоматическим сбросом при вызове событий eloquent: updated,created,deleted:

```php

class SomeRepository {
....

$result = $this->model
            ->cacheFor(config('cache.ttl'))
            ->where('feed_id',$feedId)
            ->get();

...
```

Установка кастомных тегов кэша. Такой кэш автоматически сбрасываться не будет, для сброса надо переопределять метод
invalidateCustomCache в модели

```php

class SomeRepository extends \Laravel\Foundation\Abstracts\AbstractRepository {
....

$result = $this->model
            ->cacheFor(config('cache.ttl'))
            ->cacheTags([self::getCacheTag('feeds', $feedId)]) //устанавливаем кастомные теги
            ->cacheKey(self::getCacheKey($feedId)) //задаем ключ кэша 
            ->where('feed_id',$feedId)
            ->get();
...
```

Для корректного хранения кэша в одной БД redis от нескольких микросервисов надо задать переменную SERVICE_NAME в .env

Методы getCacheTag, getCacheKey подключаются из трейта [CacheKeysTrait](src%2FTraits%2FCache%2FCacheKeysTrait.php)

### Ресурс

Ресурсы делаются под каждую возвращаемую сущность (обычно это модели). Все ресурсы наследуются
от [AbstractResource](src%2FAbstracts%2FAbstractResource.php).
По сути ресурсы повторяют собой стандартные laravel JsonResource, но дополняются некоторыми методами.

Пример ресурса:

```php
use Laravel\Foundation\Abstracts\AbstractResource;

class UserResource extends AbstractResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'login' => $this->login,
            ...$this->getEnum('information_processed')
            ...$this->getRelation('roles', RoleResource::class),
        ];
    }
}
```

Метод `$this->getEnum` рендерит enum в структуру вида `{code:<enum_code>,name:<название enum>}`. Для получения названия
вызывается метод `trans` трейта [EnumTranslatable](src%2FTraits%2FEnumTranslatable.php).
Если enum не расширен этим трейтом, то будет ошибка `NeedTranslatableEnumException`.

Метод `$this->getRelation` добавляет в выдачу связь eloquent модели. Первым параметром передается название связи,
вторым - класс ресурса для элементов этой связи.
Если связь не загружена, то ключ добавлен не будет. Если связь загружена, то в ответ добавится ключ с названием связи.

Если надо вывести коллекцию элементов через ресурс, то есть стандартный статический метод collection:

```php
return new DataResultPresenter(
            'bookmarks' => BookmarkResource::collection($bookmarkCollection),
        );
```

### Презентер

Обычно не требуется создавать кастомные презенторы и всегда
пользуемся [DataResultPresenter](src%2FPresenters%2FDataResultPresenter.php) для вывода простых данных или расширяем
этот класс.

```php
  return new DataResultPresenter([
            'token' => new TokenResource($token),
            'user' => new UserResource($user),
        ]);
```

#### Презентер с пагинацией

В качестве основного презентера с пагинацией
выступает [PaginatedDataPresenter](src%2FPresenters%2FPaginatedDataPresenter.php)

Пример использования без агрегаций:

```php
return new PaginatedDataPresenter($lengthAwarePaginatedData, null, SomeModelResource::class);
```

Первым параметром передается объект LengthAwarePaginator, полученный из метода getList репозитория. Если коллекция
получена другим способом, то можно установить в пагинатор нужную коллекцию через метод setCollection.

Вторым параметром передается коллекция или массив агрегаций (то, что рендерится в ключе filters). Обычно агрегации это
возможные значения фильтров при установленных текущих фильтрах.
Стандартная реализация заточена под ответ от elasticsearch, но можно переопределить метод aggregationToArray и задать
любую другую логику.

Третьим параметром передается класс ресурса, который надо применить к элементам коллекции.
Если надо рендерить элемент массива не через ресурс, а каким-то другим способом, то можно передать третьим параметром
null и переопределить метод bodyToArray, задать в нем свою логику обработки элемента коллекции. 

