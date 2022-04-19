<?php

namespace CSoellinger\SilverStripe\ModelAnnotations\Handler;

use CSoellinger\SilverStripe\ModelAnnotations\Task\ModelAnnotationsTask;
use Exception;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injectable;
use Psl\Filesystem as File;

/**
 * Manages the file from the data class. Saving path, content and some other
 * things.
 */
class DataClassFileHandler
{
    use Injectable;

    /**
     * @var string File path
     */
    private string $path;

    /**
     * @var string File content
     */
    private string $content;

    /**
     * @var \ast\Node File abstract syntax tree
     */
    private \ast\Node $ast;

    /**
     * Constructor. Given path has to be a valid file.
     */
    public function __construct(string $path)
    {
        // If we did not get a valid file we are throwing an exception
        if ($path === '' || File\exists($path) === false || File\is_file($path) === false) {
            throw new Exception('Error with file at path "' . $path . '"', 1);
        }

        $this->path = $path;
        $this->content = File\read_file($path);
        $this->ast = \ast\parse_code($this->content, 80);
    }

    /**
     * Get the file path.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get the file content.
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Get the file abstract syntax tree.
     */
    public function getAst(): \ast\Node
    {
        return $this->ast;
    }

    /**
     * Get namespace from abstract syntax tree.
     */
    public function getNamespaceAst(): ?\ast\Node
    {
        return $this->searchNamespaceAst($this->ast);
    }

    /**
     * Get class from abstract syntax tree.
     */
    public function getClassAst(string $fqn): ?\ast\Node
    {
        return $this->searchClassAst($this->ast, ClassInfo::shortName($fqn));
    }

    /**
     * Get all use statements from abstract syntax tree.
     *
     * @return \ast\Node[]
     */
    public function getUseStatementsFromAst()
    {
        return $this->searchUseStatementsInAst($this->ast);
    }

    /**
     * Add some text to {@see self::$content} at a specified line.
     *
     * @param string $text   - Text to add
     * @param int    $atLine - Insert at this line number
     */
    public function addText(string $text, int $atLine = 1): self
    {
        if ($atLine <= 0) {
            $atLine = 1;
        }

        $contentLines = explode(PHP_EOL, $this->content);

        array_splice($contentLines, $atLine - 1, 0, $text);

        $this->content = implode(PHP_EOL, $contentLines);

        return $this;
    }

    /**
     * Replace string in {@see self::$content}.
     */
    public function contentReplace(string $search, string $replace): self
    {
        $this->content = str_replace($search, $replace, $this->content);

        return $this;
    }

    /**
     * Write {@see self::$content} to the {@see self::$path}. Optionally create
     * a backup file.
     */
    public function write(): self
    {
        $createBackupFile = (bool) Config::forClass(ModelAnnotationsTask::class)->get('createBackupFile');

        if ($createBackupFile === true) {
            $backupFile = $this->path . '.bck';
            $cnt = 1;

            while (file_exists($backupFile)) {
                $backupFile = $this->path . '.' . $cnt . '.bck';
                ++$cnt;
            }

            File\copy($this->path, $backupFile, false);
        }

        File\write_file($this->path, $this->content);

        return $this;
    }

    /**
     * Search for a namespace declaration.
     *
     * @param \ast\Node|string $ast - Abstract syntax tree to search in
     */
    private function searchNamespaceAst($ast): ?\ast\Node
    {
        if ($ast instanceof \ast\Node) {
            if ($ast->kind === \ast\AST_NAMESPACE) {
                return $ast;
            }

            /** @var \ast\Node $child */
            foreach ($ast->children as $child) {
                $childAst = $this->searchNamespaceAst($child);

                if ($childAst !== null) {
                    return $childAst;
                }
            }
        }

        return null;
    }

    /**
     * Search for a class abstract syntax tree.
     *
     * @param \ast\Node|string $ast       - Abstract syntax tree to search in
     * @param string           $className - The class to search for
     */
    private function searchClassAst($ast, string $className): ?\ast\Node
    {
        if ($ast instanceof \ast\Node) {
            if ($ast->kind === \ast\AST_CLASS) {
                /** @var array<string,string> $meta */
                $meta = $ast->children;

                if ($meta['name'] === $className) {
                    return $ast;
                }
            }

            /** @var \ast\Node $child */
            foreach ($ast->children as $child) {
                $childAst = $this->searchClassAst($child, $className);

                if ($childAst !== null) {
                    return $childAst;
                }
            }
        }

        return null;
    }

    /**
     * Search for all use statements inside an abstract syntax tree.
     *
     * @param \ast\Node|string     $ast           - Abstract syntax tree
     * @param array<int,\ast\Node> $useStatements - Collected use statements
     *
     * @return \ast\Node[]
     */
    private function searchUseStatementsInAst($ast, $useStatements = [])
    {
        if ($ast instanceof \ast\Node) {
            if ($ast->kind === \ast\AST_USE_ELEM) {
                $useStatements[] = $ast;
            }

            /** @var \ast\Node $child */
            foreach ($ast->children as $child) {
                /** @var array<int,\ast\Node> $useStatements */
                $useStatements = $this->searchUseStatementsInAst($child, $useStatements);
            }
        }

        /** @var array<int,\ast\Node> $useStatements */
        return $useStatements;
    }
}
