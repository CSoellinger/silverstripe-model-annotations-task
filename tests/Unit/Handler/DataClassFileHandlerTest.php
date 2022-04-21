<?php

namespace CSoellinger\SilverStripe\ModelAnnotations\Test\Unit\Handler;

use CSoellinger\SilverStripe\ModelAnnotations\Handler\DataClassFileHandler;
use CSoellinger\SilverStripe\ModelAnnotations\Task\ModelAnnotationsTask;
use InvalidArgumentException;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

define('PHP_OPEN', '<?php');

/**
 * @internal
 *
 * @covers \CSoellinger\SilverStripe\ModelAnnotations\Handler\DataClassFileHandler
 */
class DataClassFileHandlerTest extends SapphireTest
{
    protected static DataClassFileHandler $fileHandler;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var DataClassFileHandler $fileHandler */
        $fileHandler = Injector::inst()->createWithArgs(DataClassFileHandler::class, [__FILE__]);

        self::$fileHandler = $fileHandler;
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Handler\DataClassFileHandler::__construct
     */
    public function testInitialize(): void
    {
        $fileHandlerInstance = new DataClassFileHandler(__FILE__);

        self::assertInstanceOf(DataClassFileHandler::class, $fileHandlerInstance);
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Handler\DataClassFileHandler::__construct
     *
     * @throws InvalidArgumentException
     * @group ExpectedOutput
     */
    public function testInitializeWrongPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Error with file at path "/no-file-exists-here.php"');

        new DataClassFileHandler('/no-file-exists-here.php');
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Handler\DataClassFileHandler::getPath
     */
    public function testGetPath(): void
    {
        self::assertEquals(__FILE__, self::$fileHandler->getPath());
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Handler\DataClassFileHandler::getContent
     */
    public function testGetContent(): void
    {
        self::assertEquals(file_get_contents(__FILE__), self::$fileHandler->getContent());
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Handler\DataClassFileHandler::getAst
     */
    public function testGetAst(): void
    {
        self::assertEquals(\ast\parse_file(__FILE__, 80), self::$fileHandler->getAst());
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Handler\DataClassFileHandler::getNamespaceAst
     */
    public function testGetNamespaceAst(): void
    {
        self::assertEquals(\ast\parse_file(__FILE__, 80)->children[0], self::$fileHandler->getNamespaceAst());
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Handler\DataClassFileHandler::getNamespaceAst
     */
    public function testGetNamespaceAstFromNoNamespaceFile(): void
    {
        $filePath = implode(DIRECTORY_SEPARATOR, [
            __DIR__,
            '..',
            '..',
            '..',
            '_config.php',
        ]);

        /** @var DataClassFileHandler $fileHandlerInstance */
        $fileHandlerInstance = Injector::inst()->createWithArgs(DataClassFileHandler::class, [$filePath]);

        self::$fileHandler = $fileHandlerInstance;

        self::assertNull(self::$fileHandler->getNamespaceAst());
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Handler\DataClassFileHandler::getClassAst
     */
    public function testGetClassAst(): void
    {
        self::assertEquals(\ast\parse_file(__FILE__, 80)->children[8], self::$fileHandler->getClassAst(self::class));
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Handler\DataClassFileHandler::getUseStatementsFromAst
     */
    public function testGetUseStatementsFromAst(): void
    {
        $useStatements = [
            \ast\parse_file(__FILE__, 80)->children[1]->children[0],
            \ast\parse_file(__FILE__, 80)->children[2]->children[0],
            \ast\parse_file(__FILE__, 80)->children[3]->children[0],
            \ast\parse_file(__FILE__, 80)->children[4]->children[0],
            \ast\parse_file(__FILE__, 80)->children[5]->children[0],
            \ast\parse_file(__FILE__, 80)->children[6]->children[0],
        ];

        self::assertEquals($useStatements, self::$fileHandler->getUseStatementsFromAst());
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Handler\DataClassFileHandler::addText
     */
    public function testAddText(): void
    {
        $newContent = str_replace(
            PHP_OPEN . PHP_EOL,
            PHP_OPEN . PHP_EOL . '\/** TEST *\/' . PHP_EOL,
            (string) file_get_contents(__FILE__)
        );

        self::$fileHandler->addText('\/** TEST *\/', 2);

        self::assertEquals($newContent, self::$fileHandler->getContent());
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Handler\DataClassFileHandler::addText
     */
    public function testAddTextAtLineZero(): void
    {
        $newContent = str_replace(
            PHP_OPEN . PHP_EOL,
            '<!-- TEST -->' . PHP_EOL . PHP_OPEN . PHP_EOL,
            (string) file_get_contents(__FILE__)
        );

        self::$fileHandler->addText('<!-- TEST -->', 0);

        self::assertEquals($newContent, self::$fileHandler->getContent());
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Handler\DataClassFileHandler::contentReplace
     */
    public function testContentReplace(): void
    {
        $newContent = str_replace(
            PHP_OPEN . PHP_EOL,
            PHP_OPEN . ' \/** TEST *\/' . PHP_EOL,
            (string) file_get_contents(__FILE__)
        );

        self::$fileHandler->contentReplace(PHP_OPEN . PHP_EOL, PHP_OPEN . ' \/** TEST *\/' . PHP_EOL);

        self::assertEquals($newContent, self::$fileHandler->getContent());
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Handler\DataClassFileHandler::write
     */
    public function testWriteFile(): void
    {
        self::$fileHandler->write();
        self::assertEquals(file_get_contents(__FILE__), self::$fileHandler->getContent());
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Handler\DataClassFileHandler::write
     */
    public function testWriteFileWithBackupFile(): void
    {
        Config::forClass(ModelAnnotationsTask::class)->set('createBackupFile', true);

        self::$fileHandler->write();
        self::assertEquals(file_get_contents(__FILE__), self::$fileHandler->getContent());
        self::assertFileExists(__FILE__ . '.bck');

        self::$fileHandler->write();
        self::assertEquals(file_get_contents(__FILE__), self::$fileHandler->getContent());
        self::assertFileExists(__FILE__ . '.1.bck');

        unlink(__FILE__ . '.bck');
        unlink(__FILE__ . '.1.bck');

        Config::forClass(ModelAnnotationsTask::class)->set('createBackupFile', false);
    }
}
