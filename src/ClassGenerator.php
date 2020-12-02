<?php

/**
 * @see       https://github.com/open-code-modeling/json-schema-to-php-ast for the canonical source repository
 * @copyright https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/COPYRIGHT.md
 * @license   https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/LICENSE.md MIT License
 */

declare(strict_types=1);

namespace OpenCodeModeling\JsonSchemaToPhpAst;

use OpenCodeModeling\CodeAst\Builder\ClassBuilder;
use OpenCodeModeling\CodeAst\Builder\ClassBuilderCollection;
use OpenCodeModeling\CodeAst\Builder\ClassPropertyBuilder;
use OpenCodeModeling\CodeAst\Package\ClassInfoList;
use OpenCodeModeling\JsonSchemaToPhp\Type\ArrayType;
use OpenCodeModeling\JsonSchemaToPhp\Type\ObjectType;
use OpenCodeModeling\JsonSchemaToPhp\Type\ReferenceType;
use OpenCodeModeling\JsonSchemaToPhp\Type\ScalarType;
use OpenCodeModeling\JsonSchemaToPhp\Type\TypeDefinition;
use OpenCodeModeling\JsonSchemaToPhp\Type\TypeSet;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\PrettyPrinterAbstract;

final class ClassGenerator
{
    private ClassInfoList $classInfoList;
    private ValueObjectFactory $valueObjectFactory;

    /**
     * @var callable
     */
    private $classNameFilter;

    /**
     * @var callable
     */
    private $propertyNameFilter;

    /**
     * @var callable
     */
    private $methodNameFilter;

    public function __construct(
        ClassInfoList $classInfoList,
        ValueObjectFactory $valueObjectFactory,
        callable $classNameFilter,
        callable $propertyNameFilter,
        callable $methodNameFilter
    ) {
        $this->classInfoList = $classInfoList;
        $this->valueObjectFactory = $valueObjectFactory;
        $this->classNameFilter = $classNameFilter;
        $this->propertyNameFilter = $propertyNameFilter;
        $this->methodNameFilter = $methodNameFilter;
    }

    /**
     * @param ClassBuilder $classBuilder Main class
     * @param ClassBuilderCollection $classBuilderCollection Collection for other classes
     * @param TypeSet $typeSet
     * @param string $srcFolder Source folder for namespace imports
     * @param string|null $className Class name of other classes
     * @return void
     */
    public function generateClasses(
        ClassBuilder $classBuilder,
        ClassBuilderCollection $classBuilderCollection,
        TypeSet $typeSet,
        string $srcFolder,
        string $className = null
    ): void {
        $type = $typeSet->first();

        $classInfo = $this->classInfoList->classInfoForPath($srcFolder);
        $classNamespace = $classInfo->getClassNamespaceFromPath($srcFolder);

        if ($type instanceof ReferenceType
            && $refType = $type->resolvedType()
        ) {
            $type = $refType->first();
        }

        switch (true) {
            case $type instanceof ObjectType:
                /** @var TypeSet $propertyTypeSet */
                foreach ($type->properties() as $propertyName => $propertyTypeSet) {
                    $propertyClassName = ($this->classNameFilter)($propertyName);
                    $propertyPropertyName = ($this->propertyNameFilter)($propertyName);

                    $propertyType = $propertyTypeSet->first();
                    switch (true) {
                        case $propertyType instanceof ArrayType:
                            foreach ($propertyType->items() as $itemTypeSet) {
                                $itemType = $itemTypeSet->first();

                                if (null === $itemType) {
                                    continue;
                                }
                                $itemClassName = ($this->classNameFilter)($itemType->name());
                                $itemPropertyName = ($this->propertyNameFilter)($itemType->name());

                                $this->generateClasses(
                                    ClassBuilder::fromScratch($itemClassName, $classNamespace)->setFinal(true),
                                    $classBuilderCollection,
                                    $itemTypeSet,
                                    $srcFolder,
                                    $itemPropertyName
                                );
                            }
                            // no break
                        case $propertyType instanceof ObjectType:
                            $this->generateClasses(
                                ClassBuilder::fromScratch($propertyClassName, $classNamespace)->setFinal(true),
                                $classBuilderCollection,
                                $propertyTypeSet,
                                $srcFolder,
                                $propertyClassName
                            );
                            $classBuilder->addNamespaceImport($classNamespace . '\\' . $propertyClassName);
                            $classBuilder->addProperty(ClassPropertyBuilder::fromScratch($propertyPropertyName, $propertyClassName));
                            break;
                        case $propertyType instanceof ReferenceType:
                            if ($propertyRefType = $propertyType->resolvedType()) {
                                $this->generateClasses(
                                    ClassBuilder::fromScratch($propertyClassName, $classNamespace)->setFinal(true),
                                    $classBuilderCollection,
                                    $propertyRefType,
                                    $srcFolder,
                                    $propertyType->name()
                                );
                                $propertyClassName = ($this->classNameFilter)($propertyType->name());
                                $classBuilder->addNamespaceImport($classNamespace . '\\' . $propertyClassName);
                            }
                            $classBuilder->addProperty(
                                ClassPropertyBuilder::fromScratch($propertyPropertyName, $propertyClassName)
                            );
                            break;
                        case $propertyType instanceof ScalarType:
                            $classBuilderCollection->add(
                                $this->generateValueObject($propertyClassName, $classNamespace, $propertyType)
                            );
                            $classBuilder->addNamespaceImport($classNamespace . '\\' . $propertyClassName);
                            $classBuilder->addProperty(
                                ClassPropertyBuilder::fromScratch(
                                    $propertyPropertyName,
                                    $propertyClassName
                                )
                            );
                            break;
                        default:
                            break;
                    }
                }
                $classBuilderCollection->add($classBuilder);
                break;
            case $type instanceof ScalarType:
                $classBuilderCollection->add(
                    $this->generateValueObject(($this->classNameFilter)($className), $classNamespace, $type)
                );
                break;
            case $type instanceof ArrayType:
                $arrayClassBuilder = $this->generateValueObject(($this->classNameFilter)($className), $classNamespace, $type);
                $this->addNamespaceImport($arrayClassBuilder, $type);
                $classBuilderCollection->add($arrayClassBuilder);
                break;
            default:
                break;
        }
    }

    public function generateValueObject(string $className, string $classNamespace, TypeDefinition $definition): ClassBuilder
    {
        $classBuilder = $this->valueObjectFactory->classBuilder($definition);
        $classBuilder->setName($className)
            ->setNamespace($classNamespace)
            ->setStrict(true)
            ->setFinal(true);

        return $classBuilder;
    }

    /**
     * @param ClassBuilderCollection $classBuilderCollection
     * @param Parser $parser
     * @param PrettyPrinterAbstract $printer
     * @return array<string, string> List of filename => code
     */
    public function generateFiles(
        ClassBuilderCollection $classBuilderCollection,
        Parser $parser,
        PrettyPrinterAbstract $printer
    ): array {
        $files = [];

        $previousNamespace = '__invalid//namespace__';

        foreach ($classBuilderCollection as $classBuilder) {
            if ($previousNamespace !== $classBuilder->getNamespace()) {
                $previousNamespace = $classBuilder->getNamespace();
                $classInfo = $this->classInfoList->classInfoForNamespace($previousNamespace);
                $path = $classInfo->getPath($classBuilder->getNamespace() . '\\' . $classBuilder->getName());
            }
            $filename = $classInfo->getFilenameFromPathAndName($path, $classBuilder->getName());

            $nodeTraverser = new NodeTraverser();
            $classBuilder->injectVisitors($nodeTraverser, $parser);

            $files[$filename] = $printer->prettyPrintFile($nodeTraverser->traverse([]));
        }

        return $files;
    }

    private function addNamespaceImport(ClassBuilder $classBuilder, TypeDefinition $typeDefinition): void
    {
        switch (true) {
            case $typeDefinition instanceof ArrayType:
                foreach ($typeDefinition->items() as $itemTypeSet) {
                    $itemType = $itemTypeSet->first();

                    if (null === $itemType) {
                        continue;
                    }

                    if ($itemType instanceof ReferenceType
                        && $refType = $itemType->resolvedType()
                    ) {
                        $itemType = $refType->first();
                    }
                    $itemClassName = ($this->classNameFilter)($itemType->name());
                    $classBuilder->addNamespaceImport($classBuilder->getNamespace() . '\\' . $itemClassName);
                }
                break;
            default:
                break;
        }
    }
}
