<?php

namespace JplCodes\Bloomerang\Auth;

use Illuminate\Http\Client\PendingRequest;
use JplCodes\Bloomerang\Exceptions\MissingCredentials;
use SensitiveParameterValue;

/**
 * Authenticates with an OAuth access token, sent as a bearer Authorization header.
 */
final class BearerToken implements Authenticator
{
    /**
     * Held as a SensitiveParameterValue so it never shows in dumps, var_export, or serialized payloads.
     */
    private readonly SensitiveParameterValue $token;

    public function __construct(#[\SensitiveParameter] ?string $token)
    {
        $this->token = new SensitiveParameterValue($token);
    }

    public function authenticate(PendingRequest $request): PendingRequest
    {
        if (trim((string) $this->token->getValue()) === '') {
            throw new MissingCredentials('A bearer token is required to make this call.');
        }

        return $request->withToken($this->token->getValue());
    }
}
