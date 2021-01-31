<?php

/**
 * @see       https://github.com/open-code-modeling/json-schema-to-php-ast for the canonical source repository
 * @copyright https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/COPYRIGHT.md
 * @license   https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/LICENSE.md MIT License
 */

declare(strict_types=1);

namespace OpenCodeModeling\JsonSchemaToPhpAst;

use OpenCodeModeling\CodeAst\Builder\ClassBuilder;
use OpenCodeModeling\CodeAst\Builder\ClassPropertyBuilder;
use OpenCodeModeling\CodeAst\Builder\File;
use OpenCodeModeling\CodeAst\Builder\FileCollection;
use OpenCodeModeling\CodeAst\Code\ClassConstGenerator;
use OpenCodeModeling\CodeAst\FileCodeGenerator;
use OpenCodeModeling\CodeAst\Package\ClassInfo;
use OpenCodeModeling\CodeAst\Package\ClassInfoList;
use OpenCodeModeling\JsonSchemaToPhp\Type\ArrayType;
use OpenCodeModeling\JsonSchemaToPhp\Type\BooleanType;
use OpenCodeModeling\JsonSchemaToPhp\Type\IntegerType;
use OpenCodeModeling\JsonSchemaToPhp\Type\NumberType;
use OpenCodeModeling\JsonSchemaToPhp\Type\ObjectType;
use OpenCodeModeling\JsonSchemaToPhp\Type\ReferenceType;
use OpenCodeModeling\JsonSchemaToPhp\Type\ScalarType;
use OpenCodeModeling\JsonSchemaToPhp\Type\StringType;
use OpenCodeModeling\JsonSchemaToPhp\Type\TypeDefinition;
use OpenCodeModeling\JsonSchemaToPhp\Type\TypeSet;
use OpenCodeModeling\JsonSchemaToPhpAst\ValueObject\ArrayFactory;
use OpenCodeModeling\JsonSchemaToPhpAst\ValueObject\BooleanFactory;
use OpenCodeModeling\JsonSchemaToPhpAst\ValueObject\DateTimeFactory;
use OpenCodeModeling\JsonSchemaToPhpAst\ValueObject\EnumFactory;
use OpenCodeModeling\JsonSchemaToPhpAst\ValueObject\IntegerFactory;
use OpenCodeModeling\JsonSchemaToPhpAst\ValueObject\NumberFactory;
use OpenCodeModeling\JsonSchemaToPhpAst\ValueObject\StringFactory;
use OpenCodeModeling\JsonSchemaToPhpAst\ValueObject\UuidFactory;
use PhpParser\NodeVisitor;
use PhpParser\Parser;
use PhpParser\PrettyPrinterAbstract;

final class ValueObjectFactory
{
    private StringFactory $stringFactory;
    private IntegerFactory $integerFactory;
    private BooleanFactory $booleanFactory;
    private NumberFactory $numberFactory;
    private DateTimeFactory $dateTimeFactory;
    private EnumFactory $enumFactory;
    private UuidFactory $uuidFactory;
    private ArrayFactory $arrayFactory;

    private ClassInfoList $classInfoList;
    private FileCodeGenerator $fileCodeGenerator;

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

    /**
     * @var callable
     */
    private $constNameFilter;

    /**
     * @var callable
     */
    private $constValueFilter;

    /**
     * @var callable
     */
    private $isValueObject;

    /**
     * @var callable
     */
    private $currentFileAst;

    /**
     * @param ClassInfoList $classInfoList
     * @param Parser $parser
     * @param PrettyPrinterAbstract $printer
     * @param bool $typed
     * @param callable $classNameFilter Converts the name of the type to a proper class name
     * @param callable $propertyNameFilter Converts the name to a proper class property name
     * @param callable $methodNameFilter Converts the name to a proper class method name
     * @param callable $constNameFilter Converts the name to a proper class constant name
     * @param callable $constValueFilter Converts the name to a proper class constant value
     */
    public function __construct(
        ClassInfoList $classInfoList,
        Parser $parser,
        PrettyPrinterAbstract $printer,
        bool $typed,
        callable $classNameFilter,
        callable $propertyNameFilter,
        callable $methodNameFilter,
        callable $constNameFilter,
        callable $constValueFilter
    ) {
        $this->classInfoList = $classInfoList;
        $this->stringFactory = new StringFactory($parser, $typed, $propertyNameFilter);
        $this->integerFactory = new IntegerFactory($parser, $typed, $propertyNameFilter);
        $this->booleanFactory = new BooleanFactory($parser, $typed, $propertyNameFilter);
        $this->numberFactory = new NumberFactory($parser, $typed, $propertyNameFilter);
        $this->dateTimeFactory = new DateTimeFactory($parser, $typed, $propertyNameFilter);
        $this->enumFactory = new EnumFactory($parser, $typed, $propertyNameFilter, $methodNameFilter, $constNameFilter, $constValueFilter);
        $this->uuidFactory = new UuidFactory($parser, $typed, $propertyNameFilter);
        $this->arrayFactory = new ArrayFactory($parser, $typed, $classNameFilter, $propertyNameFilter);

        $this->classNameFilter = $classNameFilter;
        $this->propertyNameFilter = $propertyNameFilter;
        $this->methodNameFilter = $methodNameFilter;
        $this->constNameFilter = $constNameFilter;
        $this->constValueFilter = $constValueFilter;

        $this->fileCodeGenerator = new FileCodeGenerator($parser, $printer, $classInfoList);

        $this->isValueObject = static function (ClassBuilder $classBuilder): bool {
            return $classBuilder->hasMethod('fromItems')
            || $classBuilder->hasMethod('toString')
            || $classBuilder->hasMethod('toInt')
            || $classBuilder->hasMethod('toFloat')
            || $classBuilder->hasMethod('toBool');
        };

        $this->currentFileAst = static function (File $classBuilder, ClassInfo $classInfo) use ($parser): array {
            $path = $classInfo->getPath($classBuilder->getNamespace() . '\\' . $classBuilder->getName());
            $filename = $classInfo->getFilenameFromPathAndName($path, $classBuilder->getName());

            $code = '';

            if (\file_exists($filename) && \is_readable($filename)) {
                $code = \file_get_contents($filename);
            }

            $ast = $parser->parse($code);

            if (! $ast) {
                return [];
            }

            return $ast;
        };
    }

    /**
     * @param TypeDefinition $typeDefinition
     * @return array<NodeVisitor>
     */
    public function nodeVisitors(TypeDefinition $typeDefinition): array
    {
        switch (true) {
            case $typeDefinition instanceof StringType:
                if ($typeDefinition->enum() !== null) {
                    return $this->enumFactory->nodeVisitors($typeDefinition);
                }
                switch ($typeDefinition->format()) {
                    case TypeDefinition::FORMAT_DATETIME:
                        return $this->dateTimeFactory->nodeVisitors($typeDefinition);
                    case 'uuid':
                        return $this->uuidFactory->nodeVisitors($typeDefinition);
                    default:
                        return $this->stringFactory->nodeVisitors($typeDefinition);
                }
                // no break
            case $typeDefinition instanceof IntegerType:
                return $this->integerFactory->nodeVisitors($typeDefinition);
            case $typeDefinition instanceof BooleanType:
                return $this->booleanFactory->nodeVisitors($typeDefinition);
            case $typeDefinition instanceof NumberType:
                return $this->numberFactory->nodeVisitors($typeDefinition);
            case $typeDefinition instanceof ArrayType:
                return $this->arrayFactory->nodeVisitors($typeDefinition);
            default:
                throw new \RuntimeException(\sprintf('Type "%s" not supported', \get_class($typeDefinition)));
        }
    }

    public function classBuilder(TypeDefinition $typeDefinition): ClassBuilder
    {
        switch (true) {
            case $typeDefinition instanceof StringType:
                if ($typeDefinition->enum() !== null) {
                    return $this->enumFactory->classBuilder($typeDefinition);
                }
                switch ($typeDefinition->format()) {
                    case TypeDefinition::FORMAT_DATETIME:
                        return $this->dateTimeFactory->classBuilder($typeDefinition);
                    case 'uuid':
                        return $this->uuidFactory->classBuilder($typeDefinition);
                    default:
                        return $this->stringFactory->classBuilder($typeDefinition);
                }
            // no break
            case $typeDefinition instanceof IntegerType:
                return $this->integerFactory->classBuilder($typeDefinition);
            case $typeDefinition instanceof BooleanType:
                return $this->booleanFactory->classBuilder($typeDefinition);
            case $typeDefinition instanceof NumberType:
                return $this->numberFactory->classBuilder($typeDefinition);
            case $typeDefinition instanceof ArrayType:
                return $this->arrayFactory->classBuilder($typeDefinition);
            default:
                throw new \RuntimeException(\sprintf('Type "%s" not supported', \get_class($typeDefinition)));
        }
    }

    /**
     * @param ClassBuilder $classBuilder Main class
     * @param FileCollection $fileCollection Collection for other classes
     * @param TypeSet $typeSet
     * @param string $srcFolder Source folder for namespace imports
     * @param string|null $className Class name is used from $classBuilder if not set
     * @return void
     */
    public function generateClasses(
        ClassBuilder $classBuilder,
        FileCollection $fileCollection,
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
                                    $fileCollection,
                                    $itemTypeSet,
                                    $srcFolder,
                                    $itemPropertyName
                                );
                            }
                        // no break
                        case $propertyType instanceof ObjectType:
                            $this->generateClasses(
                                ClassBuilder::fromScratch($propertyClassName, $classNamespace)->setFinal(true),
                                $fileCollection,
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
                                    $fileCollection,
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
                            $fileCollection->add(
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
                $fileCollection->add($classBuilder);
                break;
            case $type instanceof ScalarType:
                $fileCollection->add(
                    $this->generateValueObject(($this->classNameFilter)($className), $classNamespace, $type)
                );
                break;
            case $type instanceof ArrayType:
                $arrayClassBuilder = $this->generateValueObject(($this->classNameFilter)($className), $classNamespace, $type);
                $this->addNamespaceImport($arrayClassBuilder, $type);
                $fileCollection->add($arrayClassBuilder);
                break;
            default:
                break;
        }
    }

    /**
     * Returns the generated code of provided file collection
     *
     * @param FileCollection $fileCollection
     * @return array<string, string> List of filename => code
     */
    public function generateFiles(FileCollection $fileCollection): array
    {
        return $this->fileCodeGenerator->generateFiles($fileCollection, $this->currentFileAst);
    }

    /**
     * Generation of getter methods for value object are skipped.
     *
     * @param FileCollection $fileCollection
     * @param bool $typed
     */
    public function addGetterMethodsForProperties(FileCollection $fileCollection, bool $typed): void
    {
        $this->fileCodeGenerator->addGetterMethodsForProperties(
            $fileCollection,
            $typed,
            $this->methodNameFilter,
            $this->isValueObject
        );
    }

    /**
     * Generation of constants for value object are skipped.
     *
     * @param FileCollection $fileCollection
     * @param int $visibility Visibility of the class constant
     */
    public function addClassConstantsForProperties(
        FileCollection $fileCollection,
        int $visibility = ClassConstGenerator::FLAG_PUBLIC
    ): void {
        $this->fileCodeGenerator->addClassConstantsForProperties(
            $fileCollection,
            $this->constNameFilter,
            $this->constValueFilter,
            $this->isValueObject,
            $visibility
        );
    }

    public function generateValueObject(string $className, string $classNamespace, TypeDefinition $definition): ClassBuilder
    {
        $classBuilder = $this->classBuilder($definition);
        $classBuilder->setName($className)
            ->setNamespace($classNamespace)
            ->setStrict(true)
            ->setFinal(true);

        return $classBuilder;
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

    private function determinePropertyType(TypeDefinition $typeDefinition, string $className): string
    {
        return ($typeDefinition->isRequired() === false || $typeDefinition->isNullable() === true)
            ? ('?' . $className)
            : $className;
    }
}
