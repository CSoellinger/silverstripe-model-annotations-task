<?php

namespace SilverStripe\Core;

use CSoellinger\SilverStripe\ModelAnnotations\Test\PhpUnitHelper;

function php_sapi_name(): string
{
    return PhpUnitHelper::$phpSapiName;
}
