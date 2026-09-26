<?php

namespace JplCodes\Bloomerang\Auth;

use Illuminate\Http\Client\PendingRequest;
use JplCodes\Bloomerang\Exceptions\MissingCredentials;

/**
 * Applies credentials to an outgoing Bloomerang request.
 */
interface Authenticator
{
    /**
     * @throws MissingCredentials
     */
    public function authenticate(PendingRequest $request): PendingRequest;
}
