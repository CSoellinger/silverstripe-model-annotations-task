<?php

namespace CSoellinger\SilverStripe\ModelAnnotation\Util;

use ReflectionClass;
use ReflectionFunction;

/**
 * Helper class.
 */
class Util
{
    /**
     * Little helper for php7 support.
     */
    public static function strStartsWith(string $haystack, string $needle): bool
    {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }

    /**
     * Transform silver stripe db type to a php type.
     */
    public static function silverStripeToPhpTyping(string $dataType): string
    {
        $dataType = strtolower($dataType);
        $dataType = preg_replace(
            '/^(varchar|htmltext|text|htmlfragment|time|datetime|date|htmlvarchar|enum).*/m',
            'string',
            $dataType
        );
        $dataType = (string) preg_replace('/^(percentage|decimal|currency).*/m', 'float', (string) $dataType);

        return str_replace('boolean', 'bool', $dataType);
    }

    /**
     * Get the file path by the full qualified class name.
     *
     * @param string $fqn Full qualified class name
     */
    public static function fileByFqn(string $fqn): string
    {
        if (function_exists($fqn)) {
            $reflector = new ReflectionFunction($fqn);
        } elseif (class_exists($fqn)) {
            $reflector = new ReflectionClass($fqn);
        } else {
            return '';
        }

        return (string) $reflector->getFileName();
    }

    /**
     * Get namespace from class full qualified name.
     *
     * @param string $fqn Full qualified name
     *
     * @return string
     */
    public static function getNamespaceFromFqn(string $fqn): string
    {
        if ($pos = strrpos($fqn, '\\')) {
            return substr($fqn, 0, $pos);
        }

        return '\\';
    }
}
