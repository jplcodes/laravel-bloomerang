<?php

namespace JplCodes\Bloomerang\Exceptions;

/**
 * Bloomerang sent back something the package could not make sense of, such as a
 * successful response that was not valid JSON, or data missing an expected shape.
 */
final class UnexpectedResponse extends BloomerangException {}
