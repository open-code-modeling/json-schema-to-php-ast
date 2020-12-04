<?php

/**
 * @see       https://github.com/open-code-modeling/json-schema-to-php-ast for the canonical source repository
 * @copyright https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/COPYRIGHT.md
 * @license   https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/LICENSE.md MIT License
 */

declare(strict_types=1);

namespace OpenCodeModeling\JsonSchemaToPhpAst;

use OpenCodeModeling\CodeAst\Builder\ClassBuilder;
use OpenCodeModeling\JsonSchemaToPhp\Type\ArrayType;
use OpenCodeModeling\JsonSchemaToPhp\Type\BooleanType;
use OpenCodeModeling\JsonSchemaToPhp\Type\IntegerType;
use OpenCodeModeling\JsonSchemaToPhp\Type\NumberType;
use OpenCodeModeling\JsonSchemaToPhp\Type\StringType;
use OpenCodeModeling\JsonSchemaToPhp\Type\TypeDefinition;
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

    /**
     * @param Parser $parser
     * @param bool $typed
     * @param callable $classNameFilter Converts the name of the type to a proper class name
     * @param callable $propertyNameFilter Converts the name to a proper class property name
     * @param callable $methodNameFilter Converts the name to a proper class method name
     * @param callable $constNameFilter Converts the name to a proper class constant name
     * @param callable $constValueFilter Converts the name to a proper class constant value
     */
    public function __construct(
        Parser $parser,
        bool $typed,
        callable $classNameFilter,
        callable $propertyNameFilter,
        callable $methodNameFilter,
        callable $constNameFilter,
        callable $constValueFilter
    ) {
        $this->stringFactory = new StringFactory($parser, $typed, $propertyNameFilter);
        $this->integerFactory = new IntegerFactory($parser, $typed, $propertyNameFilter);
        $this->booleanFactory = new BooleanFactory($parser, $typed, $propertyNameFilter);
        $this->numberFactory = new NumberFactory($parser, $typed, $propertyNameFilter);
        $this->dateTimeFactory = new DateTimeFactory($parser, $typed, $propertyNameFilter);
        $this->enumFactory = new EnumFactory($parser, $typed, $propertyNameFilter, $methodNameFilter, $constNameFilter, $constValueFilter);
        $this->uuidFactory = new UuidFactory($parser, $typed, $propertyNameFilter);
        $this->arrayFactory = new ArrayFactory($parser, $typed, $classNameFilter, $propertyNameFilter);
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
}
