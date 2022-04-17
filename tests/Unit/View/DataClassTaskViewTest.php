<?php

namespace CSoellinger\SilverStripe\ModelAnnotations\Test\Unit\View;

// phpcs:disable
require_once __DIR__ . '/../../mockup.php';
// phpcs:enable

use CSoellinger\SilverStripe\ModelAnnotations\Test\PhpUnitHelper;
use CSoellinger\SilverStripe\ModelAnnotations\Util\Util;
use CSoellinger\SilverStripe\ModelAnnotations\View\DataClassTaskView;
use Reflection;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;

/**
 * @internal
 *
 * @covers \CSoellinger\SilverStripe\ModelAnnotations\View\DataClassTaskView
 */
class DataClassTaskViewTest extends SapphireTest
{
    protected static DataClassTaskView $view;

    public static string $phpSapiName = 'cli';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        /** @var DataClassTaskView $view */
        $view = Injector::inst()->get(DataClassTaskView::class);

        self::$view = $view;
    }

    protected function setUp(): void
    {
        parent::setUp();

        PhpUnitHelper::$phpSapiName = 'cli';
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\View\DataClassTaskView::renderHeader
     * @group ExpectedOutput
     */
    public function testRenderHeader(): void
    {
        $output = self::class . PHP_EOL;
        $output .= 'File: ' . __FILE__ . PHP_EOL . PHP_EOL;

        $this->expectOutputString($output);

        self::$view->renderHeader(self::class, __FILE__);
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\View\DataClassTaskView::renderHeader
     * @group ExpectedOutput
     */
    public function testRenderHeaderHtml(): void
    {
        PhpUnitHelper::$phpSapiName = 'apache';

        $output = '<div class="info">';
        $output .= '  <h3 style="margin-bottom: 0;">' . self::class . '</h3>';
        $output .= '</div>';

        $this->expectOutputString($output);

        self::$view->renderHeader(self::class, __FILE__);
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\View\DataClassTaskView::renderMessage
     * @group ExpectedOutput
     */
    public function testRenderMessage(): void
    {
        $output = 'Test Message' . PHP_EOL . PHP_EOL;

        $this->expectOutputString($output);

        self::$view->renderMessage('Test Message');
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\View\DataClassTaskView::renderMessage
     * @group ExpectedOutput
     */
    public function testRenderMessageHtml(): void
    {
        PhpUnitHelper::$phpSapiName = 'apache';

        $output = '<div class="build" style="padding-bottom: 0;">';
        $output .= '  <div class="success" style="font-weight: 600;">';
        $output .= 'Test Message';
        $output .= '  </div>';
        $output .= '</div>';

        $this->expectOutputString($output);

        self::$view->renderMessage('Test Message');
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\View\DataClassTaskView::renderSource
     * @group ExpectedOutput
     */
    public function testRenderSource(): void
    {
        $output = file_get_contents(__FILE__) . PHP_EOL . PHP_EOL;

        $this->expectOutputString($output);

        self::$view->renderSource(__FILE__, (string) file_get_contents(__FILE__));
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\View\DataClassTaskView::renderSource
     * @group ExpectedOutput
     */
    public function testRenderSourceHtml(): void
    {
        PhpUnitHelper::$phpSapiName = 'apache';

        $output = '<div class="info">';
        $output .= '  <small>' . __FILE__ . '</small>';
        $output .= '  <pre><code>' . htmlentities((string) file_get_contents(__FILE__)) . '</code></pre>';
        $output .= '</div><div>&nbsp;</div>';

        $this->expectOutputString($output);

        self::$view->renderSource(__FILE__, (string) file_get_contents(__FILE__));
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\View\DataClassTaskView::renderHr
     * @group ExpectedOutput
     */
    public function testRenderHr(): void
    {
        $output = '--- --- --- --- --- --- --- ---' . PHP_EOL . PHP_EOL;

        $this->expectOutputString($output);

        self::$view->renderHr();
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\View\DataClassTaskView::renderHr
     * @group ExpectedOutput
     */
    public function testRenderHrHtml(): void
    {
        PhpUnitHelper::$phpSapiName = 'apache';

        $output = '<div class="info" style="padding: 0;"><hr /></div>';

        $this->expectOutputString($output);

        self::$view->renderHr();
    }
}
