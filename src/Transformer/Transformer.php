<?php

namespace NilPortugues\Api\Transformer;

use InvalidArgumentException;
use NilPortugues\Api\Mapping\Mapping;
use NilPortugues\Serializer\Serializer;
use NilPortugues\Serializer\Strategy\StrategyInterface;

abstract class Transformer implements StrategyInterface
{
    /**
     * @var Mapping[]
     */
    protected $mappings = [];
    /**
     * @var string
     */
    protected $firstUrl = '';
    /**
     * @var string
     */
    protected $lastUrl = '';
    /**
     * @var string
     */
    protected $prevUrl = '';
    /**
     * @var string
     */
    protected $nextUrl = '';
    /**
     * @var string
     */
    protected $selfUrl = '';

    /**
     * @param array $apiMappings
     */
    public function __construct(array $apiMappings)
    {
        $this->mappings = $apiMappings;
    }

    /**
     * @param string $self
     *
     * @throws \InvalidArgumentException
     */
    public function setSelfUrl($self)
    {
        $this->selfUrl = (string) $self;
    }

    /**
     * @param string $firstUrl
     *
     * @throws \InvalidArgumentException
     */
    public function setFirstUrl($firstUrl)
    {
        $this->firstUrl = (string) $firstUrl;
    }

    /**
     * @param string $lastUrl
     *
     * @throws \InvalidArgumentException
     */
    public function setLastUrl($lastUrl)
    {
        $this->lastUrl = (string) $lastUrl;
    }

    /**
     * @param string $nextUrl
     *
     * @throws \InvalidArgumentException
     */
    public function setNextUrl($nextUrl)
    {
        $this->nextUrl = (string) $nextUrl;
    }

    /**
     * @param string $prevUrl
     *
     * @throws \InvalidArgumentException
     */
    public function setPrevUrl($prevUrl)
    {
        $this->prevUrl = (string) $prevUrl;
    }

    /**
     * Represents the provided $value as a serialized value in string format.
     *
     * @param mixed $value
     *
     * @return string
     */
    abstract public function serialize($value);

    /**
     * Unserialization will fail. This is a transformer.
     *
     * @param string $value
     *
     * @throws InvalidArgumentException
     *
     *  @return array
     */
    public function unserialize($value)
    {
        throw new InvalidArgumentException(sprintf('%s does not perform unserializations.', __CLASS__));
    }

    /**
     * Converts a under_score string to camelCase.
     *
     * @param string $string
     *
     * @return string
     */
    protected function underscoreToCamelCase($string)
    {
        return str_replace(' ', '', ucwords(strtolower(str_replace(['_', '-'], ' ', $string))));
    }

    /**
     * Removes array keys matching the $unwatedKeys array by using recursion.
     *
     * @param array $array
     * @param array $unwantedKey
     */
    protected function recursiveUnset(array &$array, array $unwantedKey)
    {
        foreach ($unwantedKey as $key) {
            if (array_key_exists($key, $array)) {
                unset($array[$key]);
            }
        }

        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->recursiveUnset($value, $unwantedKey);
            }
        }
    }

    /**
     * Replaces the Serializer array structure representing scalar values to the actual scalar value using recursion.
     *
     * @param array $array
     */
    protected function recursiveSetValues(array &$array)
    {
        if (array_key_exists(Serializer::SCALAR_VALUE, $array)) {
            $array = $array[Serializer::SCALAR_VALUE];
        }

        if (is_array($array) && !array_key_exists(Serializer::SCALAR_VALUE, $array)) {
            foreach ($array as &$value) {
                if (is_array($value)) {
                    $this->recursiveSetValues($value);
                }
            }
        }
    }

    /**
     * Simplifies the data structure by removing an array level if data is scalar and has one element in array.
     *
     * @param array $array
     */
    protected function recursiveFlattenOneElementObjectsToScalarType(array &$array)
    {
        if (1 === count($array) && is_scalar(end($array))) {
            $array = array_pop($array);
        }

        if (is_array($array)) {
            foreach ($array as &$value) {
                if (is_array($value)) {
                    $this->recursiveFlattenOneElementObjectsToScalarType($value);
                }
            }
        }
    }

    /**
     * Renames a sets if keys for a given class using recursion.
     *
     * @param array  $array      Array with data
     * @param string $typeKey    Scope to do the replacement.
     * @param array  $replaceMap Array holding the value to replace
     */
    protected function recursiveRenameKeyValue(array &$array, $typeKey, array &$replaceMap)
    {
        $newArray = [];
        foreach ($array as $key => &$value) {
            if (!empty($replaceMap[$key]) && $typeKey == $value[Serializer::CLASS_IDENTIFIER_KEY]) {
                $key = $replaceMap[$key];
            }

            if (is_array($value)) {
                $this->recursiveRenameKeyValue($newArray[$key], $typeKey, $replaceMap);
            }
        }
        $array = $newArray;
    }

    /**
     * Removes a sets if keys for a given class using recursion.
     *
     * @param array  $array        Array with data
     * @param string $typeKey      Scope to do the replacement.
     * @param array  $keysToDelete Array holding the value to hide
     */
    protected function recursiveDeleteKeyValue(array &$array, $typeKey, array &$keysToDelete)
    {
        $newArray = [];
        foreach ($array as $key => &$value) {
            if (empty($keysToDelete[$key]) && $typeKey == $value[Serializer::CLASS_IDENTIFIER_KEY]) {
                $newArray[$key] = $value;
            }

            if (is_array($value)) {
                $this->recursiveRenameKeyValue($newArray[$key], $typeKey, $keysToDelete);
            }
        }
        $array = $newArray;
    }

    /**
     * Changes all array keys to under_score format using recursion.
     *
     * @param array $array
     */
    protected function recursiveSetKeysToUnderScore(array &$array)
    {
        $newArray = [];
        foreach ($array as $key => &$value) {
            $underscoreKey = $this->camelCaseToUnderscore($key);

            $newArray[$underscoreKey] = $value;
            if (is_array($value)) {
                $this->recursiveSetKeysToUnderScore($newArray[$underscoreKey]);
            }
        }
        $array = $newArray;
    }

    /**
     * Array's type value becomes the key of the provided array using recursion.
     *
     * @param array $array
     */
    protected function recursiveSetTypeAsKey(array &$array)
    {
        if (is_array($array)) {
            foreach ($array as &$value) {
                if (!empty($value[Serializer::CLASS_IDENTIFIER_KEY])) {
                    $key = $value[Serializer::CLASS_IDENTIFIER_KEY];
                    unset($value[Serializer::CLASS_IDENTIFIER_KEY]);
                    $value = [$this->namespaceAsArrayKey($key) => $value];

                    $this->recursiveSetTypeAsKey($value);
                }
            }
        }
    }

    /**
     * Given a class name will return its name without the namespace and in under_score to be used as a key in an array.
     *
     * @param string $key
     *
     * @return string
     */
    protected function namespaceAsArrayKey($key)
    {
        $keys = explode('\\', $key);
        $className = end($keys);

        return $this->camelCaseToUnderscore($className);
    }

    /**
     * Transforms a given string from camelCase to under_score style.
     *
     * @param string $camel
     * @param string $splitter
     *
     * @return string
     */
    protected function camelCaseToUnderscore($camel, $splitter = '_')
    {
        $camel = preg_replace(
            '/(?!^)[[:upper:]][[:lower:]]/',
            '$0',
            preg_replace('/(?!^)[[:upper:]]+/', $splitter.'$0', $camel)
        );

        return strtolower($camel);
    }
}
