<?php
declare(strict_types=1);

namespace LaurensLyceum\MWS\Client\Exceptions;

use LaurensLyceum\MWS\Client\MWSClient;
use SensitiveParameter;
use SimpleXMLElement;
use Throwable;

/**
 * Exception thrown when the response to an {@link MWSClient::call() MWS call} could not be interpreted.
 *
 * @see MWSClient::call()
 * @see MWSRequestFailedException For when a request could not be completed at all.
 */
class MWSResponseInterpretationException extends MWSClientException
{
    // TODO Split into more descriptive subtypes based on exception types/error codes

    public function __construct(
        string $message,
        /** The segment of the response that caused the interpretation snafu. */
        #[SensitiveParameter] public readonly string|SimpleXMLElement $responseSegment,
        Throwable $previous = null
    )
    {
        parent::__construct($message, 0, $previous);
    }

}
