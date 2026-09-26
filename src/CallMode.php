<?php

namespace JplCodes\Bloomerang;

/**
 * The two ways a call can be made: an interactive request or a background job.
 */
enum CallMode: string
{
    case Request = 'request';
    case Job = 'job';
}
