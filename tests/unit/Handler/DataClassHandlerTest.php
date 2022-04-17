<?php

namespace CSoellinger\SilverStripe\ModelAnnotation\Test\Unit\Handler;

use CSoellinger\SilverStripe\ModelAnnotation\Handler\DataClassFileHandler;
use CSoellinger\SilverStripe\ModelAnnotation\Handler\DataClassHandler;
use CSoellinger\SilverStripe\ModelAnnotation\Task\ModelAnnotationsTask;
use CSoellinger\SilverStripe\ModelAnnotation\View\DataClassTaskView;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

/**
 * @internal
 *
 * @covers \CSoellinger\SilverStripe\ModelAnnotation\Handler\DataClassHandler
 */
class DataClassFileTest extends SapphireTest
{
    protected static DataClassHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var DataClassHandler $handler */
        $handler = Injector::inst()
            ->createWithArgs(DataClassHandler::class, ['CSoellinger\SilverStripe\ModelAnnotation\Test\Unit\Team'])
        ;

        self::$handler = $handler;
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Handler\DataClassHandler::__construct
     * @dataProvider provideFqnArray
     */
    public function testInitialize(string $fqn): void
    {
        $handler = new DataClassHandler($fqn);

        self::assertInstanceOf(DataClassHandler::class, $handler);
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Handler\DataClassHandler::getMissingUseStatements
     */
    public function testGetMissingUseStatements(): void
    {
        $missingUseStatements = self::$handler->getMissingUseStatements();

        self::assertCount(0, $missingUseStatements);

        Config::forClass(ModelAnnotationsTask::class)->set('addUseStatements', true);

        /** @var DataClassHandler $handler */
        $handler = Injector::inst()
            ->createWithArgs(DataClassHandler::class, ['CSoellinger\SilverStripe\ModelAnnotation\Test\Unit\Team'])
        ;

        self::$handler = $handler;

        $missingUseStatements = self::$handler->getMissingUseStatements();

        self::assertCount(2, $missingUseStatements);
        self::assertEquals('use SilverStripe\\ORM\\HasManyList;', $missingUseStatements[0]);
        self::assertEquals('use SilverStripe\\ORM\\ManyManyList;', $missingUseStatements[1]);

        Config::forClass(ModelAnnotationsTask::class)->set('addUseStatements', false);
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Handler\DataClassHandler::getFile
     */
    public function testGetFile(): void
    {
        self::assertInstanceOf(DataClassFileHandler::class, self::$handler->getFile());
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Handler\DataClassHandler::getAst
     */
    public function testGetAst(): void
    {
        $ast = \ast\parse_file(self::$handler->getFile()->getPath(), 80);

        self::assertEquals($ast->children[4], self::$handler->getAst());
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Handler\DataClassHandler::getModelProperties
     */
    public function testGetModelProperties(): void
    {
        self::assertCount(2, self::$handler->getModelProperties());
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Handler\DataClassHandler::getModelMethods
     */
    public function testGetModelMethods(): void
    {
        self::assertCount(3, self::$handler->getModelMethods());
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Handler\DataClassHandler::getRenderer
     */
    public function testGetRenderer(): void
    {
        self::assertInstanceOf(DataClassTaskView::class, self::$handler->getRenderer());
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Handler\DataClassHandler::getClassPhpDoc
     */
    public function testGetClassPhpDoc(): void
    {
        self::assertEquals('', self::$handler->getClassPhpDoc());

        $reflection = new \ReflectionProperty(get_class(self::$handler), 'ast');
        $reflection->setAccessible(true);
        $reflection->setValue(self::$handler, null);

        self::assertEquals('', self::$handler->getClassPhpDoc());
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Handler\DataClassHandler::generateClassPhpDoc
     * @dataProvider provideFqnArray
     *
     * @param mixed $fqn
     * @param mixed $classDoc
     */
    public function testGenerateClassPhpDoc($fqn, $classDoc): void
    {
        /** @var DataClassHandler $handler */
        $handler = Injector::inst()->createWithArgs(DataClassHandler::class, [$fqn]);
        self::$handler = $handler;

        self::assertEquals($classDoc, self::$handler->generateClassPhpDoc());
    }

    /**
     * @return array<int,string[]>
     */
    public function provideFqnArray()
    {
        return [
            [
                'CSoellinger\SilverStripe\ModelAnnotation\Test\Unit\Player',
                implode(PHP_EOL, [
                    '/**',
                    ' * @internal Testing model',
                    ' *',
                    ' * @property string $Name ...',
                    ' *',
                    ' * @property Team $Team   Has one Team {@see Team}',
                    ' * @property int  $TeamID Team ID',
                    ' */',
                ]),
            ],
            [
                'CSoellinger\SilverStripe\ModelAnnotation\Test\Unit\Supporter',
                implode(PHP_EOL, [
                    '/**',
                    ' * @internal Testing model',
                    ' *',
                    ' * @method ManyManyList Supports() ...',
                    ' *',
                    ' */',
                ]),
            ],
            [
                'CSoellinger\SilverStripe\ModelAnnotation\Test\Unit\Team',
                implode(PHP_EOL, [
                    '/**',
                    ' * @property string $Name   Name ...',
                    ' * @property string $Origin Origin ...',
                    ' *',
                    ' * @method \SilverStripe\ORM\HasManyList  Players()    Has many Players {@see Player}',
                    ' * @method \SilverStripe\ORM\ManyManyList Supporters() Many many Supporters {@see TeamSupporter}',
                    ' * @method \SilverStripe\ORM\ManyManyList Images()     Many many Images {@see Image}',
                    ' */',
                ]),
            ],
            [
                'CSoellinger\SilverStripe\ModelAnnotation\Test\Unit\TeamSupporter',
                implode(PHP_EOL, [
                    '/**',
                    ' * @property int       $Ranking     Ranking ...',
                    ' * @property int       $TeamID      Team ID',
                    ' * @property Supporter $Supporter   Has one Supporter',
                    ' *',
                    ' * @property Team $Team        Has one Team {@see Team}',
                    ' * @property int  $SupporterID Supporter ID',
                    ' */',
                ]),
            ],
        ];
    }
}
