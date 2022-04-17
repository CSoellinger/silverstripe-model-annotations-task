<?php

namespace CSoellinger\SilverStripe\ModelAnnotations\Test\Unit\Task;

// phpcs:disable
require_once __DIR__ . '/../../mockup.php';
// phpcs:enable

use CSoellinger\SilverStripe\ModelAnnotations\Task\ModelAnnotationsTask;
use CSoellinger\SilverStripe\ModelAnnotations\Test\PhpUnitHelper;
use CSoellinger\SilverStripe\ModelAnnotations\Util\Util;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Kernel;
use SilverStripe\Dev\SapphireTest;

/**
 * @internal
 *
 * @covers \CSoellinger\SilverStripe\ModelAnnotations\Task\ModelAnnotationsTask
 */
class ModelAnnotationsTaskTest extends SapphireTest
{
    protected static ModelAnnotationsTask $task;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        Injector::nest();
        Injector::inst()->registerService(new HTTPRequest('GET', '/'), HTTPRequest::class);
    }

    public function setUp(): void
    {
        parent::setUp();

        PhpUnitHelper::$phpSapiName = 'cli';

        /** @var ModelAnnotationsTask $task */
        $task = Injector::inst()->create(ModelAnnotationsTask::class);

        self::$task = $task;
        self::$task->config()->set('createBackupFile', false);
        self::$task->config()->set('dryRun', false);
        self::$task->config()->set('quiet', false);
        self::$task->config()->set('addUseStatements', false);
        self::$task->config()->set('ignoreFields', [
            'LinkTracking',
            'FileTracking',
        ]);
    }

    public static function setUpAfterClass(): void
    {
        Injector::unnest();
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Task\ModelAnnotationsTask::__construct
     */
    public function testInitialized(): void
    {
        $modelAnnotationsTask = new ModelAnnotationsTask();
        self::assertInstanceOf(ModelAnnotationsTask::class, $modelAnnotationsTask);
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Task\ModelAnnotationsTask::__construct
     */
    public function testInitializedBrowser(): void
    {
        PhpUnitHelper::$phpSapiName = 'apache';

        $modelAnnotationsTask = new ModelAnnotationsTask();
        self::assertInstanceOf(ModelAnnotationsTask::class, $modelAnnotationsTask);
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Task\ModelAnnotationsTask::getTitle
     */
    public function testGetTitle(): void
    {
        self::assertEquals('Model Annotations Generator', self::$task->getTitle());
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Task\ModelAnnotationsTask::getDescription
     */
    public function testGetDescription(): void
    {
        $description = "
        Add ide annotations for dataobject models. This is helpful to get auto
        completions for db fields and relations in your ide (most should
        support it). This task (over)write files so it's always a good idea to
        make a dryRun and/or backup files.

		Parameters (optional):
        - dataClass: Generate annotations only for one class. If not set all found data object classes will be used.
		- createBackupFile: Create a backup before writing a file. (default: FALSE)
		- addUseStatements: Add use statements for data types which are not declared (default: FALSE)
		- dryRun: Only print changes and don't write file (default: TRUE)
		- quiet: No outputs (default: FALSE)
	";

        self::assertEquals($description, self::$task->getDescription());
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Task\ModelAnnotationsTask::setUtil
     */
    public function testSetUtil(): void
    {
        self::$task->setUtil(new Util());

        self::assertInstanceOf(ModelAnnotationsTask::class, self::$task);
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Task\ModelAnnotationsTask::setRequest
     */
    public function testSetRequest(): void
    {
        self::$task->setRequest(new HTTPRequest('GET', '/'));

        self::assertInstanceOf(ModelAnnotationsTask::class, self::$task);
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Task\ModelAnnotationsTask::setLogger
     */
    public function testSetLogger(): void
    {
        /** @var Logger $logger */
        $logger = Injector::inst()->get(LoggerInterface::class);

        self::$task->setLogger($logger);

        self::assertInstanceOf(ModelAnnotationsTask::class, self::$task);
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Task\ModelAnnotationsTask::run
     * @group ExpectedOutput
     */
    public function testTaskRunNotOnDev(): void
    {
        /** @var Kernel $kernel */
        $kernel = Injector::inst()->get(Kernel::class);
        $kernel->setEnvironment('live');

        $output = [
            'ERROR [Alert]: You can run this task only inside a dev environment. Your environment is: live',
            'IN GET /dev/tasks/ModelAnnotationsTask',
            'Line 0 in ' . realpath(
                implode(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', '..', 'src', 'Task', 'ModelAnnotationsTask.php'])
            ),
            '',
            '',
        ];

        $this->expectOutputString(implode(PHP_EOL, $output));
        $this->expectError();

        $request = $this->getRequest('CSoellinger\SilverStripe\ModelAnnotations\Test\Unit\Player');
        self::$task->setRequest($request);
        self::$task->run($request);

        /** @var Kernel $kernel */
        $kernel = Injector::inst()->get(Kernel::class);
        $kernel->setEnvironment('dev');
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Task\ModelAnnotationsTask::run
     * @group ExpectedOutput
     */
    public function testTaskRunErrorSilently(): void
    {
        /** @var Kernel $kernel */
        $kernel = Injector::inst()->get(Kernel::class);
        $kernel->setEnvironment('live');

        $this->expectOutputString('');
        $this->expectError();

        self::$task->config()->set('quiet', true);

        $request = $this->getRequest('CSoellinger\SilverStripe\ModelAnnotations\Test\Unit\Player');
        self::$task->setRequest($request);
        self::$task->run($request);

        /** @var Kernel $kernel */
        $kernel = Injector::inst()->get(Kernel::class);
        $kernel->setEnvironment('dev');
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Task\ModelAnnotationsTask::run
     * @group ExpectedOutput
     */
    public function testTaskRunNotAdminNotCli(): void
    {
        PhpUnitHelper::$phpSapiName = 'apache';

        $output = [
            'ERROR [Alert]: Inside browser only admins are allowed to run this task.',
            'IN GET /dev/tasks/ModelAnnotationsTask',
            'Line 0 in ' . realpath(
                implode(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', '..', 'src', 'Task', 'ModelAnnotationsTask.php'])
            ),
            '',
            '',
        ];

        $this->expectOutputString(implode(PHP_EOL, $output));
        $this->expectError();

        $request = $this->getRequest('CSoellinger\SilverStripe\ModelAnnotations\Test\Unit\Player');
        self::$task->setRequest($request);
        self::$task->run($request);
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Task\ModelAnnotationsTask::run
     * @dataProvider provideFqnArray
     * @group ExpectedOutput
     */
    public function testTaskRunWithDryRunForOneClass(string $fqn, string $expectedOutput): void
    {
        $this->expectOutputString($expectedOutput);

        $request = $this->getRequest($fqn);
        self::$task->setRequest($request);
        self::$task->run($request);
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Task\ModelAnnotationsTask::run
     * @group ExpectedOutput
     */
    public function testTaskRunWithDryRunForOneClassWithCollectedUse(): void
    {
        $fqn = $this->provideFqnArray()[2][0];
        $expected = $this->provideFqnArray()[2][2];

        $this->expectOutputString($expected);
        self::$task->config()->set('addUseStatements', true);

        $request = $this->getRequest($fqn);
        self::$task->setRequest($request);
        self::$task->run($request);
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Task\ModelAnnotationsTask::run
     * @group ExpectedOutput
     */
    public function testTaskRunWithNotExistingClass(): void
    {
        $output = implode(PHP_EOL, [
            'PARAMS',
            '| dataClass: \Not\Existing\Class | dryRun: true | addUseStatements: false | createBackupFile: false',
            '| quiet: false |',
            '----------------------------------------------------------------------------------------------------',
            '',
            '',
            'ERROR [Alert]: Data class "\not\existing\class" does not exist',
            'IN GET /dev/tasks/ModelAnnotationsTask',
            'Line 0 in ' . realpath(
                implode(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', '..', 'src', 'Task', 'ModelAnnotationsTask.php'])
            ),
            '',
            '',
        ]);
        $this->expectError();
        $this->expectOutputString($output);

        $request = $this->getRequest('\\Not\\Existing\\Class');
        self::$task->setRequest($request);
        self::$task->run($request);
    }

    /**
     * @covers \CSoellinger\SilverStripe\ModelAnnotations\Task\ModelAnnotationsTask::run
     * @group ExpectedOutput
     */
    public function testTaskRunWritingFile(): void
    {
        $fqn = $this->provideFqnArray()[2][0];

        $expected = implode(PHP_EOL, [
            'PARAMS',
            '| dataClass: CSoellinger\SilverStripe\ModelAnnotations\Test\Unit\Team | dryRun: false |',
            'addUseStatements: false | createBackupFile: false | quiet: false |',
            '----------------------------------------------------------------------------------------------------',
            '',
            '',
            'CSoellinger\SilverStripe\ModelAnnotations\Test\Unit\Team',
            'File: ' . realpath(implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'Team.php'])),
            '',
            'Generating annotations done',
            '',
            '',
        ]);
        $expectedFile = $this->provideFqnArray()[2][3];

        $this->expectOutputString($expected);

        $modelFile = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Team.php';
        $testBackup = implode(DIRECTORY_SEPARATOR, [
            __DIR__,
            '..',
            '..',
            '..',
            'build',
            'cache',
            'Team.php.test',
        ]);

        copy($modelFile, $testBackup);

        $request = $this->getRequest($fqn, 0);

        self::$task->setRequest($request);
        self::$task->run($request);

        self::assertEquals($expectedFile, file_get_contents($modelFile));

        unlink($modelFile);
        copy($testBackup, $modelFile);
        unlink($testBackup);
    }

    /**
     * @return array<int,string[]>
     */
    public function provideFqnArray()
    {
        return [
            [
                'CSoellinger\SilverStripe\ModelAnnotations\Test\Unit\Player',
                implode(PHP_EOL, [
                    'PARAMS',
                    '| dataClass: CSoellinger\SilverStripe\ModelAnnotations\Test\Unit\Player | dryRun: true |',
                    'addUseStatements: false | createBackupFile: false | quiet: false |',
                    '--------------------------------------------------------------------------------------------' .
                        '--------',
                    '',
                    '',
                    'CSoellinger\SilverStripe\ModelAnnotations\Test\Unit\Player',
                    'File: ' .
                    realpath(implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'Player.php'])),
                    '',
                    'Generating annotations done',
                    '',
                    '<?php',
                    '',
                    'namespace CSoellinger\SilverStripe\ModelAnnotations\Test\Unit;',
                    '',
                    'use SilverStripe\Dev\TestOnly;',
                    'use SilverStripe\ORM\DataObject;',
                    '',
                    '/**',
                    ' * @internal Testing model',
                    ' *',
                    ' * @property string $Name ...',
                    ' *',
                    ' * @property Team $Team   Has one Team {@see Team}',
                    ' * @property int  $TeamID Team ID',
                    ' */',
                    ...$this->getPlayerModelOutput(),
                    '',
                    '',
                    '',
                ]),
            ],
            [
                'CSoellinger\SilverStripe\ModelAnnotations\Test\Unit\Supporter',
                implode(PHP_EOL, [
                    'PARAMS',
                    '| dataClass: CSoellinger\SilverStripe\ModelAnnotations\Test\Unit\Supporter | dryRun: true |',
                    'addUseStatements: false | createBackupFile: false | quiet: false |',
                    '--------------------------------------------------------------------------------------------' .
                        '--------',
                    '',
                    '',
                    'CSoellinger\SilverStripe\ModelAnnotations\Test\Unit\Supporter',
                    'File: ' .
                    realpath(implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'Supporter.php'])),
                    '',
                    'Generating annotations done',
                    '',
                    '<?php',
                    '',
                    'namespace CSoellinger\SilverStripe\ModelAnnotations\Test\Unit;',
                    '',
                    'use SilverStripe\Dev\TestOnly;',
                    'use SilverStripe\ORM\DataObject;',
                    'use SilverStripe\ORM\ManyManyList;',
                    '',
                    '/**',
                    ' * @internal Testing model',
                    ' *',
                    ' * @method ManyManyList Supports() ...',
                    ' */',
                    ...$this->getSupporterModelOutput(),
                    '',
                    '',
                    '',
                ]),
            ],
            [
                'CSoellinger\SilverStripe\ModelAnnotations\Test\Unit\Team',
                implode(PHP_EOL, [
                    'PARAMS',
                    '| dataClass: CSoellinger\SilverStripe\ModelAnnotations\Test\Unit\Team | dryRun: true |',
                    'addUseStatements: false | createBackupFile: false | quiet: false |',
                    '----------------------------------------------------------------------------------------------' .
                        '------',
                    '',
                    '',
                    'CSoellinger\SilverStripe\ModelAnnotations\Test\Unit\Team',
                    'File: ' .
                    realpath(implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'Team.php'])),
                    '',
                    'Generating annotations done',
                    '',
                    '<?php',
                    '',
                    'namespace CSoellinger\SilverStripe\ModelAnnotations\Test\Unit;',
                    '',
                    'use SilverStripe\Assets\Image;',
                    'use SilverStripe\Dev\TestOnly;',
                    'use SilverStripe\ORM\DataObject;',
                    '',
                    '/**',
                    ' * @property string $Name   Name ...',
                    ' * @property string $Origin Origin ...',
                    ' *',
                    ' * @method \SilverStripe\ORM\HasManyList  Players()    Has many Players {@see Player}',
                    ' * @method \SilverStripe\ORM\ManyManyList Supporters() Many many Supporters {@see TeamSupporter}',
                    ' * @method \SilverStripe\ORM\ManyManyList Images()     Many many Images {@see Image}',
                    ' */',
                    ...$this->getTeamModelOutput(),
                    '',
                    '',
                    '',
                ]),
                implode(PHP_EOL, [
                    'PARAMS',
                    '| dataClass: CSoellinger\SilverStripe\ModelAnnotations\Test\Unit\Team | dryRun: true |',
                    'addUseStatements: true | createBackupFile: false | quiet: false |',
                    '-----------------------------------------------------------------------------------------------' .
                        '-----',
                    '',
                    '',
                    'CSoellinger\SilverStripe\ModelAnnotations\Test\Unit\Team',
                    'File: ' .
                    realpath(implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'Team.php'])),
                    '',
                    'Generating annotations done',
                    '',
                    '<?php',
                    '',
                    'namespace CSoellinger\SilverStripe\ModelAnnotations\Test\Unit;',
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
                    ' * @method HasManyList  Players()    Has many Players {@see Player}',
                    ' * @method ManyManyList Supporters() Many many Supporters {@see TeamSupporter}',
                    ' * @method ManyManyList Images()     Many many Images {@see Image}',
                    ' */',
                    ...$this->getTeamModelOutput(),
                    '',
                    '',
                    '',
                ]),
                implode(PHP_EOL, [
                    '<?php',
                    '',
                    'namespace CSoellinger\SilverStripe\ModelAnnotations\Test\Unit;',
                    '',
                    'use SilverStripe\Assets\Image;',
                    'use SilverStripe\Dev\TestOnly;',
                    'use SilverStripe\ORM\DataObject;',
                    '',
                    '/**',
                    ' * @property string $Name   Name ...',
                    ' * @property string $Origin Origin ...',
                    ' *',
                    ' * @method \SilverStripe\ORM\HasManyList  Players()    Has many Players {@see Player}',
                    ' * @method \SilverStripe\ORM\ManyManyList Supporters() Many many Supporters {@see TeamSupporter}',
                    ' * @method \SilverStripe\ORM\ManyManyList Images()     Many many Images {@see Image}',
                    ' */',
                    ...$this->getTeamModelOutput(),
                    '',
                ]),
            ],
            [
                'CSoellinger\SilverStripe\ModelAnnotations\Test\Unit\TeamSupporter',
                implode(PHP_EOL, [
                    'PARAMS',
                    '| dataClass: CSoellinger\SilverStripe\ModelAnnotations\Test\Unit\TeamSupporter | dryRun: true |',
                    'addUseStatements: false | createBackupFile: false | quiet: false |',
                    '-----------------------------------------------------------------------------------------------'.
                        '-----',
                    '',
                    '',
                    'CSoellinger\SilverStripe\ModelAnnotations\Test\Unit\TeamSupporter',
                    'File: ' .
                    realpath(implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'TeamSupporter.php'])),
                    '',
                    'Generating annotations done',
                    '',
                    '<?php',
                    '',
                    'namespace CSoellinger\SilverStripe\ModelAnnotations\Test\Unit;',
                    '',
                    'use SilverStripe\Dev\TestOnly;',
                    'use SilverStripe\ORM\DataObject;',
                    '',
                    '/**',
                    ' * @property int       $Ranking     Ranking ...',
                    ' * @property int       $TeamID      Team ID',
                    ' * @property Supporter $Supporter   Has one Supporter',
                    ' *',
                    ' * @property Team $Team        Has one Team {@see Team}',
                    ' * @property int  $SupporterID Supporter ID',
                    ' */',
                    ...$this->getTeamSupporterModelOutput(),
                    '',
                    '',
                    '',
                ]),
            ],
        ];
    }

    private function getRequest(
        string $dataClass = null,
        int $dryRun = 1,
        string $method = 'GET',
        string $url = '/dev/tasks/ModelAnnotationsTask'
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
            '        \'Team\' => Team::class . \'.ID\',',
            '    ];',
            '}',
        ];
    }

    /**
     * @return string[]
     */
    private function getSupporterModelOutput(): array
    {
        return [
            'class Supporter extends DataObject implements TestOnly',
            '{',
            '    /**',
            '     * @var array<string,string> undocumented variable',
            '     */',
            '    private static $belongs_many_many = [',
            '        \'Supports\' => Team::class . \'.Supporters\',',
            '    ];',
            '}',
        ];
    }

    /**
     * @return string[]
     */
    private function getTeamSupporterModelOutput(): array
    {
        return [
            'class TeamSupporter extends DataObject implements TestOnly',
            '{',
            '    /**',
            '     * @var array<string,string> undocumented variable',
            '     */',
            '    private static $db = [',
            '        \'Ranking\' => \'Int\',',
            '    ];',
            '',
            '    /**',
            '     * @var array<string,string> undocumented variable',
            '     */',
            '    private static $has_one = [',
            '        \'Team\' => Team::class,',
            '        \'Supporter\' => Supporter::class,',
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
            '        \'LinkTracking\' => \'Boolean\',',
            '    ];',
            '',
            '    /**',
            '     * @var array<string,string> undocumented variable',
            '     */',
            '    private static $has_many = [',
            '        \'Players\' => Player::class,',
            '        \'FileTracking\' => Image::class,',
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
