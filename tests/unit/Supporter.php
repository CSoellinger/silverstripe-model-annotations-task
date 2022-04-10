<?php

namespace CSoellinger\SilverStripe\ModelAnnotation\Test\Unit;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ManyManyList;

/**
 * @internal Testing model
 *
 * @method ManyManyList Supports() ...
 */
class Supporter extends DataObject
{
    /**
     * @var array<string,string> undocumented variable
     */
    private static $belongs_many_many = [
        'Supports' => Team::class . '.Supporters',
    ];
}
