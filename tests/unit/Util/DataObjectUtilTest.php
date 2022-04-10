<?php

namespace CSoellinger\SilverStripe\ModelAnnotation\Test\Unit\Util;

use CSoellinger\SilverStripe\ModelAnnotation\Util\DataObjectUtil;
use CSoellinger\SilverStripe\ModelAnnotation\Test\Unit\Player;
use Exception;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Dev\Debug;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataList;

/**
 * @internal
 *
 * @covers \CSoellinger\SilverStripe\ModelAnnotation\Util\DataObjectUtil
 */
class DataObjectUtilTest extends SapphireTest
{
    protected DataObjectUtil $util;

    public function setUp(): void
    {
        parent::setUp();

        $util = new DataObjectUtil(Player::class);

        $this->util = $util;
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Util\DataObjectUtil::__construct
     */
    public function testInitialized(): void
    {
        self::assertInstanceOf(DataObjectUtil::class, $this->util);
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Util\DataObjectUtil::getRelationProperty
     */
    public function testAddRelationProperty(): void
    {
        $relationProperty = $this->util->getRelationProperty('Logo', Image::class . '.Title', 'Personal Logo');

        self::assertArrayHasKey('dataType', $relationProperty);
        self::assertArrayHasKey('variableName', $relationProperty);
        self::assertArrayHasKey('description', $relationProperty);

        self::assertEquals($relationProperty['dataType'], '\\' . Image::class);
        self::assertEquals($relationProperty['variableName'], 'Logo');
        self::assertEquals($relationProperty['description'], 'Personal Logo');
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Util\DataObjectUtil::getRelationProperty
     */
    public function testAddRelationPropertyWithExistingUseDataType(): void
    {
        $relationProperty = $this->util->getRelationProperty('MyList', DataList::class, 'My List ...');

        self::assertArrayHasKey('dataType', $relationProperty);
        self::assertArrayHasKey('variableName', $relationProperty);
        self::assertArrayHasKey('description', $relationProperty);

        self::assertEquals($relationProperty['dataType'], '\\' . DataList::class);
        self::assertEquals($relationProperty['variableName'], 'MyList');
        self::assertEquals($relationProperty['description'], 'My List ...');
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Util\DataObjectUtil::getRelationProperty
     */
    public function testAddRelationPropertyCollectUnused(): void
    {
        $this->util->collectUnused = true;

        $relationProperty = $this->util->getRelationProperty('MyFile', File::class, 'My File ...');

        $this->util->collectUnused = false;

        self::assertArrayHasKey('dataType', $relationProperty);
        self::assertArrayHasKey('variableName', $relationProperty);
        self::assertArrayHasKey('description', $relationProperty);

        self::assertEquals($relationProperty['dataType'], 'File');
        self::assertEquals($relationProperty['variableName'], 'MyFile');
        self::assertEquals($relationProperty['description'], 'My File ...');

        self::assertCount(1, $this->util->unusedTypes);
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Util\DataObjectUtil::getRelationMethod
     */
    public function testGetRelationMethod(): void
    {
        $relationMethod = $this->util->getRelationMethod('Logo', Image::class . '.Title', 'Personal Logo');

        self::assertArrayHasKey('dataType', $relationMethod);
        self::assertArrayHasKey('variableName', $relationMethod);
        self::assertArrayHasKey('description', $relationMethod);

        self::assertEquals($relationMethod['dataType'], '\\' . DataList::class);
        self::assertEquals($relationMethod['variableName'], 'Logo');
        self::assertEquals($relationMethod['description'], 'Personal Logo');
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Util\DataObjectUtil::getRelationMethod
     */
    public function testGetRelationMethodWithNotExistingUseDataType(): void
    {
        $relationMethod = $this->util->getRelationMethod('Logo', Image::class, 'Personal Logo', Debug::class);

        self::assertArrayHasKey('dataType', $relationMethod);
        self::assertArrayHasKey('variableName', $relationMethod);
        self::assertArrayHasKey('description', $relationMethod);
        self::assertEquals($relationMethod['dataType'], '\SilverStripe\Dev\Debug');
        self::assertEquals($relationMethod['variableName'], 'Logo');
        self::assertEquals($relationMethod['description'], 'Personal Logo');
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Util\DataObjectUtil::getRelationMethod
     */
    public function testGetRelationMethodCollectUnused(): void
    {
        $this->util->collectUnused = true;

        $relationMethod = $this->util->getRelationMethod('MyFile', File::class, 'My File ...', Debug::class);

        $this->util->collectUnused = false;

        self::assertArrayHasKey('dataType', $relationMethod);
        self::assertArrayHasKey('variableName', $relationMethod);
        self::assertArrayHasKey('description', $relationMethod);

        self::assertEquals($relationMethod['dataType'], 'Debug');
        self::assertEquals($relationMethod['variableName'], 'MyFile');
        self::assertEquals($relationMethod['description'], 'My File ...');

        self::assertCount(2, $this->util->unusedTypes);
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Util\DataObjectUtil::__construct
     *
     * @throws Exception
     */
    public function testInitializeException(): void
    {
        $this->expectException(Exception::class);

        new DataObjectUtil('Exception');
    }
}
