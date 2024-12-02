<?php
declare(strict_types=1);

namespace LaurensLyceum\MWS\Client\Exceptions;

use LaurensLyceum\MWS\Client\MWSClient;

/**
 * Exception thrown when {@link MWSClient} expects to have credentials, but doesn't.
 *
 * @see MWSClient::setCredentials()
 */
class MWSNoCredentialsException extends MWSClientException
{

    public function __construct()
    {
        parent::__construct("MWS client does not have credentials");
    }

}
