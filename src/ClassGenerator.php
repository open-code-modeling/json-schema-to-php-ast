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
use OpenCodeModeling\CodeAst\Builder\ClassConstBuilder;
use OpenCodeModeling\CodeAst\Builder\ClassMethodBuilder;
use OpenCodeModeling\CodeAst\Builder\ClassPropertyBuilder;
use OpenCodeModeling\CodeAst\Code\ClassConstGenerator;
use OpenCodeModeling\CodeAst\Package\ClassInfo;
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

    public function __construct(
        ClassInfoList $classInfoList,
        ValueObjectFactory $valueObjectFactory,
        callable $classNameFilter,
        callable $propertyNameFilter
    ) {
        $this->classInfoList = $classInfoList;
        $this->valueObjectFactory = $valueObjectFactory;
        $this->classNameFilter = $classNameFilter;
        $this->propertyNameFilter = $propertyNameFilter;
    }

    /**
     * @param ClassBuilder $classBuilder Main class
     * @param ClassBuilderCollection $classBuilderCollection Collection for other classes
     * @param TypeSet $typeSet
     * @param string $srcFolder Source folder for namespace imports
     * @param string|null $className Class name is used from $classBuilder if not set
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
        $className = $className ?: $classBuilder->getName();

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
                            $classBuilder->addProperty(
                                ClassPropertyBuilder::fromScratch(
                                    $propertyPropertyName,
                                    $this->determinePropertyType($propertyType, $propertyClassName)
                                )
                            );
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
                            }
                            $classBuilder->addProperty(
                                ClassPropertyBuilder::fromScratch(
                                    $propertyPropertyName,
                                    $this->determinePropertyType($propertyType, $propertyClassName)
                                )
                            );
                            $classBuilder->addNamespaceImport($classNamespace . '\\' . $propertyClassName);
                            break;
                        case $propertyType instanceof ScalarType:
                            $classBuilderCollection->add(
                                $this->generateValueObject($propertyClassName, $classNamespace, $propertyType)
                            );
                            $classBuilder->addNamespaceImport($classNamespace . '\\' . $propertyClassName);
                            $classBuilder->addProperty(
                                ClassPropertyBuilder::fromScratch(
                                    $propertyPropertyName,
                                    $this->determinePropertyType($propertyType, $propertyClassName)
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

    /**
     * Generation of getter methods for value object are skipped.
     *
     * @param ClassBuilderCollection $classBuilderCollection
     * @param bool $typed
     * @param callable $methodNameFilter Filter the property name to your desired method name e.g. with get prefix
     */
    public function addGetterMethods(
        ClassBuilderCollection $classBuilderCollection,
        bool $typed,
        callable $methodNameFilter
    ): void {
        foreach ($classBuilderCollection as $classBuilder) {
            foreach ($classBuilder->getProperties() as $classPropertyBuilder) {
                $methodName = ($methodNameFilter)($classPropertyBuilder->getName());

                if ($this->isValueObject($classBuilder)
                    || $classBuilder->hasMethod($methodName)) {
                    continue 2;
                }
                $classBuilder->addMethod(
                    ClassMethodBuilder::fromScratch($methodName, $typed)
                        ->setReturnType($classPropertyBuilder->getType())
                        ->setReturnTypeDocBlockHint($classPropertyBuilder->getTypeDocBlockHint())
                        ->setBody('return $this->' . $classPropertyBuilder->getName() . ';')
                );
            }
        }
    }

    /**
     * Generation of constants for value object are skipped.
     *
     * @param ClassBuilderCollection $classBuilderCollection
     * @param callable $constantNameFilter Converts the name to a proper class constant name
     * @param callable $constantValueFilter Converts the name to a proper class constant value e.g. snake_case or camelCase
     * @param int $visibility Visibility of the class constant
     */
    public function addClassConstantsForProperties(
        ClassBuilderCollection $classBuilderCollection,
        callable $constantNameFilter,
        callable $constantValueFilter,
        int $visibility = ClassConstGenerator::FLAG_PUBLIC
    ): void {
        foreach ($classBuilderCollection as $classBuilder) {
            foreach ($classBuilder->getProperties() as $classPropertyBuilder) {
                $constantName = ($constantNameFilter)($classPropertyBuilder->getName());

                if ($this->isValueObject($classBuilder)
                    || $classBuilder->hasConstant($constantName)) {
                    continue 2;
                }
                $classBuilder->addConstant(
                    ClassConstBuilder::fromScratch(
                        $constantName,
                        ($constantValueFilter)($classPropertyBuilder->getName()),
                        $visibility
                    )
                );
            }
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
     * @param callable|null $currentFileAst Callable to return current file AST, if null, file will be overwritten
     * @return array<string, string> List of filename => code
     */
    public function generateFiles(
        ClassBuilderCollection $classBuilderCollection,
        Parser $parser,
        PrettyPrinterAbstract $printer,
        callable $currentFileAst = null
    ): array {
        $files = [];

        if ($currentFileAst === null) {
            $currentFileAst = static function (ClassBuilder $classBuilder, ClassInfo $classInfo) {
                return [];
            };
        }

        $previousNamespace = '__invalid//namespace__';

        foreach ($classBuilderCollection as $classBuilder) {
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

    private function isValueObject(ClassBuilder $classBuilder): bool
    {
        return $classBuilder->hasMethod('fromItems')
            || $classBuilder->hasMethod('toString')
            || $classBuilder->hasMethod('toInt')
            || $classBuilder->hasMethod('toFloat')
            || $classBuilder->hasMethod('toBool');
    }

    private function determinePropertyType(TypeDefinition $typeDefinition, string $className): string
    {
        return ($typeDefinition->isRequired() === false || $typeDefinition->isNullable() === true)
            ? ('?' . $className)
            : $className;
    }
}
