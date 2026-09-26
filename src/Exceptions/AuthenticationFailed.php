<?php

namespace JplCodes\Bloomerang\Exceptions;

/**
 * Bloomerang rejected the credentials used for the call (HTTP 401 or 403).
 */
final class AuthenticationFailed extends BloomerangException {}
