<?php

namespace JplCodes\Bloomerang\Exceptions;

/**
 * No usable API key or bearer token was available, so nothing was sent to Bloomerang.
 */
final class MissingCredentials extends BloomerangException {}
