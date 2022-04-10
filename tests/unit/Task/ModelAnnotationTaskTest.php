<?php

namespace CSoellinger\SilverStripe\ModelAnnotation\Test\Unit\Task;

use CSoellinger\SilverStripe\ModelAnnotation\Task\ModelAnnotationTask;
use Exception;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

/**
 * @internal
 *
 * @covers \CSoellinger\SilverStripe\ModelAnnotation\Task\ModelAnnotationTask
 */
class ModelAnnotationTaskTest extends SapphireTest
{
    protected static ModelAnnotationTask $task;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        /** @var ModelAnnotationTask $task */
        $task = Injector::inst()->get(ModelAnnotationTask::class);

        self::$task = $task;
    }

    public function setUp(): void
    {
        parent::setUp();

        self::$task->config()->set('createBackupFile', false);
        self::$task->config()->set('dryRun', false);
        self::$task->config()->set('quiet', false);
        self::$task->config()->set('addUseStatements', false);
        self::$task->config()->set('ignoreFields', [
            'LinkTracking',
            'FileTracking',
        ]);
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Task\ModelAnnotationTask::__construct
     */
    public function testInitialized(): void
    {
        self::assertInstanceOf(ModelAnnotationTask::class, self::$task);
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Task\ModelAnnotationTask::getTitle
     */
    public function testGetTitle(): void
    {
        self::assertEquals('IDE Model Annotations', self::$task->getTitle());
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Task\ModelAnnotationTask::getDescription
     */
    public function testGetDescription(): void
    {
        $description = implode('', [
            'Add ide annotations for dataobject models. ',
            'This is helpful to get auto completions for db fields ',
            'and relations in your ide (most should support it). Be ',
            'careful: This software writes your files. So it normally ',
            'should not crash your code be aware that this could happen. ',
            'So turning on backup files config could be a good idea if ',
            'you have very complex data objects ;-)',
        ]);

        self::assertEquals($description, self::$task->getDescription());
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Task\ModelAnnotationTask::run
     */
    public function testTaskRunWithDryRunForOneClass(): void
    {
        $output = [
            date('d.m.Y H:m'),
            '',
            '',
            'CSoellinger\SilverStripe\ModelAnnotation\Test\Unit\Player',
            '/var/www/html/tests/unit/Player.php',
            '<?php',
            '',
            'namespace CSoellinger\SilverStripe\ModelAnnotation\Test\Unit;',
            '',
            'use SilverStripe\Dev\TestOnly;',
            'use SilverStripe\ORM\DataObject;',
            '',
            '/**',
            ' * @internal Testing model',
            ' *',
            ' * @property string $Name ...',
            ' *',
            ' * @property Team $Team   Has one Team',
            ' * @property int  $TeamID Team ID',
            ' */',
            ...$this->getPlayerModelOutput(),
            '-----------------------',
            '',
            'Task finished',
            '',
            '',
            '',
        ];

        $this->expectOutputString(implode(PHP_EOL, $output));

        self::$task->run($this->getRequest('CSoellinger\SilverStripe\ModelAnnotation\Test\Unit\Player'));
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Task\ModelAnnotationTask::run
     */
    public function testTaskRunWithIgnoreFieldConfig(): void
    {
        $output = [
            date('d.m.Y H:m'),
            '',
            '',
            'CSoellinger\SilverStripe\ModelAnnotation\Test\Unit\Player',
            '/var/www/html/tests/unit/Player.php',
            '<?php',
            '',
            'namespace CSoellinger\SilverStripe\ModelAnnotation\Test\Unit;',
            '',
            'use SilverStripe\Dev\TestOnly;',
            'use SilverStripe\ORM\DataObject;',
            '',
            '/**',
            ' * @internal Testing model',
            ' *',
            ' * @property string $Name ...',
            ' */',
            ...$this->getPlayerModelOutput(),
            '-----------------------',
            '',
            'Task finished',
            '',
            '',
            '',
        ];

        $this->expectOutputString(implode(PHP_EOL, $output));

        self::$task->config()->set('ignoreFields', [
            'Name',
            'Team',
            'LinkTracking',
            'FileTracking',
        ]);

        self::$task->run($this->getRequest('CSoellinger\SilverStripe\ModelAnnotation\Test\Unit\Player'));
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Task\ModelAnnotationTask::run
     */
    public function testTaskRunWithQuietConfig(): void
    {
        $this->expectOutputString('');

        self::$task->config()->set('quiet', true);

        self::$task->run($this->getRequest('CSoellinger\SilverStripe\ModelAnnotation\Test\Unit\Player'));
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Task\ModelAnnotationTask::run
     */
    public function testTaskRunWithMethodPhpDoc(): void
    {
        $output = [
            date('d.m.Y H:m'),
            '',
            '',
            'CSoellinger\SilverStripe\ModelAnnotation\Test\Unit\Team',
            '/var/www/html/tests/unit/Team.php',
            '<?php',
            '',
            'namespace CSoellinger\SilverStripe\ModelAnnotation\Test\Unit;',
            '',
            'use SilverStripe\Assets\Image;',
            'use SilverStripe\Dev\TestOnly;',
            'use SilverStripe\ORM\DataObject;',
            '',
            '/**',
            ' * @property string $Name   Name ...',
            ' * @property string $Origin Origin ...',
            ' *',
            ' * @method \SilverStripe\ORM\HasManyList  Players()    Has many Players {@link Player}',
            ' * @method \SilverStripe\ORM\ManyManyList Supporters() Many many Supporters {@link TeamSupporter}',
            ' * @method \SilverStripe\ORM\ManyManyList Images()     Many many Images {@link Image}',
            ' */',
            ...$this->getTeamModelOutput(),
            '-----------------------',
            '',
            'Task finished',
            '',
            '',
            '',
        ];

        $this->expectOutputString(implode(PHP_EOL, $output));

        self::$task->run($this->getRequest('CSoellinger\SilverStripe\ModelAnnotation\Test\Unit\Team'));
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Task\ModelAnnotationTask::run
     */
    public function testTaskRunOutputWithUseStatements(): void
    {
        $output = [
            date('d.m.Y H:m'),
            '',
            '',
            'CSoellinger\SilverStripe\ModelAnnotation\Test\Unit\Team',
            '/var/www/html/tests/unit/Team.php',
            '<?php',
            '',
            'namespace CSoellinger\SilverStripe\ModelAnnotation\Test\Unit;',
            '',
            'use SilverStripe\Assets\Image;',
            'use SilverStripe\Dev\TestOnly;',
            'use SilverStripe\ORM\DataObject;',
            'use SilverStripe\ORM\HasManyList;',
            'use SilverStripe\ORM\ManyManyList;',
            '',
            '/**',
            ' * @property string $Name   Name ...',
            ' * @property string $Origin Origin ...',
            ' *',
            ' * @method HasManyList  Players()    Has many Players {@link Player}',
            ' * @method ManyManyList Supporters() Many many Supporters {@link TeamSupporter}',
            ' * @method ManyManyList Images()     Many many Images {@link Image}',
            ' */',
            ...$this->getTeamModelOutput(),
            '-----------------------',
            '',
            'Task finished',
            '',
            '',
            '',
        ];

        $this->expectOutputString(implode(PHP_EOL, $output));

        self::$task->config()->set('addUseStatements', true);

        self::$task->run($this->getRequest('CSoellinger\SilverStripe\ModelAnnotation\Test\Unit\Team'));
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Task\ModelAnnotationTask::run
     */
    public function testTaskRunWriteToFileIncludingBackupFile(): void
    {
        if (!file_exists(__DIR__ . '/../../../build/cache/test')) {
            mkdir(__DIR__ . '/../../../build/cache/test', 0777, true);
        }

        copy(__DIR__ . '/../Team.php', __DIR__ . '/../../../build/cache/test/Team.BeforeTest.tmp');

        self::$task->config()->set('addUseStatements', true);
        self::$task->config()->set('createBackupFile', true);

        $request = $this->getRequest('CSoellinger\SilverStripe\ModelAnnotation\Test\Unit\Team', 0);

        $this->expectOutputString(implode(PHP_EOL, [
            date('d.m.Y H:m'),
            '',
            '',
            'CSoellinger\SilverStripe\ModelAnnotation\Test\Unit\Team',
            '/var/www/html/tests/unit/Team.php',
            'Creating backup file at /var/www/html/tests/unit/Team.php.bck',
            'Writing file /var/www/html/tests/unit/Team.php',
            '-----------------------',
            '',
            'Task finished',
            '',
            '',
            '',
        ]));

        self::$task->run($request);

        $fileContent = file_get_contents(__DIR__ . '/../Team.php');
        $fileShouldBe = implode(PHP_EOL, [
            '<?php',
            '',
            'namespace CSoellinger\SilverStripe\ModelAnnotation\Test\Unit;',
            '',
            'use SilverStripe\Assets\Image;',
            'use SilverStripe\Dev\TestOnly;',
            'use SilverStripe\ORM\DataObject;',
            'use SilverStripe\ORM\HasManyList;',
            'use SilverStripe\ORM\ManyManyList;',
            '',
            '/**',
            ' * @property string $Name   Name ...',
            ' * @property string $Origin Origin ...',
            ' *',
            ' * @method HasManyList  Players()    Has many Players {@link Player}',
            ' * @method ManyManyList Supporters() Many many Supporters {@link TeamSupporter}',
            ' * @method ManyManyList Images()     Many many Images {@link Image}',
            ' */',
            ...$this->getTeamModelOutput(),
            '',
        ]);

        self::assertFileExists(__DIR__ . '/../Team.php.bck');
        self::assertEquals($fileShouldBe, $fileContent);

        unlink(__DIR__ . '/../Team.php');
        unlink(__DIR__ . '/../Team.php.bck');

        copy(__DIR__ . '/../../../build/cache/test/Team.BeforeTest.tmp', __DIR__ . '/../Team.php');

        unlink(__DIR__ . '/../../../build/cache/test/Team.BeforeTest.tmp');
    }


    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Task\ModelAnnotationTask::run
     */
    public function testTaskRunWriteToFileIncludingSecondBackupFile(): void
    {
        if (!file_exists(__DIR__ . '/../../../build/cache/test')) {
            mkdir(__DIR__ . '/../../../build/cache/test', 0777, true);
        }

        copy(__DIR__ . '/../Team.php', __DIR__ . '/../../../build/cache/test/Team.BeforeTest.tmp');
        copy(__DIR__ . '/../Team.php', __DIR__ . '/../Team.php.bck');

        self::$task->config()->set('quiet', true);
        self::$task->config()->set('createBackupFile', true);

        $request = $this->getRequest('CSoellinger\SilverStripe\ModelAnnotation\Test\Unit\Team', 0);

        self::$task->run($request);

        self::assertFileExists(__DIR__ . '/../Team.php.1.bck');

        unlink(__DIR__ . '/../Team.php');
        unlink(__DIR__ . '/../Team.php.bck');
        unlink(__DIR__ . '/../Team.php.1.bck');

        copy(__DIR__ . '/../../../build/cache/test/Team.BeforeTest.tmp', __DIR__ . '/../Team.php');

        unlink(__DIR__ . '/../../../build/cache/test/Team.BeforeTest.tmp');
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Task\ModelAnnotationTask::run
     */
    public function testNothingToUpdate(): void
    {
        $this->expectOutputString(implode(PHP_EOL, [
            date('d.m.Y H:m'),
            '',
            '',
            'CSoellinger\SilverStripe\ModelAnnotation\Test\Unit\TeamSupporter',
            '/var/www/html/tests/unit/TeamSupporter.php',
            'No update for /var/www/html/tests/unit/TeamSupporter.php',
            '-----------------------',
            '',
            'Task finished',
            '',
            '',
            '',
        ]));

        $request = $this->getRequest('CSoellinger\SilverStripe\ModelAnnotation\Test\Unit\TeamSupporter', 0);

        self::$task->run($request);
        // TeamSupporter

        self::assertTrue(true);
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotation\Task\ModelAnnotationTask::run
     */
    public function testBadClassName(): void
    {
        $this->expectException(Exception::class);

        $request = $this->getRequest('Not\\Existing\\Class', 0);

        self::$task->run($request);
    }

    private function getRequest(
        string $dataClass = null,
        int $dryRun = 1,
        string $method = 'GET',
        string $url = '/dev/tasks/CSoellinger-SilverStripe-ModelAnnotation-Task-ModelAnnotationTask'
    ): HTTPRequest {
        $opts = ['dryRun' => $dryRun];

        if ($dataClass) {
            $opts['dataClass'] = $dataClass;
        }

        /** @var HTTPRequest */
        return Injector::inst()->createWithArgs(HTTPRequest::class, [
            $method,
            $url,
            $opts,
        ]);
    }

    /**
     * @return string[]
     */
    private function getPlayerModelOutput(): array
    {
        return [
            'class Player extends DataObject implements TestOnly',
            '{',
            '    /**',
            '     * @var array<string,string> undocumented variable',
            '     */',
            '    private static $db = [',
            '        \'Name\' => \'Varchar(255)\',',
            '    ];',
            '',
            '    /**',
            '     * @var array<string,string> undocumented variable',
            '     */',
            '    private static $has_one = [',
            '        \'Team\' => Team::class,',
            '    ];',
            '}',
        ];
    }

    /**
     * Undocumented function.
     *
     * @return string[]
     */
    private function getTeamModelOutput(): array
    {
        return [
            'class Team extends DataObject implements TestOnly',
            '{',
            '    /**',
            '     * @var array<string,string> undocumented variable',
            '     */',
            '    private static $db = [',
            '        \'Name\' => \'Varchar(255)\',',
            '        \'Origin\' => \'Varchar(255)\',',
            '    ];',
            '',
            '    /**',
            '     * @var array<string,string> undocumented variable',
            '     */',
            '    private static $has_many = [',
            '        \'Players\' => Player::class,',
            '    ];',
            '',
            '    /**',
            '     * @var array<string,array<string,string>|string> undocumented variable',
            '     */',
            '    private static $many_many = [',
            '        \'Supporters\' => [',
            '            \'through\' => TeamSupporter::class,',
            '            \'from\' => \'Team\',',
            '            \'to\' => \'Supporter\',',
            '        ],',
            '        \'Images\' => Image::class,',
            '    ];',
            '}',
        ];
    }
}
