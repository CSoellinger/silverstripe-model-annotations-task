<?php

namespace CSoellinger\SilverStripe\ModelAnnotations\Util;

use ReflectionClass;
use ReflectionFunction;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;

/**
 * Helper class.
 */
class Util
{
    use Injectable;

    /**
     * Transform silver stripe db type to a php type.
     */
    public function silverStripeToPhpType(string $dataType): string
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
    public function fileByFqn(string $fqn): string
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
     */
    public function getNamespaceFromFqn(string $fqn): string
    {
        $pos = strrpos($fqn, '\\');

        if ($pos !== false) {
            return substr($fqn, 0, $pos);
        }

        return '\\';
    }

    /**
     * Get all classes from a namespace.
     *
     * @param string $namespace The namespace in which to search
     *
     * @return string[]
     */
    public function getClassesFromNamespace(string $namespace)
    {
        $namespace .= '\\';

        $classes = array_filter(ClassInfo::allClasses(), function (string $item) use ($namespace) {
            return substr($item, 0, strlen($namespace)) === $namespace;
        });

        /** @var string[] */
        return array_values($classes);
    }
}
