<?php

namespace CSoellinger\SilverStripe\ModelAnnotation\Util;

use Exception;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;

/**
 * Helper class to handle your silver stripe models which are sub classes
 * from {@link DataObject}.
 */
class DataObjectUtil
{
    /**
     * @var string - Path to the file where the class is located
     */
    public string $file = '';

    /**
     * @var string - Content of the file
     */
    public string $fileContent = '';

    /**
     * @var \ast\Node - Abstract syntax tree of the file
     */
    public \ast\Node $fileAst;

    /**
     * @var DataObject - Data object class instance
     */
    public DataObject $class;

    /**
     * @var bool - Data object extends from site tree. So it has to be a page
     *           model.
     */
    public bool $classIsSiteTree = false;

    /**
     * @var string - Existing class documentation used to look for existing
     *             defined properties or methods
     */
    public string $classDoc = '';

    /**
     * @var \ast\Node - Abstract syntax tree of the data object class
     */
    public \ast\Node $classAst;

    /**
     * @var string[] - All classes in same namespace
     */
    public array $classesInNamespace = [];

    /**
     * @var array<int,array<string,string>> - Use statement names array
     */
    public array $useStatements = [];

    /**
     * @var string[] - Use statement names array
     */
    public array $useStatementNames = [];

    /**
     * @var string[] - Use statement alias array
     */
    public array $useStatementAlias = [];

    /**
     * @var null|\ast\Node last declared use statement we can find inside file ast
     */
    public ?\ast\Node $lastUseStatement = null;

    /**
     * @var bool - Collect types which are not declared in an use statement
     */
    public bool $collectUnused = false;

    /**
     * @var string[] - Array to collect all types which are not defined as use
     *               statement
     */
    public array $unusedTypes = [];

    /**
     * Constructor.
     *
     * @param string $fqn full qualified name of the class
     */
    public function __construct(string $fqn)
    {
        // Check if class is in site tree (e.g. Page)
        $this->classIsSiteTree = in_array(
            'silverstripe\\cms\\model\\sitetree',
            array_keys(ClassInfo::dataClassesFor($fqn))
        );
        // Get the file path by a reflection helper
        $this->file = Util::fileByFqn($fqn);

        // If we did not get a file we are throwing an exception
        if (!$this->file) {
            throw new Exception('Error Processing Request', 1);
        }

        // Save the file content and the abstract syntax tree of the file
        $this->fileContent = (string) file_get_contents($this->file);
        $this->fileAst = \ast\parse_code($this->fileContent, 80);

        // Inject a class instance
        /** @var DataObject $class */
        $class = Injector::inst()->get($fqn);
        $this->class = $class;
        $this->classAst = new \ast\Node();

        // Collect all use statements of the file and extract the class abstract
        // syntax tree.
        $this
            ->getUseStatementsFromAst($this->fileAst)
            ->findLastUse($this->fileAst)
            ->findClassInAst($this->fileAst, ClassInfo::shortName($fqn))
        ;

        // Extract some informations from the class
        /** @var array<string,string> $meta */
        $meta = $this->classAst->children;
        $this->classDoc = $meta['docComment'] ?: '';
        $this->classesInNamespace = $this->getClassesInNamespace(Util::getNamespaceFromFqn($fqn));

        // Get use statement names array
        /** @var string[] $useStatementNames */
        $useStatementNames = array_column($this->useStatements, 'name');

        // Get use statement alias array
        /** @var string[] $useStatementAlias */
        $useStatementAlias = array_column($this->useStatements, 'alias');

        $this->useStatementNames = $useStatementNames;
        $this->useStatementAlias = $useStatementAlias;
    }

    /**
     * Get an associative array which represents a relation property. The method als do some checks
     * if the data type is declared as use statement for example.
     *
     * @example ['dataType' => 'Member', 'variableName' => 'Member', 'description' => 'Member...']
     *
     * @return array<string,string>
     */
    public function getRelationProperty(string $fieldName, string $relationType, string $description): array
    {
        $relationType = $this->shortenDataType($relationType);

        return [
            'dataType' => $relationType,
            'variableName' => $fieldName,
            'description' => $description,
        ];
    }

    /**
     * Get an associative array which represents a relation method. In fact it handles has_many and many_many
     * relations.
     *
     * @example ['dataType' => 'ManyManyList', 'variableName' => 'Supporters', 'description' => 'Supporters...']
     *
     * @return array<string,string>
     */
    public function getRelationMethod(
        string $fieldName,
        string $relationType,
        string $description,
        string $listType = 'SilverStripe\ORM\DataList'
    ): array {
        $listType = $this->shortenDataType($listType);
        $relationType = $this->shortenDataType($relationType);

        return [
            'dataType' => $listType,
            'variableName' => $fieldName,
            'description' => str_replace('{@link fieldType}', '{@link ' . $relationType . '}', $description),
        ];
    }

    /**
     * Collect from file abstract syntax tree all declared use statements.
     *
     * @param mixed $ast
     */
    private function getUseStatementsFromAst($ast): self
    {
        if ($ast instanceof \ast\Node) {
            if ($ast->kind === \ast\AST_USE_ELEM) {
                /** @var array<string,string> $child */
                $child = $ast->children;
                $this->useStatements[] = $child;
            }

            /** @var mixed $child */
            foreach ($ast->children as $child) {
                $this->getUseStatementsFromAst($child);
            }
        }

        return $this;
    }

    /**
     * Get the class abstract syntax tree by the class name.
     *
     * @param mixed $ast
     */
    private function findClassInAst($ast, string $className): self
    {
        if ($ast instanceof \ast\Node) {
            if ($ast->kind === \ast\AST_CLASS) {
                /** @var array<string,string> $meta */
                $meta = $ast->children;

                if ($meta['name'] === $className) {
                    $this->classAst = $ast;
                }
            }

            /** @var mixed $child */
            foreach ($ast->children as $child) {
                $this->findClassInAst($child, $className);
            }
        }

        return $this;
    }

    /**
     * Find last use state from abstract syntax tree.
     *
     * @param mixed $ast
     */
    private function findLastUse($ast): self
    {
        if ($ast instanceof \ast\Node) {
            if ($ast->kind === \ast\AST_USE) {
                $this->lastUseStatement = $ast;
            }

            /** @var \ast\Node $child */
            foreach ($ast->children as $child) {
                $this->findLastUse($child);
            }
        }

        return $this;
    }

    /**
     * Get all classes from a namespace.
     *
     * @return string[]
     */
    private function getClassesInNamespace(string $namespace): array
    {
        $namespace .= '\\';

        $classes = array_filter(ClassInfo::allClasses(), function (string $item) use ($namespace) {
            return substr($item, 0, strlen($namespace)) === $namespace;
        });

        /** @var string[] */
        return array_values($classes);
    }

    /**
     * Format data type. If we collect data types which are not declared as use statement and or not inside same
     * namespace we can shorten the data type.
     */
    private function shortenDataType(string $dataType): string
    {
        // If we have a dot notation we will strip it cause we just need the class name
        if ($pos = strpos($dataType, '.')) {
            $dataType = substr($dataType, 0, $pos);
        }

        // Class name only from fqn
        $dataTypeName = ClassInfo::shortName($dataType);

        // Check if field type is declared as use statement
        $useStatementExists = in_array($dataType, $this->useStatementNames)
            || in_array($dataType, $this->useStatementAlias);
        // Also check if field type is in same namespace
        $inSameNamespace = in_array($dataType, $this->classesInNamespace);

        // By default we take the type from the global namespace
        $dataType = '\\' . $dataType;

        // var_dump($dataType, $useStatementExists, $inSameNamespace, $this->useStatementNames);

        // We can shorten the type if use statement exists or type is in same namespace. Also if we collect all types
        // which are not declared as use statement
        if ($useStatementExists === true || $inSameNamespace === true || $this->collectUnused === true) {
            if ($this->collectUnused === true && $inSameNamespace === false && $useStatementExists === false) {
                $this->unusedTypes[] = trim($dataType, '\\');
            }

            $dataType = $dataTypeName;
        }

        return $dataType;
    }
}
