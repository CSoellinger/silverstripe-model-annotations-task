<?php

namespace CSoellinger\SilverStripe\ModelAnnotation\Test\Unit;

use SilverStripe\ORM\DataObject;

/**
 * @property int       $Ranking     Ranking ...
 * @property Team      $Team        Has one Team
 * @property int       $TeamID      Team ID
 * @property Supporter $Supporter   Has one Supporter
 * @property int       $SupporterID Supporter ID
 */
class TeamSupporter extends DataObject
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
