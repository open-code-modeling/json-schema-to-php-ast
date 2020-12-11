<?php

/**
 * @see       https://github.com/open-code-modeling/json-schema-to-php-ast for the canonical source repository
 * @copyright https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/COPYRIGHT.md
 * @license   https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/LICENSE.md MIT License
 */

declare(strict_types=1);

namespace OpenCodeModeling\JsonSchemaToPhpAst;

use OpenCodeModeling\CodeAst\Builder\File;
use OpenCodeModeling\CodeAst\Builder\FileCollection;
use OpenCodeModeling\CodeAst\Package\ClassInfo;
use OpenCodeModeling\CodeAst\Package\ClassInfoList;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\PrettyPrinterAbstract;

final class FileGenerator
{
    private ClassInfoList $classInfoList;

    public function __construct(ClassInfoList $classInfoList)
    {
        $this->classInfoList = $classInfoList;
    }

    /**
     * @param FileCollection $fileCollection
     * @param Parser $parser
     * @param PrettyPrinterAbstract $printer
     * @param callable|null $currentFileAst Callable to return current file AST, if null, file will be overwritten
     * @return array<string, string> List of filename => code
     */
    public function generateFiles(
        FileCollection $fileCollection,
        Parser $parser,
        PrettyPrinterAbstract $printer,
        callable $currentFileAst = null
    ): array {
        $files = [];

        if ($currentFileAst === null) {
            $currentFileAst = static function (File $file, ClassInfo $classInfo) {
                return [];
            };
        }

        $previousNamespace = '__invalid//namespace__';

        foreach ($fileCollection as $classBuilder) {
            if ($previousNamespace !== $classBuilder->getNamespace()) {
                $previousNamespace = $classBuilder->getNamespace();
                $classInfo = $this->classInfoList->classInfoForNamespace($previousNamespace);
                $path = $classInfo->getPath($classBuilder->getNamespace() . '\\' . $classBuilder->getName());
            }
            // @phpstan-ignore-next-line
            $filename = $classInfo->getFilenameFromPathAndName($path, $classBuilder->getName());

            $nodeTraverser = new NodeTraverser();
            $classBuilder->injectVisitors($nodeTraverser, $parser);

            $files[$filename] = $printer->prettyPrintFile(
                // @phpstan-ignore-next-line
                $nodeTraverser->traverse($currentFileAst($classBuilder, $classInfo))
            );
        }

        return $files;
    }
}
