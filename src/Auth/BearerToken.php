<?php

namespace JplCodes\Bloomerang\Auth;

use Illuminate\Http\Client\PendingRequest;
use JplCodes\Bloomerang\Exceptions\MissingCredentials;

/**
 * Authenticates with an OAuth access token, sent as a bearer Authorization header.
 */
final class BearerToken implements Authenticator
{
    public function __construct(
        #[\SensitiveParameter] private readonly ?string $token,
    ) {}

    public function authenticate(PendingRequest $request): PendingRequest
    {
        if ($this->token === null || trim($this->token) === '') {
            throw new MissingCredentials('A bearer token is required to make this call.');
        }

        return $request->withToken($this->token);
    }
}
