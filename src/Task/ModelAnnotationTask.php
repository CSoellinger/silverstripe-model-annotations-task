<?php

namespace CSoellinger\SilverStripe\ModelAnnotation\Task;

use CSoellinger\SilverStripe\ModelAnnotation\Util\DataObjectUtil;
use CSoellinger\SilverStripe\ModelAnnotation\Util\Util;
use Exception;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;

/**
 * This task generates annotations for your silver stripe model. This is mostly
 * helpful for the IDE autocompletion. Please always make a dry run before
 * really writing to the files.
 */
class ModelAnnotationTask extends BuildTask
{
    use Configurable;

    /**
     * @var string Task title
     */
    protected $title = 'IDE Model Annotations';

    /**
     * @var string[] Site tree fields which are ignored
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
     * @var bool create a backup file before writing the model
     */
    private static bool $createBackupFile = false;

    /**
     * @var bool only print what normally would be written to file
     */
    private static bool $dryRun = false;

    /**
     * @var bool no output
     */
    private static bool $quiet = false;

    /**
     * @var bool If we set this to true it will add use statements for data
     *           types. It also shortens down the data type inside php doc.
     */
    private static bool $addUseStatements = false;

    /**
     * {@inheritdoc}
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return implode('', [
            'Add ide annotations for dataobject models. ',
            'This is helpful to get auto completions for db fields ',
            'and relations in your ide (most should support it). Be ',
            'careful: This software writes your files. So it normally ',
            'should not crash your code be aware that this could happen. ',
            'So turning on backup files config could be a good idea if ',
            'you have very complex data objects ;-)',
        ]);
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
        $quiet = $this->getVarConfigValue('quiet', $request);

        /** @psalm-suppress UndefinedDocblockClass */
        Environment::increaseTimeLimitTo(600);

        $this->echo(date('d.m.Y H:m') . PHP_EOL, $quiet);

        // Check config and get vars. Get vars will overrule the config
        $dryRun = $this->getVarConfigValue('dryRun', $request);
        $addUseStatements = $this->getVarConfigValue('addUseStatements', $request);
        $createBackupFile = $this->getVarConfigValue('createBackupFile', $request);

        /** @var string $dataClass */
        $dataClass = $request->getVar('dataClass');
        $dataClass = strtolower($dataClass);

        // Loop all classes which are extending from data object
        foreach ($this->getDataClasses($dataClass) as $dataClass) {
            /** @var array<int,array<string,string>> $modelProperties */
            $modelProperties = [];

            /** @var array<int,array<string,string>> $modelMethods */
            $modelMethods = [];
            $handler = new DataObjectUtil($dataClass);
            $handler->collectUnused = $addUseStatements;

            // DB
            /** @var array<string,string> $fieldConfigs */
            $fieldConfigs = $handler->class->config()->get('db');
            foreach ($fieldConfigs as $fieldName => $fieldType) {
                if ($this->checkDbField($fieldName, $handler) === true) {
                    continue;
                }

                $modelProperties[] = [
                    'dataType' => Util::silverStripeToPhpTyping(strtolower($fieldType)),
                    'variableName' => $fieldName,
                    'description' => $fieldName . ' ...',
                ];
            }

            // Relations
            $relations = ['has_one', 'belongs_to'];

            foreach ($relations as $relation) {
                /** @var array<string,string> $fieldConfigs */
                $fieldConfigs = $handler->class->config()->get($relation);

                foreach ($fieldConfigs as $fieldName => $fieldType) {
                    if ($this->checkDbField($fieldName, $handler) === true) {
                        continue;
                    }

                    $description = str_replace('_', ' ', ucfirst($relation)) . ' ' . $fieldName;
                    $modelProperties[] = $handler->getRelationProperty($fieldName, $fieldType, $description);

                    // For has one relations we also add the ID field
                    if ($relation === 'has_one') {
                        $modelProperties[] = [
                            'dataType' => 'int',
                            'variableName' => $fieldName . 'ID',
                            'description' => $fieldName . ' ID',
                        ];
                    }
                }
            }

            // List relations
            $relations = [
                'has_many' => ['list' => 'SilverStripe\ORM\HasManyList'],
                'many_many' => ['list' => 'SilverStripe\ORM\ManyManyList'],
                'belongs_many_many' => ['list' => 'SilverStripe\ORM\ManyManyList'],
            ];

            foreach ($relations as $key => $relation) {
                /** @var array<string,array<string,string>|string> $fieldConfigs */
                $fieldConfigs = $handler->class->config()->get($key);

                foreach ($fieldConfigs as $fieldName => $fieldType) {
                    if ($this->checkDbField($fieldName, $handler) === true) {
                        continue;
                    }

                    if (is_array($fieldType) === true) {
                        $fieldType = $fieldType['through'];
                        $relation['list'] = 'SilverStripe\ORM\ManyManyList';
                    }

                    $description = str_replace('_', ' ', ucfirst($key)) . ' ' . $fieldName . ' {@link fieldType}';
                    $modelMethods[] = $handler
                        ->getRelationMethod($fieldName, $fieldType, $description, $relation['list'])
                    ;
                }
            }

            // After collecting all possible properties and methods we filter out all which are already declared as
            // php doc.
            $getFilterFunction = function (string $regex, DataObjectUtil $handler): callable {
                return function (array $item) use ($regex, $handler): bool {
                    /** @var array<string,string> $item */
                    $item = $item;
                    $matches = [];
                    $regex = str_replace('VARIABLE_NAME', preg_quote($item['variableName'], '/'), $regex);
                    preg_match_all($regex, $handler->classDoc, $matches, PREG_SET_ORDER, 0);

                    return count($matches) === 0;
                };
            };

            $propertiesFilter = $getFilterFunction('/@property[\s]*[\w]*[\s]*\\$VARIABLE_NAME[\s]*.*$/m', $handler);
            $methodsFilter = $getFilterFunction('/@method[\s]*[\w\\\\]*[\s]*VARIABLE_NAME\(.*\)[\s]*.*$/m', $handler);
            $modelProperties = array_filter($modelProperties, $propertiesFilter);
            $modelMethods = array_filter($modelMethods, $methodsFilter);

            // Before writing the php doc we are looking if we need to insert some use statements.
            if (count($handler->unusedTypes) > 0) {
                $handler->unusedTypes = array_unique($handler->unusedTypes);
                $contentLines = explode(PHP_EOL, $handler->fileContent);
                $atLine = 1;

                if ($handler->lastUseStatement) {
                    $atLine = (int) $handler->lastUseStatement->lineno;
                }

                foreach ($handler->unusedTypes as $index => $unusedType) {
                    $handler->unusedTypes[$index] = 'use ' . $unusedType . ';';
                }

                array_splice($contentLines, $atLine, 0, $handler->unusedTypes);

                $handler->fileContent = implode(PHP_EOL, $contentLines);
            }

            // Only if we have data to process
            if (count($modelProperties) > 0 || count($modelMethods) > 0) {
                // Get space paddings (TODO: maybe we find a better way, this looks hacky)
                $spacePad = ['dataType' => 0, 'variableName' => 0, 'methodName' => 0, 'methodType' => 0];
                foreach ($modelProperties as $modelProperty) {
                    if (strlen($modelProperty['dataType']) > $spacePad['dataType']) {
                        $spacePad['dataType'] = strlen($modelProperty['dataType']);
                    }
                    if (strlen($modelProperty['variableName']) > $spacePad['variableName']) {
                        $spacePad['variableName'] = strlen($modelProperty['variableName']);
                    }
                }
                foreach ($modelMethods as $modelMethod) {
                    if (strlen($modelMethod['variableName']) > $spacePad['methodName']) {
                        $spacePad['methodName'] = strlen($modelMethod['variableName']);
                    }

                    if (strlen($modelMethod['dataType']) > $spacePad['methodType']) {
                        $spacePad['methodType'] = strlen($modelMethod['dataType']);
                    }
                }

                // Create php docs line by line
                $commentLines = [];
                foreach ($modelProperties as $modelProperty) {
                    $dataType = str_pad($modelProperty['dataType'], $spacePad['dataType']);
                    $variableName = str_pad($modelProperty['variableName'], $spacePad['variableName']);
                    $commentLine = ' * @property ' . $dataType . ' $' . $variableName . ' ';
                    $commentLine .= $modelProperty['description'];

                    $commentLines[] = $commentLine;
                }

                if (count($modelProperties) > 0 && count($modelMethods) > 0) {
                    $commentLines[] = ' *';
                }

                foreach ($modelMethods as $modelMethod) {
                    $variableName = str_pad($modelMethod['variableName'] . '()', $spacePad['methodName'] + 2);
                    $dataType = str_pad($modelMethod['dataType'], $spacePad['methodType']);
                    $commentLine = ' * @method ' . $dataType . ' ' . $variableName . ' ' . $modelMethod['description'];

                    $commentLines[] = $commentLine;
                }

                $commentLines[] = ' */';
                $classDocAdd = implode(PHP_EOL, $commentLines);

                // After generating the docs we are checking if the class already has a class comment or if we make the
                // first one.
                if ($handler->classDoc) {
                    $classDocAdd = preg_replace('/\*\/$/m', '*' . PHP_EOL, $handler->classDoc) . $classDocAdd;
                    $handler->fileContent = str_replace($handler->classDoc, $classDocAdd, $handler->fileContent);
                } else {
                    $classDocAdd = '/**' . PHP_EOL . $classDocAdd;
                    $contentLines = explode(PHP_EOL, $handler->fileContent);

                    $atLine = 1;

                    if ($handler->classAst->lineno) {
                        $atLine = (((int) $handler->classAst->lineno) + count($handler->unusedTypes)) - 1;
                    }

                    array_splice($contentLines, $atLine, 0, explode(PHP_EOL, $classDocAdd));

                    $handler->fileContent = implode(PHP_EOL, $contentLines);
                }
            }

            $headline = '<h2 style="margin-bottom: 0; padding-bottom: 0;">' . $dataClass . '</h2>';
            $headline .= '<a name="' . $dataClass . '"></a>';

            $this
                ->echo('', $quiet)
                ->echo($headline, $quiet)
                ->echo('<h3 style="margin-top: 0; padding-top: 0;">' . $handler->file . '</h3>', $quiet)
            ;

            // At dry run we only print the file content (what we normally would write to the class file)
            if ($dryRun === true) {
                if ($quiet === false) {
                    echo Environment::isCli() ?
                        $handler->fileContent :
                        '<pre><code>' . htmlentities($handler->fileContent) . '</code></pre>';
                }
            } else {
                // Last check if we really have to write to the file or if we have no changes
                if (count($modelProperties) > 0 || count($modelMethods) > 0) {
                    // Backup file if set
                    if ($createBackupFile === true) {
                        $backupFile = $handler->file . '.bck';
                        $cnt = 1;

                        while (file_exists($backupFile)) {
                            $backupFile = $handler->file . '.' . $cnt . '.bck';
                            ++$cnt;
                        }

                        $this->echo('<p>Creating backup file at ' . $backupFile . '</p>', $quiet);

                        copy($handler->file, $backupFile);
                    }

                    $this->echo('<p>Writing file ' . $handler->file . '</p>', $quiet);

                    file_put_contents($handler->file, $handler->fileContent);
                } else {
                    $this->echo('<p>No update for ' . $handler->file . '</p>', $quiet);
                }
            }

            $this->echo(Environment::isCli() ? '-----------------------' : '<hr />', $quiet);
        }

        $this->echo(PHP_EOL . 'Task finished' . PHP_EOL . PHP_EOL, $quiet);
    }


    /**
     * Print out a string
     *
     * @param string $str
     * @param bool   $quiet
     *
     * @return self
     */
    private function echo(string $str, bool $quiet = false): self
    {
        if ($quiet === true) {
            return $this;
        }

        if (Environment::isCli() === true) {
            echo strip_tags($str) . PHP_EOL;

            return $this;
        }

        // @codeCoverageIgnoreStart
        echo $str;

        return $this;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get all sub classes for data object. Normally this should be all models. Vendor
     * models will be filtered out.
     *
     * @return string[]
     */
    private function getDataClasses(string $dataClass = ''): array
    {
        /** @var string[] */
        $dataClasses = ClassInfo::subclassesFor(DataObject::class);

        // Filter all classes from vendor
        $dataClasses = array_filter($dataClasses, function (string $dataClass) {
            $file = Util::fileByFqn($dataClass);
            $vendorPath = ((string) BASE_PATH) . DIRECTORY_SEPARATOR . 'vendor';

            return Util::strStartsWith($file, $vendorPath) === false;
        });

        if ($dataClass) {
            if (isset($dataClasses[$dataClass]) === false) {
                throw new Exception('Data class "' . $dataClass . '" does not exist', 1);
            }

            $dataClasses = [$dataClasses[$dataClass]];
        }

        return $dataClasses;
    }

    /**
     * Check if model field should be ignored.
     * TODO: This should be moved to the handler..
     *
     * @param string           $fieldName
     * @param DataObjectUtil $handler
     *
     * @return bool
     */
    private function checkDbField(string $fieldName, DataObjectUtil $handler): bool
    {
        $siteTreeFields = (array) $this->config()->get('siteTreeFields');
        $ignoreFields = (array) $this->config()->get('ignoreFields');

        if (($handler->classIsSiteTree === true && in_array($fieldName, $siteTreeFields) === true)
            || in_array($fieldName, $ignoreFields) === true
        ) {
            return true;
        }

        return false;
    }

    /**
     * Helper to get config value which can be overruled by get var.
     *
     * @param string      $key
     * @param HTTPRequest $request
     *
     * @return bool
     */
    private function getVarConfigValue(string $key, HTTPRequest $request): bool
    {
        return (bool) ($request->getVar($key) ?? $this->config()->get($key));
    }
}
