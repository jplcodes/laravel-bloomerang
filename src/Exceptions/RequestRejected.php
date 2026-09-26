<?php

namespace JplCodes\Bloomerang\Exceptions;

/**
 * Bloomerang rejected the call for a reason other than authentication, a missing
 * resource, rate limiting, or a server error (any other 4xx status).
 */
final class RequestRejected extends BloomerangException {}
