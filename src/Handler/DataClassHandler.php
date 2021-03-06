<?php

namespace CSoellinger\SilverStripe\ModelAnnotations\Handler;

use CSoellinger\SilverStripe\ModelAnnotations\Task\ModelAnnotationsTask;
use CSoellinger\SilverStripe\ModelAnnotations\Util\Util;
use CSoellinger\SilverStripe\ModelAnnotations\View\DataClassTaskView;
use Psl\Regex;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;

/**
 * Helper class to handle your silver stripe models which are sub classes
 * from {@see DataObject}.
 */
class DataClassHandler
{
    use Injectable;

    /**
     * @var null|\ast\Node - Abstract syntax tree of the data object class
     */
    public ?\ast\Node $ast;

    /**
     * @var string[] - Array to collect all types which are not defined as use
     *               statement
     */
    public array $unusedTypes = [];

    /**
     * @var string - Full qualified name of the class
     */
    private string $fqn;

    /**
     * @var DataClassFileHandler - Handle the file of the class
     */
    private DataClassFileHandler $file;

    /**
     * @var array<int,array<string,string>> - All collected model properties. Collected from db, has_one and belongs_to
     *                                      config
     */
    private array $modelProperties = [];

    /**
     * @var array<int,array<string,string>> - All collected model methods. Collected from has_many, many_many,
     *                                      belongs_many_many config.
     */
    private array $modelMethods = [];

    /**
     * @var string[] - Injected dependencies
     */
    private static array $dependencies = [
        'renderer' => '%$' . DataClassTaskView::class,
    ];

    /**
     * @var DataClassTaskView - Injected data class renderer
     * @psalm-suppress PropertyNotSetInConstructor
     */
    private DataClassTaskView $renderer;

    /**
     * @var Util - Injected util class
     */
    private Util $util;

    /**
     * @var string[] - All classes in same namespace
     */
    private array $classesInSameNamespace = [];

    /**
     * Constructor.
     *
     * @param string $fqn Full qualified name of the class
     */
    public function __construct(string $fqn)
    {
        $this->fqn = $fqn;

        /** @var Util $util */
        $util = Injector::inst()->create(Util::class);
        $this->util = $util;

        /** @var DataClassFileHandler $fileHandler */
        $fileHandler = Injector::inst()->createWithArgs(DataClassFileHandler::class, [$this->util->fileByFqn($fqn)]);
        $this->file = $fileHandler;

        $this->ast = $this->file->getClassAst($fqn);

        $this->classesInSameNamespace = $this->util->getClassesFromNamespace($this->util->getNamespaceFromFqn($fqn));

        $this->fetchModelProperties();
        $this->fetchModelMethods();
    }

    /**
     * Get all missing use statements.
     *
     * @return string[]
     */
    public function getMissingUseStatements(): array
    {
        $this->unusedTypes = array_unique($this->unusedTypes);

        if (count($this->unusedTypes) === 0) {
            return [];
        }

        return array_map(function ($unusedType) {
            return 'use ' . $unusedType . ';';
        }, $this->unusedTypes);
    }

    /**
     * Get the file handler.
     */
    public function getFile(): DataClassFileHandler
    {
        return $this->file;
    }

    /**
     * Get the abstract syntax tree of the class.
     */
    public function getAst(): ?\ast\Node
    {
        return $this->ast;
    }

    /**
     * Get class phpdoc from abstract syntax tree.
     */
    public function getClassPhpDoc(): string
    {
        if ($this->ast === null) {
            return '';
        }

        /** @var array<string,string> $meta */
        $meta = $this->ast->children;

        return $meta['docComment'] ?: '';
    }

    /**
     * Get all db fields, collected from "db", "has_one" and "belongs_to" config,
     * to fetch all possible model phpdoc properties.
     *
     * @return array<int,array<string,string>>
     */
    public function getModelProperties()
    {
        return $this->modelProperties;
    }

    /**
     * Get all db many relations, collected from "has_many", "many_many" and
     * "belongs_many_many" config, to fetch all possible model phpdoc methods.
     *
     * @return array<int,array<string,string>>
     */
    public function getModelMethods()
    {
        return $this->modelMethods;
    }

    /**
     * Generate a class php doc for collected model properties and methods.
     */
    public function generateClassPhpDoc(): string
    {
        // Get space paddings
        $spacePad = ['dataType' => 0, 'variableName' => 0, 'methodName' => 0, 'methodType' => 0];
        foreach ($this->modelProperties as $modelProperty) {
            if (strlen($modelProperty['dataType']) > $spacePad['dataType']) {
                $spacePad['dataType'] = strlen($modelProperty['dataType']);
            }
            if (strlen($modelProperty['variableName']) > $spacePad['variableName']) {
                $spacePad['variableName'] = strlen($modelProperty['variableName']);
            }
        }
        foreach ($this->modelMethods as $modelMethod) {
            if (strlen($modelMethod['variableName']) > $spacePad['methodName']) {
                $spacePad['methodName'] = strlen($modelMethod['variableName']);
            }

            if (strlen($modelMethod['dataType']) > $spacePad['methodType']) {
                $spacePad['methodType'] = strlen($modelMethod['dataType']);
            }
        }

        // Create php docs line by line
        $commentLines = [];
        foreach ($this->modelProperties as $modelProperty) {
            $dataType = str_pad($modelProperty['dataType'], $spacePad['dataType']);
            $variableName = str_pad($modelProperty['variableName'], $spacePad['variableName']);
            $commentLine = ' * @property ' . $dataType . ' $' . $variableName . ' ';
            $commentLine .= $modelProperty['description'];

            $commentLines[] = $commentLine;
        }

        if (count($this->modelProperties) > 0 && count($this->modelMethods) > 0) {
            $commentLines[] = ' *';
        }

        foreach ($this->modelMethods as $modelMethod) {
            $variableName = str_pad($modelMethod['variableName'] . '()', $spacePad['methodName'] + 2);
            $dataType = str_pad($modelMethod['dataType'], $spacePad['methodType']);
            $commentLine = ' * @method ' . $dataType . ' ' . $variableName . ' ' . $modelMethod['description'];

            $commentLines[] = $commentLine;
        }

        $commentLines[] = ' */';

        $oldClassDoc = $this->getClassPhpDoc();
        $classDocAdd = implode(PHP_EOL, $commentLines);

        if ($oldClassDoc === '') {
            return '/**' . PHP_EOL . $classDocAdd;
        }

        return Regex\replace($oldClassDoc, '/\*\/$/m', '*' . PHP_EOL) . $classDocAdd;
    }

    /**
     * Set data class renderer.
     */
    final public function setRenderer(DataClassTaskView $renderer): self
    {
        $this->renderer = $renderer;

        return $this;
    }

    /**
     * Get data class renderer.
     */
    public function getRenderer(): DataClassTaskView
    {
        return $this->renderer;
    }

    private function fetchModelProperties(bool $filterExistingAnnotations = true): void
    {
        foreach (['db', 'has_one', 'belongs_to'] as $configKey) {
            /** @var array<string,string> $fieldConfigs */
            $fieldConfigs = Config::forClass($this->fqn)->get($configKey);

            foreach ($fieldConfigs as $fieldName => $fieldType) {
                if ($configKey === 'db') {
                    $fieldType = $this->util->silverStripeToPhpType(strtolower($fieldType));
                    $description = $fieldName . ' ...';
                } else {
                    $fieldType = $this->shortenDataType($fieldType);
                    $description = str_replace('_', ' ', ucfirst($configKey)) . ' '
                         . $fieldName . ' {@see ' . $fieldType . '}';
                }

                $this->modelProperties[] = $this->getModelPropertyData(
                    $fieldName,
                    $fieldType,
                    $filterExistingAnnotations,
                    $description
                );

                // For has one relations we also add the ID field
                if ($configKey === 'has_one') {
                    $this->modelProperties[] = $this->getModelPropertyData(
                        $fieldName . 'ID',
                        'int',
                        $filterExistingAnnotations,
                        $fieldName . ' ID'
                    );
                }
            }
        }

        $this->modelProperties = array_filter($this->modelProperties, function ($modelProperty) {
            return count($modelProperty) > 0;
        });
    }

    /**
     * Gert model property array data.
     *
     * @return array<string,string>
     */
    private function getModelPropertyData(
        string $fieldName,
        string $fieldType,
        bool $filterExistingAnnotations,
        string $description
    ) {
        if ($this->checkField($fieldName) === true) {
            return [];
        }

        $matches = [];
        if ($filterExistingAnnotations === true) {
            $regex = '/@property[\s]*' . preg_quote($fieldType, '/') . '[\s]*\\$' .
                preg_quote($fieldName, '/') . '[\s]*/m';

            preg_match_all($regex, $this->getClassPhpDoc(), $matches, PREG_SET_ORDER, 0);
        }

        if (count($matches) > 0) {
            return [];
        }

        return [
            'dataType' => $fieldType,
            'variableName' => $fieldName,
            'description' => $description,
        ];
    }

    private function fetchModelMethods(bool $filterExistingAnnotations = true): void
    {
        $hasManyList = 'SilverStripe\ORM\HasManyList';
        $manyManyList = 'SilverStripe\ORM\ManyManyList';

        // List relations
        $relations = [
            'has_many' => ['list' => $hasManyList],
            'many_many' => ['list' => $manyManyList],
            'belongs_many_many' => ['list' => $manyManyList],
        ];

        foreach ($relations as $key => $relation) {
            /** @var array<string,array<string,string>|string> $fieldConfigs */
            $fieldConfigs = Config::forClass($this->fqn)->get($key);

            foreach ($fieldConfigs as $fieldName => $fieldType) {
                $this->modelMethods[] = $this->getModelMethodData(
                    $fieldName,
                    $fieldType,
                    $relation,
                    $manyManyList,
                    $key,
                    $filterExistingAnnotations
                );
            }
        }

        $this->modelMethods = array_filter($this->modelMethods, function ($modelMethod) {
            return count($modelMethod) > 0;
        });
    }

    /**
     * Get model method data array.
     *
     * @param array<string,string>|string $fieldType
     * @param array<string,string>        $relation
     *
     * @return array<string,string>
     */
    private function getModelMethodData(
        string $fieldName,
        $fieldType,
        $relation,
        string $manyManyList,
        string $relationKey,
        bool $filterExistingAnnotations
    ) {
        if ($this->checkField($fieldName) === true) {
            return [];
        }

        if (is_array($fieldType) === true) {
            $fieldType = $fieldType['through'];
            $relation['list'] = $manyManyList;
        }

        $fieldType = $this->shortenDataType($fieldType);
        $listType = $this->shortenDataType($relation['list']);
        $description = str_replace('_', ' ', ucfirst($relationKey)) . ' '
            . $fieldName . ' {@see ' . $fieldType . '}';

        if ($filterExistingAnnotations === true) {
            $matches = [];
            $regex = '/@method[\s]*' . preg_quote($listType, '/') . '[\s]*'
                . preg_quote($fieldName, '/') . '\(.*\)[\s]*/m';

            preg_match_all($regex, $this->getClassPhpDoc(), $matches, PREG_SET_ORDER, 0);

            if (count($matches) > 0) {
                return [];
            }
        }

        return [
            'dataType' => $listType,
            'variableName' => $fieldName,
            'description' => $description,
        ];
    }

    /**
     * Check if a db field should not be included.
     */
    private function checkField(string $fieldName): bool
    {
        $config = Config::forClass(ModelAnnotationsTask::class);
        $siteTreeFields = (array) $config->get('siteTreeFields');
        $ignoreFields = (array) $config->get('ignoreFields');
        $dataClasses = ClassInfo::dataClassesFor($this->fqn);
        $isSiteTree = in_array('silverstripe\\cms\\model\\sitetree', array_keys($dataClasses), true);
        $isSiteTreeField = in_array($fieldName, $siteTreeFields, true);
        $ignoreField = in_array($fieldName, $ignoreFields, true);

        if (($isSiteTree === true && $isSiteTreeField === true) || $ignoreField === true) {
            return true;
        }

        return false;
    }

    /**
     * Format data type. If we collect data types which are not declared as use statement and or not inside same
     * namespace we can shorten the data type.
     */
    private function shortenDataType(string $dataType): string
    {
        $pos = strpos($dataType, '.');

        // If we have a dot notation we will strip it cause we just need the class name
        if ($pos !== false) {
            $dataType = substr($dataType, 0, $pos);
        }

        // Class name only from fqn
        $dataTypeName = ClassInfo::shortName($dataType);

        $useStatements = $this->file->getUseStatementsFromAst();

        // Check if field type is declared as use statement
        $useStatement = array_filter($useStatements, function (\ast\Node $useStatement) use ($dataType) {
            /** @var array<string,string> */
            $meta = $useStatement->children;

            return $meta['name'] === $dataType || $meta['alias'] === $dataType;
        });
        $useStatementExists = (count($useStatement) > 0);

        // Also check if field type is in same namespace
        $inSameNamespace = in_array($dataType, $this->classesInSameNamespace, true);

        // By default we take the type from the global namespace
        $dataType = '\\' . $dataType;

        $config = Config::forClass(ModelAnnotationsTask::class);
        $collectNotDeclaredUse = (bool) $config->get('addUseStatements');

        // We can shorten the type if use statement exists or type is in same namespace. Also if we collect all types
        // which are not declared as use statement
        if ($useStatementExists === true || $inSameNamespace === true || $collectNotDeclaredUse === true) {
            if ($collectNotDeclaredUse === true && $inSameNamespace === false && $useStatementExists === false) {
                $this->unusedTypes[] = trim($dataType, '\\');
            }

            $dataType = $dataTypeName;
        }

        return $dataType;
    }
}
