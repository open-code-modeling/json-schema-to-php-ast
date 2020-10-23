<?php

/**
 * @see       https://github.com/open-code-modeling/json-schema-to-php-ast for the canonical source repository
 * @copyright https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/COPYRIGHT.md
 * @license   https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/LICENSE.md MIT License
 */

declare(strict_types=1);

namespace OpenCodeModeling\JsonSchemaToPhpAst\ValueObject;

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

    public function __construct(Parser $parser, bool $typed)
    {
        $this->parser = $parser;
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

    /**
     * @param string $name
     * @return array<NodeVisitor>
     */
    public function nodeVisitorsFromNative(string $name): array
    {
        $nodeVisitors = $this->propertyFactory->nodeVisitorFromNative($name, 'float');
        $nodeVisitors[] = $this->methodFromFloat($name);
        $nodeVisitors[] = $this->methodMagicConstruct($name);
        $nodeVisitors[] = $this->methodToFloat($name);
        $nodeVisitors[] = $this->methodEquals($name);
        $nodeVisitors[] = $this->methodMagicToString($name);

        return $nodeVisitors;
    }

    public function methodFromFloat(string $argumentName): NodeVisitor
    {
        $method = new MethodGenerator(
            'fromFloat',
            [
                new ParameterGenerator($argumentName, 'float'),
            ],
            MethodGenerator::FLAG_STATIC | MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, 'return new self($' . $argumentName . ');')
        );
        $method->setReturnType('self');

        return new ClassMethod($method);
    }

    public function methodMagicConstruct(string $argumentName): NodeVisitor
    {
        $method = new MethodGenerator(
            '__construct',
            [
                new ParameterGenerator($argumentName, 'float'),
            ],
            MethodGenerator::FLAG_PRIVATE,
            new BodyGenerator($this->parser, \sprintf('$this->%s = $%s;', $argumentName, $argumentName))
        );

        return new ClassMethod($method);
    }

    public function methodToFloat(string $argumentName): NodeVisitor
    {
        $method = new MethodGenerator(
            'toFloat',
            [],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, 'return $this->' . $argumentName . ';')
        );
        $method->setReturnType('float');

        return new ClassMethod($method);
    }

    public function methodEquals(string $propertyName, string $argumentName = 'other'): NodeVisitor
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
        $method->setReturnType('bool');

        return new ClassMethod($method);
    }

    public function methodMagicToString(string $argumentName): NodeVisitor
    {
        $method = new MethodGenerator(
            '__toString',
            [],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, 'return (string)$this->' . $argumentName . ';')
        );
        $method->setReturnType('string');

        return new ClassMethod($method);
    }
}
