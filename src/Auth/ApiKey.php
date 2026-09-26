<?php

namespace JplCodes\Bloomerang\Auth;

use Illuminate\Http\Client\PendingRequest;
use JplCodes\Bloomerang\Exceptions\MissingCredentials;
use SensitiveParameterValue;

/**
 * Authenticates with Bloomerang's API key, sent as the X-API-KEY header.
 */
final class ApiKey implements Authenticator
{
    /**
     * Held as a SensitiveParameterValue so it never shows in dumps, var_export, or serialized payloads.
     */
    private readonly SensitiveParameterValue $key;

    public function __construct(#[\SensitiveParameter] ?string $key)
    {
        $this->key = new SensitiveParameterValue($key);
    }

    public function authenticate(PendingRequest $request): PendingRequest
    {
        if (trim((string) $this->key->getValue()) === '') {
            throw new MissingCredentials('A Bloomerang API key is required to make this call.');
        }

        return $request->withHeader('X-API-KEY', $this->key->getValue());
    }
}
