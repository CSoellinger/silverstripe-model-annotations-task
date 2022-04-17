<?php

namespace CSoellinger\SilverStripe\ModelAnnotation\Task;

use CSoellinger\SilverStripe\ModelAnnotation\Handler\DataClassHandler;
use CSoellinger\SilverStripe\ModelAnnotation\Util\Util;
use CSoellinger\SilverStripe\ModelAnnotation\View\DataClassTaskView;
use Error;
use Exception;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Dev\CliDebugView;
use SilverStripe\Dev\DebugView;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;

/**
 * This task generates annotations for your silver stripe model. This is mostly
 * helpful for the IDE autocompletion. Please always make a dry run before
 * really writing to the files.
 */
class ModelAnnotationsTask extends BuildTask
{
    use Configurable;

    /**
     * @var string {@inheritDoc}
     */
    protected $title = 'Model Annotations Generator';

    /**
     * @var string {@inheritDoc}
     */
    protected $description = "
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

    /**
     * @var string {@inheritDoc}
     */
    private static string $segment = 'ModelAnnotationsTask';

    /**
     * @var string[] site tree fields which are ignored if the data object extends
     *               from it
     */
    private static array $siteTreeFields = [
        'URLSegment',
        'Title',
        'MenuTitle',
        'Content',
        'MetaDescription',
        'ExtraMeta',
        'ReportClass',
        'Sort',
        'ShowInMenus',
        'ShowInSearch',
        'HasBrokenFile',
        'HasBrokenLink',
        'ViewerGroups',
        'EditorGroups',
        'Parent',
        'BackLinks',
        'VirtualPages',
    ];

    /**
     * @var string[] custom field which will be ignored
     */
    private static array $ignoreFields = [
        'LinkTracking',
        'FileTracking',
    ];

    /**
     * @var bool Create a backup file before writing the model. You
     *           can set this variable also as $_GET var.
     */
    private static bool $createBackupFile = false;

    /**
     * @var bool Only print what normally would be written to file. You
     *           can set this variable also as $_GET var.
     */
    private static bool $dryRun = true;

    /**
     * @var bool If we set this to true it will add use statements for data
     *           types. It also shortens down the data type inside php doc. You
     *           can set this variable also as $_GET var.
     */
    private static bool $addUseStatements = false;

    /**
     * @var bool Prints no output on true.
     */
    private static bool $quiet = false;

    /**
     * @var array<string,string> Auto injected dependencies
     */
    private static $dependencies = [
        'logger' => '%$' . LoggerInterface::class,
        'util' => '%$' . Util::class,
        'request' => '%$' . HTTPRequest::class,
    ];

    /**
     * @var Logger Logger
     * @psalm-suppress PropertyNotSetInConstructor
     */
    private $logger;

    /**
     * @var Util Util helper class
     * @psalm-suppress PropertyNotSetInConstructor
     */
    private $util;

    /**
     * @var HTTPRequest Silverstripe http request class
     * @psalm-suppress PropertyNotSetInConstructor
     */
    private $request;

    /**
     * @var DebugView|CliDebugView Custom injected renderer. Depends if
     * environment is on cli or not.
     */
    private $renderer;

    public function __construct()
    {
        if (Director::is_cli() === true) {
            /** @var CliDebugView $renderer */
            $renderer = Injector::inst()->get(CliDebugView::class);
            $this->renderer = $renderer;
        } else {
            /** @var DebugView $renderer */
            $renderer = Injector::inst()->get(DebugView::class);
            $this->renderer = $renderer;
        }
    }

    /**
     * Task will search for all data object classes and try to add necessary
     * annotations. If there are already some defined it should leave them
     * and just add the annotations which not exists.
     *
     * @param HTTPRequest $request
     */
    public function run($request)
    {
        $quiet = $this->getConfigVarValue('quiet');

        if ($quiet === false) {
            echo $this->getRenderer()->renderHeader();
        }

        if (Director::isDev() === false) {
            $error = 'You can run this task only inside a dev environment. ';
            $error .= 'Your environment is: ' . Director::get_environment_type();

            $this->printError($error);
        }

        if (Permission::check('ADMIN') === false && Director::is_cli() === false) {
            $this->printError('Inside browser only admins are allowed to run this task.');
        }

        // Set max time and memory limit
        /** @psalm-suppress UndefinedDocblockClass */
        Environment::increaseTimeLimitTo();

        /** @psalm-suppress UndefinedDocblockClass */
        Environment::setMemoryLimitMax(-1);

        /** @psalm-suppress UndefinedDocblockClass */
        Environment::increaseMemoryLimitTo(-1);

        // Check config and get vars. Get vars will overrule the config
        $dryRun = $this->getConfigVarValue('dryRun');
        $addUseStatements = $this->getConfigVarValue('addUseStatements');
        $createBackupFile = $this->getConfigVarValue('createBackupFile');

        /** @var string $dataClass */
        $dataClass = $request->getVar('dataClass');

        if ($quiet === false) {
            $paramsText = '| ' . implode(' | ', [
                'dataClass: ' . ($dataClass ?: 'All'),
                'dryRun: ' . ($dryRun ? 'true' : 'false'),
                'addUseStatements: ' . ($addUseStatements ? 'true' : 'false'),
                'createBackupFile: ' . ($createBackupFile ? 'true' : 'false'),
                'quiet: false',
            ]) . ' |';

            echo $this->getRenderer()->renderInfo('Params', $paramsText);
        }

        $dataClass = strtolower($dataClass);
        $dataClasses = $this->getDataClasses();

        if ($dataClass) {
            if (isset($dataClasses[$dataClass]) === false) {
                $this->printError('Data class "' . $dataClass . '" does not exist');
            }

            $dataClasses = [$dataClasses[$dataClass]];
        }

        foreach ($dataClasses as $index => $fqn) {
            try {
                /** @var DataClassHandler $dataClassHandler */
                $dataClassHandler = Injector::inst()->createWithArgs(DataClassHandler::class, [$fqn]);
                $dataClassAst = $dataClassHandler->getAst();
                if (!$dataClassAst) {
                    continue;
                }

                if ($quiet === false) {
                    $dataClassHandler
                        ->getRenderer()
                        ->renderHeader($fqn, $dataClassHandler->getFile()->getPath())
                    ;
                }

                // Update file content with missing use statements
                $missingUseStatements = $dataClassHandler->getMissingUseStatements();
                if (count($missingUseStatements) > 0) {
                    $atLine = 2;
                    $useStatements = $dataClassHandler->getFile()->getUseStatementsFromAst();

                    if (count($useStatements) > 0) {
                        $lastUse = end($useStatements);
                        $atLine = ((int) $lastUse->lineno) + 1;
                    } else {
                        $namespaceAst = $dataClassHandler->getFile()->getNamespaceAst();

                        if ($namespaceAst) {
                            $atLine = ((int) $namespaceAst->lineno) + 1;
                        }
                    }

                    $dataClassHandler
                        ->getFile()
                        ->addText(implode(PHP_EOL, $missingUseStatements), $atLine)
                    ;
                }

                $modelProperties = $dataClassHandler->getModelProperties();
                $modelMethods = $dataClassHandler->getModelMethods();

                if (count($modelProperties) > 0 || count($modelMethods) > 0) {
                    $oldPhpDoc = $dataClassHandler->getClassPhpDoc();
                    $newPhpDoc = $dataClassHandler->generateClassPhpDoc();

                    if ($oldPhpDoc === '') {
                        $atLine = ((int) $dataClassAst->lineno) + count($missingUseStatements);

                        $dataClassHandler
                            ->getFile()
                            ->addText($newPhpDoc, $atLine)
                        ;
                    } else {
                        $dataClassHandler->getFile()->contentReplace($oldPhpDoc, $newPhpDoc);
                    }

                    if ($dryRun === false) {
                        $dataClassHandler->getFile()->write();
                    }
                }

                if ($quiet === false) {
                    $dataClassHandler->getRenderer()->renderMessage('Generating annotations done');

                    if ($dryRun === true) {
                        $dataClassHandler
                            ->getRenderer()
                            ->renderSource(
                                $dataClassHandler->getFile()->getPath(),
                                $dataClassHandler->getFile()->getContent()
                            )
                        ;

                        if ($index !== array_key_last($dataClasses)) {
                            $dataClassHandler->getRenderer()->renderHr();
                        }
                    }
                }
            } catch (\Throwable $th) {
                $message = 'Error generating annotations';

                /** @var DataClassTaskView $view */
                $view = Injector::inst()->get(DataClassTaskView::class);

                if ($quiet === false) {
                    $view
                        ->renderHeader($fqn, $this->util->fileByFqn($fqn))
                        ->renderMessage($message, 'error');

                    if ($index !== array_key_last($dataClasses)) {
                        $view->renderHr();
                    }
                } else {
                    $this->logger->error($message);
                }

                $this->logger->error($th->__toString());
            }
        }

        if ($quiet === false) {
            echo $this->getRenderer()->renderFooter();
        }
    }

    /**
     * Set http request dependency.
     */
    public function setRequest(HTTPRequest $request): self
    {
        $this->request = $request;

        return $this;
    }

    /**
     * Set logger dependency.
     */
    public function setLogger(Logger $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Set util dependency.
     */
    public function setUtil(Util $util): self
    {
        $this->util = $util;

        return $this;
    }

    /**
     * Helper to get config value which can be overruled by get var.
     */
    public function getConfigVarValue(string $key): bool
    {
        $value = (bool) ($this->request->getVar($key) ?? $this->config()->get($key));

        if ($value !== (bool) $this->config()->get($key)) {
            $this->config()->set($key, $value);
        }

        return $value;
    }

    /**
     * Get all sub classes for data object. Normally this should be all models. Vendor
     * models will be filtered out.
     *
     * @return string[]
     */
    private function getDataClasses(): array
    {
        /** @var string[] */
        $dataClasses = ClassInfo::subclassesFor(DataObject::class);

        // Exclude all classes from vendor
        return array_filter($dataClasses, function (string $dataClass) {
            $file = $this->util->fileByFqn($dataClass);
            $vendorPath = ((string) BASE_PATH) . DIRECTORY_SEPARATOR . 'vendor';

            return $this->util->strStartsWith($file, $vendorPath) === false;
        });
    }

    /**
     * Print an error an exit.
     *
     * @param string $error Error message
     */
    private function printError(string $error): void
    {
        if ($this->getConfigVarValue('quiet') === true) {
            throw new Error($error);
        }

        echo $this->getRenderer()->renderError('GET /dev/tasks/ModelAnnotationsTask', 1, $error, __FILE__, 0);
        echo $this->getRenderer()->renderFooter();

        throw new Error($error);
    }

    /**
     * Get renderer depending if we are on a cli or not
     *
     * @return DebugView|CliDebugView
     */
    private function getRenderer()
    {
        return $this->renderer;
    }
}
