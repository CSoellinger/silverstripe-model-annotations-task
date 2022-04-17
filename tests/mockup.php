<?php

namespace CSoellinger\SilverStripe\ModelAnnotation\Test;

class PhpUnitHelper
{
    public static string $phpSapiName = 'cli';
}

namespace SilverStripe\Core;

use CSoellinger\SilverStripe\ModelAnnotation\Test\PhpUnitHelper;

function php_sapi_name(): string
{
    return PhpUnitHelper::$phpSapiName;
}
