<?php

namespace CSoellinger\SilverStripe\ModelAnnotations\Test;

class PhpUnitHelper
{
    public static string $phpSapiName = 'cli';
}

namespace SilverStripe\Core;

use CSoellinger\SilverStripe\ModelAnnotations\Test\PhpUnitHelper;

function php_sapi_name(): string
{
    return PhpUnitHelper::$phpSapiName;
}
