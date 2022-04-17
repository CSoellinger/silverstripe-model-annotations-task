<?php

namespace CSoellinger\SilverStripe\ModelAnnotations\Test\Unit;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ManyManyList;

/**
 * @internal Testing model
 *
 * @method ManyManyList Supports() ...
 */
class Supporter extends DataObject implements TestOnly
{
    /**
     * @var array<string,string> undocumented variable
     */
    private static $belongs_many_many = [
        'Supports' => Team::class . '.Supporters',
    ];
}
