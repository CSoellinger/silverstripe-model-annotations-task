<?php

namespace CSoellinger\SilverStripe\ModelAnnotation\Test\Unit\Util;

use CSoellinger\SilverStripe\ModelAnnotation\Util\Util;
use Reflection;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;

/**
 * @internal
 *
 * @covers \CSoellinger\SilverStripe\ModelAnnotation\Util\Util
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
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Util\Util::strStartsWith
     */
    public function testStrStartsWith(): void
    {
        self::assertTrue(self::$util->strStartsWith('Hallo', 'Ha'));
        self::assertFalse(self::$util->strStartsWith('Hallo', 'Ol'));
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Util\Util::silverStripeToPhpType
     */
    public function testSilverStripeToPhpType(): void
    {
        self::assertEquals('string', self::$util->silverStripeToPhpType('Varchar'));
        self::assertEquals('float', self::$util->silverStripeToPhpType('Decimal'));
        self::assertEquals('bool', self::$util->silverStripeToPhpType('Boolean'));
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Util\Util::fileByFqn
     */
    public function testFileByFqn(): void
    {
        $classPath = str_replace(BASE_PATH, '', self::$util->fileByFqn(DataObject::class));

        self::assertEquals('/vendor/silverstripe/framework/src/ORM/DataObject.php', $classPath);
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Util\Util::fileByFqn
     */
    public function testFileByFqnFunction(): void
    {
        $functionPath = str_replace(BASE_PATH, '', self::$util->fileByFqn('_t'));

        self::assertEquals('/vendor/silverstripe/framework/src/includes/functions.php', $functionPath);
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Util\Util::fileByFqn
     */
    public function testFileByFqnUnknown(): void
    {
        self::assertEquals('', self::$util->fileByFqn('xyz'));
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Util\Util::getNamespaceFromFqn
     */
    public function testGetNamespaceFromFqn(): void
    {
        $namespace = self::$util->getNamespaceFromFqn(self::class);

        self::assertEquals('CSoellinger\SilverStripe\ModelAnnotation\Test\Unit\Util', $namespace);
        self::assertEquals('\\', self::$util->getNamespaceFromFqn(Reflection::class));
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Util\Util::getClassesFromNamespace
     */
    public function testGetClassesFromNamespace(): void
    {
        $namespace = self::$util->getNamespaceFromFqn(self::class);
        $classes = self::$util->getClassesFromNamespace($namespace);

        self::assertCount(1, $classes);
        self::assertEquals($classes[0], self::class);
    }
}
