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
use OpenCodeModeling\JsonSchemaToPhp\Type\StringType;
use OpenCodeModeling\JsonSchemaToPhpAst\PropertyFactory;
use PhpParser\NodeVisitor;
use PhpParser\Parser;

/**
 * This file creates node visitors for a value object of type string.
 *
 * The following code will be generated:
 *
 * private string $text;
 *
 * public static function fromString(string $text): self
 * {
 *     return new self($text);
 * }
 *
 * private function __construct(string $text)
 * {
 *     $this->text = $text;
 * }
 *
 * public function toString(): string
 * {
 *     return $this->text;
 * }
 *
 * public function equals($other): bool
 * {
 *     if(!$other instanceof self) {
 *         return false;
 *     }
 *
 *     return $this->text === $other->text;
 * }
 *
 * public function __toString(): string
 * {
 *     return $this->text;
 * }
 */
final class StringFactory
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
     * @param StringType $typeDefinition
     * @return array<NodeVisitor>
     */
    public function nodeVisitors(StringType $typeDefinition): array
    {
        $name = $typeDefinition->name() ?: 'text';

        return $this->nodeVisitorsFromNative($name);
    }

    /**
     * @param string $name
     * @return array<NodeVisitor>
     */
    public function nodeVisitorsFromNative(string $name): array
    {
        $nodeVisitors = $this->propertyFactory->nodeVisitorFromNative($name, 'string');
        $nodeVisitors[] = $this->methodFromString($name);
        $nodeVisitors[] = $this->methodMagicConstruct($name);
        $nodeVisitors[] = $this->methodToString($name);
        $nodeVisitors[] = $this->methodEquals($name);
        $nodeVisitors[] = $this->methodMagicToString($name);

        return $nodeVisitors;
    }

    public function methodFromString(string $argumentName): NodeVisitor
    {
        $method = new MethodGenerator(
            'fromString',
            [
                new ParameterGenerator($argumentName, 'string'),
            ],
            MethodGenerator::FLAG_STATIC | MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, 'return new self($' . $argumentName . ');')
        );
        $method->setTyped($this->typed);
        $method->setReturnType('self');

        return new ClassMethod($method);
    }

    public function methodMagicConstruct(string $argumentName): NodeVisitor
    {
        $method = new MethodGenerator(
            '__construct',
            [
                new ParameterGenerator($argumentName, 'string'),
            ],
            MethodGenerator::FLAG_PRIVATE,
            new BodyGenerator($this->parser, \sprintf('$this->%s = $%s;', $argumentName, $argumentName))
        );
        $method->setTyped($this->typed);

        return new ClassMethod($method);
    }

    public function methodToString(string $argumentName): NodeVisitor
    {
        $method = new MethodGenerator(
            'toString',
            [],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, 'return $this->' . $argumentName . ';')
        );
        $method->setTyped($this->typed);
        $method->setReturnType('string');

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
        $method->setTyped($this->typed);
        $method->setReturnType('bool');

        return new ClassMethod($method);
    }

    public function methodMagicToString(string $argumentName): NodeVisitor
    {
        $method = new MethodGenerator(
            '__toString',
            [],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, 'return $this->' . $argumentName . ';')
        );
        $method->setTyped($this->typed);
        $method->setReturnType('string');

        return new ClassMethod($method);
    }
}
