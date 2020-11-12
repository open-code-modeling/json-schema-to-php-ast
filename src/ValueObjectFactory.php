<?php

/**
 * @see       https://github.com/open-code-modeling/json-schema-to-php-ast for the canonical source repository
 * @copyright https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/COPYRIGHT.md
 * @license   https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/LICENSE.md MIT License
 */

declare(strict_types=1);

namespace OpenCodeModeling\JsonSchemaToPhpAst;

use OpenCodeModeling\CodeAst\Builder\ClassBuilder;
use OpenCodeModeling\JsonSchemaToPhp\Type\BooleanType;
use OpenCodeModeling\JsonSchemaToPhp\Type\IntegerType;
use OpenCodeModeling\JsonSchemaToPhp\Type\NumberType;
use OpenCodeModeling\JsonSchemaToPhp\Type\StringType;
use OpenCodeModeling\JsonSchemaToPhp\Type\TypeDefinition;
use OpenCodeModeling\JsonSchemaToPhpAst\ValueObject\BooleanFactory;
use OpenCodeModeling\JsonSchemaToPhpAst\ValueObject\DateTimeFactory;
use OpenCodeModeling\JsonSchemaToPhpAst\ValueObject\IntegerFactory;
use OpenCodeModeling\JsonSchemaToPhpAst\ValueObject\NumberFactory;
use OpenCodeModeling\JsonSchemaToPhpAst\ValueObject\StringFactory;
use PhpParser\NodeVisitor;
use PhpParser\Parser;

final class ValueObjectFactory
{
    private StringFactory $stringFactory;
    private IntegerFactory $integerFactory;
    private BooleanFactory $booleanFactory;
    private NumberFactory $numberFactory;
    private DateTimeFactory $dateTimeFactory;

    public function __construct(Parser $parser, bool $typed)
    {
        $this->stringFactory = new StringFactory($parser, $typed);
        $this->integerFactory = new IntegerFactory($parser, $typed);
        $this->booleanFactory = new BooleanFactory($parser, $typed);
        $this->numberFactory = new NumberFactory($parser, $typed);
        $this->dateTimeFactory = new DateTimeFactory($parser, $typed);
    }

    /**
     * @param TypeDefinition $typeDefinition
     * @return array<NodeVisitor>
     */
    public function nodeVisitors(TypeDefinition $typeDefinition): array
    {
        switch (true) {
            case $typeDefinition instanceof StringType:
                switch ($typeDefinition->format()) {
                    case TypeDefinition::FORMAT_DATETIME:
                        return $this->dateTimeFactory->nodeVisitors($typeDefinition);
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
            default:
                // TODO throw exception
                return [];
        }
    }

    public function classBuilder(TypeDefinition $typeDefinition): ClassBuilder
    {
        switch (true) {
            case $typeDefinition instanceof StringType:
                switch ($typeDefinition->format()) {
                    case TypeDefinition::FORMAT_DATETIME:
                        return $this->dateTimeFactory->classBuilder($typeDefinition);
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
            default:
                throw new \RuntimeException(\sprintf('Type "%s" not supported', \get_class($typeDefinition)));
        }
    }
}
