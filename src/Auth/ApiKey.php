<?php

namespace JplCodes\Bloomerang\Auth;

use Illuminate\Http\Client\PendingRequest;
use JplCodes\Bloomerang\Exceptions\MissingCredentials;

/**
 * Authenticates with Bloomerang's API key, sent as the X-API-KEY header.
 */
final class ApiKey implements Authenticator
{
    public function __construct(
        #[\SensitiveParameter] private readonly ?string $key,
    ) {}

    public function authenticate(PendingRequest $request): PendingRequest
    {
        if ($this->key === null || trim($this->key) === '') {
            throw new MissingCredentials('A Bloomerang API key is required to make this call.');
        }

        return $request->withHeader('X-API-KEY', $this->key);
    }
}
