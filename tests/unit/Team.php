<?php

namespace CSoellinger\SilverStripe\ModelAnnotations\Test\Unit;

use SilverStripe\Assets\Image;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class Team extends DataObject implements TestOnly
{
    /**
     * @var array<string,string> undocumented variable
     */
    private static $db = [
        'Name' => 'Varchar(255)',
        'Origin' => 'Varchar(255)',
        'LinkTracking' => 'Boolean',
    ];

    /**
     * @var array<string,string> undocumented variable
     */
    private static $has_many = [
        'Players' => Player::class,
        'FileTracking' => Image::class,
    ];

    /**
     * @var array<string,array<string,string>|string> undocumented variable
     */
    private static $many_many = [
        'Supporters' => [
            'through' => TeamSupporter::class,
            'from' => 'Team',
            'to' => 'Supporter',
        ],
        'Images' => Image::class,
    ];
}
