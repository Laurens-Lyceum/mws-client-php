<?php
declare(strict_types=1);

namespace LaurensLyceum\MWS\Client;

use LaurensLyceum\MWS\Client\Exceptions\MWSEncodingException;
use Stringable;

/**
 * STUB phpdoc MWSEncoder
 * @see MWSClient::call()
 */
class MWSEncoder
{

    /**
     * Encode a value for use as a parameter of an {@link MWSClient::call()}.
     * Note that this value is not yet URL encoded.
     *
     * Values must be of one of the following types: `string`, `Stringable`, `int`, `float`, `bool`, `null` or `array`.
     * 1. `array` values will be encoded with {@link self::encodeArray()}
     * 2. Other values will be encoded with {@link self::encodeScalarish()}.
     *
     * @param mixed $value
     * @return string
     * @throws MWSEncodingException
     *
     * @see MWSClient::call()
     * @see self::encodeScalarish()
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
     *  1. `Stringable`, `int` and `float` are cast to a `string`.
     *  2. `null` values are converted to an empty string.
     *  3. `bool` values are converted to `0` or `1`, corresponding to SQL Server's representation of `FALSE` and `TRUE`, respectively.
     *
     * @param mixed $value
     * @return string
     * @throws MWSEncodingException
     *
     * @see encodeParameterValue()
     */
    public static function encodeScalarish(mixed $value): string
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
            throw new MWSEncodingException("Value is of type '$type', expected string, Stringable, int, float, bool or null", $value);
        }
    }

    /**
     * Encode values of type `array`.
     *  1. Sequential arrays will be encoded as: `value,value,...`.
     *  2. Associative arrays will be encoded as: `key=value;key=value;...`.
     * Each element is encoded using {@link self::encodeScalarish()}.
     *
     * @param array $value
     * @return string
     * @throws MWSEncodingException
     *
     * @see encodeParameterValue()
     * @see encodeScalarish()
     */
    public static function encodeArray(array $value): string
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
                        throw new MWSEncodingException("Encoded elements in sequential array must not contain special character: ','", $encodedElement);
                    }
                    // Note that `encodeScalarish` doesn't behave the same as `implode`
                    $encodedValue .= ",$encodedElement";
                } else {
                    // foo=bar;baz=qux
                    if (str_contains($encodedElement, ';') || str_contains($encodedElement, '=')) {
                        // Don't directly log encoded value, it could be something sensitive like a password
                        throw new MWSEncodingException("Encoded elements in associative array must not contain special characters: ';' or '='", $encodedElement);
                    }
                    $encodedValue .= ";$key=$encodedElement";
                }
            } catch (MWSEncodingException $e) {
                throw new MWSEncodingException("Could not encode element at '$key'", $value, $e);
            }
        }

        // Cut off first separator
        return mb_substr($encodedValue, 1);
    }

}
