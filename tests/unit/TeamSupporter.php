<?php

namespace CSoellinger\SilverStripe\ModelAnnotation\Test\Unit;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * @property int       $Ranking     Ranking ...
 * @property int       $TeamID      Team ID
 * @property Supporter $Supporter   Has one Supporter
 */
class TeamSupporter extends DataObject implements TestOnly
{
    /**
     * @var array<string,string> undocumented variable
     */
    private static $db = [
        'Ranking' => 'Int',
    ];

    /**
     * @var array<string,string> undocumented variable
     */
    private static $has_one = [
        'Team' => Team::class,
        'Supporter' => Supporter::class,
    ];
}
