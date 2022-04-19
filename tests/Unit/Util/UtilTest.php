<?php

namespace CSoellinger\SilverStripe\ModelAnnotations\Test\Unit\Util;

use CSoellinger\SilverStripe\ModelAnnotations\Util\Util;
use Reflection;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;

/**
 * @internal
 *
 * @covers \CSoellinger\SilverStripe\ModelAnnotations\Util\Util
 */
class UtilTest extends SapphireTest
{
    protected static Util $util;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        /** @var Util $util */
        $util = Injector::inst()->get(Util::class);

        self::$util = $util;
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Util\Util::silverStripeToPhpType
     */
    public function testSilverStripeToPhpType(): void
    {
        self::assertEquals('string', self::$util->silverStripeToPhpType('Varchar'));
        self::assertEquals('float', self::$util->silverStripeToPhpType('Decimal'));
        self::assertEquals('bool', self::$util->silverStripeToPhpType('Boolean'));
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Util\Util::fileByFqn
     */
    public function testFileByFqn(): void
    {
        $classPath = str_replace(BASE_PATH, '', self::$util->fileByFqn(DataObject::class));

        self::assertEquals('/vendor/silverstripe/framework/src/ORM/DataObject.php', $classPath);
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Util\Util::fileByFqn
     */
    public function testFileByFqnFunction(): void
    {
        $functionPath = str_replace(BASE_PATH, '', self::$util->fileByFqn('_t'));

        self::assertEquals('/vendor/silverstripe/framework/src/includes/functions.php', $functionPath);
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Util\Util::fileByFqn
     */
    public function testFileByFqnUnknown(): void
    {
        self::assertEquals('', self::$util->fileByFqn('xyz'));
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Util\Util::getNamespaceFromFqn
     */
    public function testGetNamespaceFromFqn(): void
    {
        $namespace = self::$util->getNamespaceFromFqn(self::class);

        self::assertEquals('CSoellinger\SilverStripe\ModelAnnotations\Test\Unit\Util', $namespace);
        self::assertEquals('\\', self::$util->getNamespaceFromFqn(Reflection::class));
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Util\Util::getClassesFromNamespace
     */
    public function testGetClassesFromNamespace(): void
    {
        $namespace = self::$util->getNamespaceFromFqn(self::class);
        $classes = self::$util->getClassesFromNamespace($namespace);

        self::assertCount(1, $classes);
        self::assertEquals($classes[0], self::class);
    }
}
