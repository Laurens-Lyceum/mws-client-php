<?php
declare(strict_types=1);

namespace LaurensLyceum\MWS\Client\Exceptions;

use Exception;

/** Base class for all exceptions thrown by {@link MWSClient}. */
abstract class MWSClientException extends Exception
{
    // CHECK Messages are not binary safe?
}
