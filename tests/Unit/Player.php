<?php

namespace CSoellinger\SilverStripe\ModelAnnotations\Test\Unit;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * @internal Testing model
 *
 * @property string $Name ...
 */
class Player extends DataObject implements TestOnly
{
    /**
     * @var array<string,string> undocumented variable
     */
    private static $db = [
        'Name' => 'Varchar(255)',
    ];

    /**
     * @var array<string,string> undocumented variable
     */
    private static $has_one = [
        'Team' => Team::class . '.ID',
    ];
}
