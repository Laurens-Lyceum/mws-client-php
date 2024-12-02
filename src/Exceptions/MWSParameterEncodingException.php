<?php
declare(strict_types=1);

namespace LaurensLyceum\MWS\Client\Exceptions;

use LaurensLyceum\MWS\Client\MWSParameterEncoder;
use SensitiveParameter;
use Throwable;

/**
 * Exception thrown when the value of a parameter for an {@link MWSClient::call() MWS call} could not be encoded.
 *
 * @see MWSParameterEncoder
 */
class MWSParameterEncodingException extends MWSClientException
{

    public function __construct(
        string $message,
        #[SensitiveParameter] public readonly mixed $value,
        Throwable $previous = null
    )
    {
        parent::__construct($message, 0, $previous);
    }

}
