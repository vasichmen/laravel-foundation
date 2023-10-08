<?php


namespace App\Traits;


use App\Exceptions\User\InvalidCredentialsException;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Support\Str;
use IPPU\Foundation\Exceptions\RefreshTokenExpiredException;
use Laravel\Passport\Passport;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CryptTrait;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use UnexpectedValueException;

trait OAuthTrait
{
    use CryptTrait;


    public function attemptCreate($grantType, array $data): array
    {
        return $this->proxy($grantType, $data);
    }

    public function attemptRefresh($refreshToken): array
    {
        return $this->proxy('refresh_token', [
            'refresh_token' => $refreshToken,
        ]);
    }

    /**Выпуск токена для заданного пользвоателя
     * @param  User  $user
     * @return array
     */
    protected function issueToken(User $user): array
    {
        $accessToken = $user->createToken('token');

        $refreshTokenExpiresAt = Carbon::now()->addMinutes(config('auth.guards.api.refresh_token_expire'));
        $refreshToken = Passport::refreshToken();
        $refreshToken->fill([
            'id' => Str::lower(Str::random(80)),
            'access_token_id' => $accessToken->token->id,
            'revoked' => false,
            'expires_at' => $refreshTokenExpiresAt, //дата протухания токена
        ]);
        $refreshToken->save();

        $this->setEncryptionKey(Passport::tokenEncryptionKey(app('encrypter'))); //нужно установить ключ шифрования, чтоб работал метод encrypt

        $refreshTokenPayload = [
            'client_id' => config('oauth.client_id'),
            'refresh_token_id' => $refreshToken->id,
            'access_token_id' => $accessToken->token->id,
            'scopes' => [],
            'user_id' => $user->id,
            'expire_time' => $refreshTokenExpiresAt->getTimestamp(), //timestamp протухания токена
        ];

        return [
            'access_token' => $accessToken->accessToken,
            'refresh_token' => $this->encrypt(json_encode($refreshTokenPayload)),
            'expires_in' => config('auth.guards.api.access_token_expire') * 60, //секунды
            'token_type' => 'Bearer',
        ];
    }

    /**отправка запроса на api паспорта
     * @throws RefreshTokenExpiredException
     * @throws InvalidCredentialsException
     * @throws Exception
     */
    private function proxy($grantType, array $data = []): array
    {
        $data = [
            ...$data,
            'grant_type' => $grantType,
            'client_id' => config('oauth.client_id'),
            'client_secret' => config('oauth.client_secret'),
        ];

        return $this->dispatchRequestToAuthorizationServer($this->createRequest($data));
    }

    /**
     * Create a request instance
     *
     * @param  array  $params
     * @return ServerRequest
     */
    protected function createRequest(array $params): ServerRequest
    {
        return (new ServerRequest('POST', 'not-important'))->withParsedBody($params);
    }

    /**
     * @throws RefreshTokenExpiredException
     * @throws OAuthServerException
     * @throws InvalidCredentialsException
     */
    private function dispatchRequestToAuthorizationServer(ServerRequest $request): array
    {
        try {
            $response = app(AuthorizationServer::class)->respondToAccessTokenRequest($request, new Response());

            $body = $response->getBody()->__toString();
            return json_decode($body, true);
        }
        catch (OAuthServerException $e) {
            throw match ($e->getErrorType()) {
                'invalid_request' => new RefreshTokenExpiredException(),
                'invalid_grant' => new InvalidCredentialsException(),
                default => $e,
            };
        }
    }

    /**Проверка времени жизни и шифрования access_token без запроса в БД
     * @param  string  $accessToken
     * @return bool|array
     */
    protected function testAccessToken(string $accessToken): bool|array
    {
        try {
            return (array) JWT::decode($accessToken, new Key(file_get_contents(storage_path('oauth-public.key')), 'RS256'));
        }
        catch (SignatureInvalidException|ExpiredException|BeforeValidException|UnexpectedValueException) {
            return false;
        }
    }

}
