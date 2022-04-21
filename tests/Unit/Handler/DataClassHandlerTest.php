<?php

namespace CSoellinger\SilverStripe\ModelAnnotations\Test\Unit\Handler;

use CSoellinger\SilverStripe\ModelAnnotations\Handler\DataClassFileHandler;
use CSoellinger\SilverStripe\ModelAnnotations\Handler\DataClassHandler;
use CSoellinger\SilverStripe\ModelAnnotations\Task\ModelAnnotationsTask;
use CSoellinger\SilverStripe\ModelAnnotations\Test\Unit\Player;
use CSoellinger\SilverStripe\ModelAnnotations\Test\Unit\Supporter;
use CSoellinger\SilverStripe\ModelAnnotations\Test\Unit\Team;
use CSoellinger\SilverStripe\ModelAnnotations\Test\Unit\TeamSupporter;
use CSoellinger\SilverStripe\ModelAnnotations\View\DataClassTaskView;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

/**
 * @internal
 *
 * @covers \CSoellinger\SilverStripe\ModelAnnotations\Handler\DataClassHandler
 */
class DataClassHandlerTest extends SapphireTest
{
    protected static DataClassHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var DataClassHandler $handlerInstance */
        $handlerInstance = Injector::inst()
            ->createWithArgs(DataClassHandler::class, [Team::class])
        ;

        self::$handler = $handlerInstance;
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Handler\DataClassHandler::__construct
     * @dataProvider provideFqnArray
     */
    public function testInitialize(string $fqn, string $classDoc, string $classFilePath): void
    {
        $handlerInstance = new DataClassHandler($fqn);

        self::assertEquals($classFilePath, $handlerInstance->getFile()->getPath());
        self::assertNotEmpty($classDoc);
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Handler\DataClassHandler::getMissingUseStatements
     */
    public function testGetMissingUseStatements(): void
    {
        $missingUseStatements = self::$handler->getMissingUseStatements();

        self::assertCount(0, $missingUseStatements);

        Config::forClass(ModelAnnotationsTask::class)->set('addUseStatements', true);

        /** @var DataClassHandler $handlerInstance */
        $handlerInstance = Injector::inst()
            ->createWithArgs(DataClassHandler::class, ['CSoellinger\SilverStripe\ModelAnnotations\Test\Unit\Team'])
        ;

        self::$handler = $handlerInstance;

        $missingUseStatements = self::$handler->getMissingUseStatements();

        self::assertCount(2, $missingUseStatements);
        self::assertEquals('use SilverStripe\\ORM\\HasManyList;', $missingUseStatements[0]);
        self::assertEquals('use SilverStripe\\ORM\\ManyManyList;', $missingUseStatements[1]);

        Config::forClass(ModelAnnotationsTask::class)->set('addUseStatements', false);
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Handler\DataClassHandler::getFile
     */
    public function testGetFile(): void
    {
        self::assertInstanceOf(DataClassFileHandler::class, self::$handler->getFile());
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Handler\DataClassHandler::getAst
     */
    public function testGetAst(): void
    {
        $ast = \ast\parse_file(self::$handler->getFile()->getPath(), 80);

        self::assertEquals($ast->children[4], self::$handler->getAst());
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Handler\DataClassHandler::getModelProperties
     */
    public function testGetModelProperties(): void
    {
        self::assertCount(2, self::$handler->getModelProperties());
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Handler\DataClassHandler::getModelMethods
     */
    public function testGetModelMethods(): void
    {
        self::assertCount(3, self::$handler->getModelMethods());
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Handler\DataClassHandler::getRenderer
     */
    public function testGetRenderer(): void
    {
        self::assertInstanceOf(DataClassTaskView::class, self::$handler->getRenderer());
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Handler\DataClassHandler::getClassPhpDoc
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
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Handler\DataClassHandler::generateClassPhpDoc
     * @dataProvider provideFqnArray
     */
    public function testGenerateClassPhpDoc(string $fqn, string $classDoc): void
    {
        /** @var DataClassHandler $handlerInstance */
        $handlerInstance = Injector::inst()->createWithArgs(DataClassHandler::class, [$fqn]);
        self::$handler = $handlerInstance;

        self::assertEquals($classDoc, self::$handler->generateClassPhpDoc());
    }

    /**
     * @return array<int,string[]>
     */
    public function provideFqnArray()
    {
        return [
            [
                Player::class,
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
                (string) realpath(implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'Player.php'])),
            ],
            [
                Supporter::class,
                implode(PHP_EOL, [
                    '/**',
                    ' * @internal Testing model',
                    ' *',
                    ' * @method ManyManyList Supports() ...',
                    ' *',
                    ' */',
                ]),
                (string) realpath(implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'Supporter.php'])),
            ],
            [
                Team::class,
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
                (string) realpath(implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'Team.php'])),
            ],
            [
                TeamSupporter::class,
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
                (string) realpath(implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'TeamSupporter.php'])),
            ],
        ];
    }
}
