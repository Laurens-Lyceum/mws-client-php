<?php
declare(strict_types=1);

namespace LaurensLyceum\MWS\Client;

use Exception;
use InvalidArgumentException;
use LaurensLyceum\MWS\Client\Exceptions\MWSEncodingException;
use LaurensLyceum\MWS\Client\Exceptions\MWSFailedRequestException;
use LaurensLyceum\MWS\Client\Exceptions\MWSInterpretationException;
use LaurensLyceum\MWS\Client\Exceptions\MWSNoCredentialsException;
use LogicException;
use SensitiveParameter;
use SimpleXMLElement;

/** STUB phpdoc MWSClient */
class MWSClient
{

    // Session tokens from `Algemeen.Login` expire, stateless auth makes more sense
    private ?string $username = null;
    private ?string $password = null;

    public function __construct(
        /**
         * @example Production: `https://[school].swp.nl:8800`
         * @example Test: `https://[school].swp.nl:8801`
         */
        private readonly string $baseUrl
    )
    {
        // SECURITY Check https://portswigger.net/web-security/ssrf/url-validation-bypass-cheat-sheet
        // TEST Unicode & binary shenanigans
        $baseUrlComponents = parse_url($baseUrl);
        // `parse_url` doesn't properly validate the URL, so we need `filter_var` as well
        if ($baseUrlComponents === false || filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException("Malformed base URL: $baseUrl");
        } else if (!isset($baseUrlComponents["scheme"]) || $baseUrlComponents["scheme"] !== "https") {
            throw new InvalidArgumentException("Invalid base URL, must use HTTPS: $baseUrl");
        } else if (isset($baseUrlComponents["query"]) || isset($baseUrlComponents["fragment"])) {
            throw new InvalidArgumentException("Invalid base URL, must not contain query or fragment component: $baseUrl");
        }
    }


    public function setCredentials(string $username, #[SensitiveParameter] string $password): void
    {
        $this->username = $username;
        $this->password = $password;
    }

    public function clearCredentials(): void
    {
        $this->username = null;
        $this->password = null;
    }

    public function hasCredentials(): bool
    {
        $hasUsername = $this->username !== null;
        $hasPassword = $this->password !== null;
        if ($hasUsername !== $hasPassword) {
            throw new LogicException("Mismatched null-ness of username ($hasUsername) and password ($hasPassword)");
        }
        return $hasUsername;
    }


    /**
     * STUB phpdoc getData
     *
     * @param string $layout
     * @param array $parameters
     * @return array
     *
     * @throws MWSNoCredentialsException
     * @throws MWSEncodingException
     * @throws MWSFailedRequestException
     * @throws MWSInterpretationException
     */
    public function getData(string $layout, array $parameters): array
    {
        if (!$this->hasCredentials()) {
            throw new MWSNoCredentialsException();
        }

        if (array_is_list($parameters)) {
            throw new InvalidArgumentException("Parameters must be an associative array, got keys: " . implode(", ", array_keys($parameters)));
        }

        return $this->call(
            "Data",
            "GetData",
            [
                "Layout" => $layout,
                "Parameters" => $parameters
            ]
        );
    }

    // STUB Implement convenience methods for the other services


    /**
     * Make a call to MWS.
     *
     * @param string $library
     * @param string $function
     * @param array $parameters Associative array of query parameters.
     * See {@link MWSEncoder::encodeParameterValue()} for the encoding rules.
     * The following parameters are handled automatically and should not be supplied manually: `Library`, `Function`, `Type` and `SessionToken`.
     * @param int $timeout Timeout in seconds. Default is 10 seconds.
     *
     * @return array The parsed response table. See {@link MWSInterpreter::parseResponseTable()} for the mapping methodology.
     *
     * @throws MWSEncodingException
     * @throws MWSFailedRequestException
     * @throws MWSInterpretationException
     *
     * @see MWSInterpreter
     */
    public function call(string $library, string $function, array $parameters, int $timeout = 10): array
    {
        // SECURITY Scrutinize for SSRF

        // Set up parameters
        $queryParams = [
            // Remember to update the docs if reserved parameters change!
            "Library" => $library,
            "Function" => $function,
            "Type" => "xml",
        ];

        if ($this->hasCredentials()) {
            $queryParams["SessionToken"] = "$this->username;$this->password";
        }

        $reservedParameters = [...array_keys($queryParams), "SessionToken"];
        foreach ($parameters as $key => $value) {
            // TEST Reserved key handling
            if (in_array($key, $reservedParameters)) {
                throw new InvalidArgumentException("MWS call parameter '$key' is reserved");
            }
            // TEST Encoding
            $queryParams[$key] = MWSEncoder::encodeParameterValue($value);
        }


        // Make the call to MWS
        $url = $this->baseUrl . "?" . http_build_query($queryParams);
        // OPTIMIZE Reuse curl handle?
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_URL => $url,
            CURLOPT_TIMEOUT => $timeout,
            // TODO Response parsing should eventually be stream-based to avoid hogging memory
            CURLOPT_RETURNTRANSFER => true,
            // CHECK All curl options
        ]);

        $response = curl_exec($curl);
        if ($response === false) {
            $error = curl_error($curl);
            $errno = curl_errno($curl);
            throw new MWSFailedRequestException("cURL error: $error ($errno)");
        }

        $responseCode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if ($responseCode !== 200) {
            throw new MWSFailedRequestException("Unexpected response code: $responseCode", $response);
        }


        // Interpret MWS' rambling
        try {
            // XXE prevented by default since PHP 8.0
            // https://www.php.net/manual/en/function.libxml-disable-entity-loader.php
            $xml = new SimpleXMLElement($response);
        } catch (Exception $e) {
            // TEST Malformed XML
            throw new MWSInterpretationException("Could not parse XML", $response, $e);
        }

        return MWSInterpreter::interpretResponse($xml);
    }

}
