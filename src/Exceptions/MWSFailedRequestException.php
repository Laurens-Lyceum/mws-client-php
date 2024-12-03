<?php
declare(strict_types=1);

namespace LaurensLyceum\MWS\Client\Exceptions;

use LaurensLyceum\MWS\Client\MWSClient;
use SensitiveParameter;
use Throwable;

/**
 * Exception thrown when a {@link MWSClient::call() request to MWS} fails outright.
 *
 * @see MWSClient::call()
 * @see MWSInterpretationException For when a request was completed, but the response doesn't make sense.
 */
class MWSFailedRequestException extends MWSClientException
{

    public function __construct(
        string $message,
        #[SensitiveParameter] public readonly string|null $response = null,
        Throwable $previous = null
    )
    {
        parent::__construct($message, 0, $previous);
    }

}
