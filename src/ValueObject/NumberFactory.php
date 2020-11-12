<?php

/**
 * @see       https://github.com/open-code-modeling/json-schema-to-php-ast for the canonical source repository
 * @copyright https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/COPYRIGHT.md
 * @license   https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/LICENSE.md MIT License
 */

declare(strict_types=1);

namespace OpenCodeModeling\JsonSchemaToPhpAst\ValueObject;

use OpenCodeModeling\CodeAst\Builder\ClassBuilder;
use OpenCodeModeling\CodeAst\Code\BodyGenerator;
use OpenCodeModeling\CodeAst\Code\MethodGenerator;
use OpenCodeModeling\CodeAst\Code\ParameterGenerator;
use OpenCodeModeling\CodeAst\NodeVisitor\ClassMethod;
use OpenCodeModeling\JsonSchemaToPhp\Type\NumberType;
use OpenCodeModeling\JsonSchemaToPhpAst\PropertyFactory;
use PhpParser\NodeVisitor;
use PhpParser\Parser;

/**
 * This file creates node visitors for a value object of type float.
 *
 * The following code will be generated:
 *
 * private float $number;
 *
 * public static function fromFloat(float $number): self
 * {
 *     return new self($number);
 * }
 *
 * private function __construct(float $number)
 * {
 *     $this->number = $number;
 * }
 *
 * public function toFloat(): float
 * {
 *     return $this->number;
 * }
 *
 * public function equals($other): bool
 * {
 *     if(!$other instanceof self) {
 *         return false;
 *     }
 *
 *     return $this->number === $other->number;
 * }
 *
 * public function __toString(): string
 * {
 *     return (string)$this->number;
 * }
 */
final class NumberFactory
{
    private Parser $parser;
    private PropertyFactory $propertyFactory;
    private bool $typed;

    public function __construct(Parser $parser, bool $typed)
    {
        $this->parser = $parser;
        $this->typed = $typed;
        $this->propertyFactory = new PropertyFactory($typed);
    }

    /**
     * @param NumberType $typeDefinition
     * @return array<NodeVisitor>
     */
    public function nodeVisitors(NumberType $typeDefinition): array
    {
        $name = $typeDefinition->name() ?: 'number';

        return $this->nodeVisitorsFromNative($name);
    }

    public function classBuilder(NumberType $typeDefinition): ClassBuilder
    {
        $name = $typeDefinition->name() ?: 'number';

        return $this->classBuilderFromNative($name)->setTyped($this->typed);
    }

    /**
     * @param string $name
     * @return array<NodeVisitor>
     */
    public function nodeVisitorsFromNative(string $name): array
    {
        $nodeVisitors = $this->propertyFactory->nodeVisitorFromNative($name, 'float');
        $nodeVisitors[] = new ClassMethod($this->methodFromFloat($name));
        $nodeVisitors[] = new ClassMethod($this->methodMagicConstruct($name));
        $nodeVisitors[] = new ClassMethod($this->methodToFloat($name));
        $nodeVisitors[] = new ClassMethod($this->methodEquals($name));
        $nodeVisitors[] = new ClassMethod($this->methodMagicToString($name));

        return $nodeVisitors;
    }

    public function classBuilderFromNative(string $name): ClassBuilder
    {
        return ClassBuilder::fromNodes(
            $this->propertyFactory->propertyGenerator($name, 'float')->generate(),
            $this->methodFromFloat($name)->generate(),
            $this->methodMagicConstruct($name)->generate(),
            $this->methodToFloat($name)->generate(),
            $this->methodEquals($name)->generate(),
            $this->methodMagicToString($name)->generate(),
        )->setTyped($this->typed);
    }

    public function methodFromFloat(string $argumentName): MethodGenerator
    {
        $method = new MethodGenerator(
            'fromFloat',
            [
                new ParameterGenerator($argumentName, 'float'),
            ],
            MethodGenerator::FLAG_STATIC | MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, 'return new self($' . $argumentName . ');')
        );
        $method->setTyped($this->typed);
        $method->setReturnType('self');

        return $method;
    }

    public function methodMagicConstruct(string $argumentName): MethodGenerator
    {
        $method = new MethodGenerator(
            '__construct',
            [
                new ParameterGenerator($argumentName, 'float'),
            ],
            MethodGenerator::FLAG_PRIVATE,
            new BodyGenerator($this->parser, \sprintf('$this->%s = $%s;', $argumentName, $argumentName))
        );
        $method->setTyped($this->typed);

        return $method;
    }

    public function methodToFloat(string $argumentName): MethodGenerator
    {
        $method = new MethodGenerator(
            'toFloat',
            [],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, 'return $this->' . $argumentName . ';')
        );
        $method->setTyped($this->typed);
        $method->setReturnType('float');

        return $method;
    }

    public function methodEquals(string $propertyName, string $argumentName = 'other'): MethodGenerator
    {
        $body = <<<PHP
    if(!\$$argumentName instanceof self) {
       return false;
    }

    return \$this->$propertyName === \$$argumentName->$propertyName;
PHP;

        $method = new MethodGenerator(
            'equals',
            [
                new ParameterGenerator($argumentName),
            ],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, $body)
        );
        $method->setTyped($this->typed);
        $method->setReturnType('bool');

        return $method;
    }

    public function methodMagicToString(string $argumentName): MethodGenerator
    {
        $method = new MethodGenerator(
            '__toString',
            [],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, 'return (string)$this->' . $argumentName . ';')
        );
        $method->setTyped($this->typed);
        $method->setReturnType('string');

        return $method;
    }
}
