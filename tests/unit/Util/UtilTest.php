<?php

namespace CSoellinger\SilverStripe\ModelAnnotation\Test\Unit\Util;

use CSoellinger\SilverStripe\ModelAnnotation\Util\Util;
use Reflection;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;

/**
 * @internal
 *
 * @covers \CSoellinger\SilverStripe\ModelAnnotation\Util\Util
 */
class UtilTest extends SapphireTest
{
    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Util\Util::strStartsWith
     */
    public function testStrStartsWith(): void
    {
        self::assertTrue(Util::strStartsWith('Hallo', 'Ha'));
        self::assertFalse(Util::strStartsWith('Hallo', 'Ol'));
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Util\Util::silverStripeToPhpTyping
     */
    public function testSilverStripeToPhpTyping(): void
    {
        self::assertEquals('string', Util::silverStripeToPhpTyping('Varchar'));
        self::assertEquals('float', Util::silverStripeToPhpTyping('Decimal'));
        self::assertEquals('bool', Util::silverStripeToPhpTyping('Boolean'));
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Util\Util::fileByFqn
     */
    public function testFileByFqn(): void
    {
        $classPath = str_replace(BASE_PATH, '', Util::fileByFqn(DataObject::class));

        self::assertEquals('/vendor/silverstripe/framework/src/ORM/DataObject.php', $classPath);
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Util\Util::fileByFqn
     */
    public function testFileByFqnFunction(): void
    {
        $functionPath = str_replace(BASE_PATH, '', Util::fileByFqn('_t'));

        self::assertEquals('/vendor/silverstripe/framework/src/includes/functions.php', $functionPath);
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Util\Util::fileByFqn
     */
    public function testFileByFqnUnknown(): void
    {
        self::assertEquals('', Util::fileByFqn('xyz'));
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Util\Util::getNamespaceFromFqn
     */
    public function testGetNamespaceFromFqn(): void
    {
        $namespace = Util::getNamespaceFromFqn(self::class);

        self::assertEquals('CSoellinger\SilverStripe\ModelAnnotation\Test\Unit\Util', $namespace);
        self::assertEquals('\\', Util::getNamespaceFromFqn(Reflection::class));
    }
}
