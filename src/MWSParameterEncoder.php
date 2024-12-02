<?php
declare(strict_types=1);

namespace LaurensLyceum\MWS\Client;

use LaurensLyceum\MWS\Client\Exceptions\MWSParameterEncodingException;
use Stringable;

/**
 * @see MWSParameterEncoder::encodeParameterValue()
 * @see MWSClient::call()
 */
class MWSParameterEncoder
{

    /**
     * Encode a value for use as a parameter of an MWS call.
     * Note that this value is not yet URL encoded.
     *
     * Values must be of one of the following types: `string`, `Stringable`, `int`, `float`, `bool`, `null` or `array`.
     * 1. `Stringable`, `int` and `float` are cast to a `string`.
     * 2. `null` values are converted to an empty string.
     * 3. `bool` values are converted to `0` or `1`, corresponding to SQL Server's representation of `FALSE` and `TRUE`, respectively.
     * 4. Sequential `array` values will be {@link implode() imploded} with a comma.
     * Elements must be of one of the aforementioned types, except `array`.
     * 5. Associative `array` values will be encoded as `key=value;key=value;...`.
     * Elements must be of one of the aforementioned types, except `array`.
     *
     * @throws MWSParameterEncodingException
     */
    public static function encodeParameterValue(mixed $value): string
    {
        // TEST URL special characters like & and #
        // TEST Unicode shenanigans
        // TEST Binary shenanigans
        return is_array($value) ? self::encodeArray($value) : self::encodeScalarish($value);
    }

    /**
     * Encode values of type `string`, `Stringable`, `int`, `float`, `bool` or `null`.
     *
     * @param mixed $value
     * @return string
     * @throws MWSParameterEncodingException
     *
     * @see encodeParameterValue()
     */
    private static function encodeScalarish(mixed $value): string
    {
        if ($value === null) {
            return '';
        } else if (is_bool($value)) {
            return $value ? '1' : '0';
        } else if (is_scalar($value) || $value instanceof Stringable) {
            // int, float or string(able) (bools were already handled)
            return (string)$value;
        } else {
            $type = gettype($value);
            throw new MWSParameterEncodingException("Value is of type '$type', expected string, Stringable, int, float, bool, null or array", $value);
        }
    }

    /**
     * Encode values of type `array`.
     *
     * @param array $value
     * @return string
     * @throws MWSParameterEncodingException
     *
     * @see encodeParameterValue()
     * @see encodeScalarish()
     */
    private static function encodeArray(array $value): string
    {
        $encodedValue = '';
        $sequential = array_is_list($value);

        foreach ($value as $key => $element) {
            try {
                $encodedElement = self::encodeScalarish($element);
                if ($sequential) {
                    // foo,bar,baz
                    if (str_contains($encodedElement, ',')) {
                        // Don't directly log encoded value, it could be something sensitive like a password
                        throw new MWSParameterEncodingException("Encoded elements in sequential array must not contain special character: ','", $encodedElement);
                    }
                    // Note that `encodeScalarish` doesn't behave the same as `implode`
                    $encodedValue .= ",$encodedElement";
                } else {
                    // foo=bar;baz=qux
                    if (str_contains($encodedElement, ';') || str_contains($encodedElement, '=')) {
                        // Don't directly log encoded value, it could be something sensitive like a password
                        throw new MWSParameterEncodingException("Encoded elements in associative array must not contain special characters: ';' or '='", $encodedElement);
                    }
                    $encodedValue .= ";$key=$encodedElement";
                }
            } catch (MWSParameterEncodingException $e) {
                throw new MWSParameterEncodingException("Could not encode element at '$key'", $value, $e);
            }
        }

        // Cut off first separator
        return mb_substr($encodedValue, 1);
    }

}
