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
use OpenCodeModeling\JsonSchemaToPhp\Type\BooleanType;
use OpenCodeModeling\JsonSchemaToPhpAst\PropertyFactory;
use PhpParser\NodeVisitor;
use PhpParser\Parser;

/**
 * This file creates node visitors for a value object of type bool.
 *
 * The following code will be generated:
 *
 * private bool $boolean;
 *
 * public static function fromBool(bool $boolean): self
 * {
 *     return new self($boolean);
 * }
 *
 * private function __construct(bool $boolean)
 * {
 *     $this->boolean = $boolean;
 * }
 *
 * public function toBool(): bool
 * {
 *     return $this->boolean;
 * }
 *
 * public function equals($other): bool
 * {
 *     if(!$other instanceof self) {
 *         return false;
 *     }
 *
 *     return $this->boolean === $other->boolean;
 * }
 *
 * public function __toString(): string
 * {
 *     return $this->boolean ? 'TRUE' : 'FALSE';
 * }
 */
final class BooleanFactory
{
    private Parser $parser;
    private PropertyFactory $propertyFactory;

    public function __construct(Parser $parser, bool $typed)
    {
        $this->parser = $parser;
        $this->propertyFactory = new PropertyFactory($typed);
    }

    /**
     * @param BooleanType $typeDefinition
     * @return array<NodeVisitor>
     */
    public function nodeVisitors(BooleanType $typeDefinition): array
    {
        $name = $typeDefinition->name() ?: 'boolean';

        return $this->nodeVisitorsFromNative($name);
    }

    /**
     * @param string $name
     * @return array<NodeVisitor>
     */
    public function nodeVisitorsFromNative(string $name): array
    {
        $nodeVisitors = $this->propertyFactory->nodeVisitorFromNative($name, 'bool');
        $nodeVisitors[] = $this->methodFromBool($name);
        $nodeVisitors[] = $this->methodMagicConstruct($name);
        $nodeVisitors[] = $this->methodToBool($name);
        $nodeVisitors[] = $this->methodEquals($name);
        $nodeVisitors[] = $this->methodMagicToString($name);

        return $nodeVisitors;
    }

    public function methodFromBool(string $argumentName): NodeVisitor
    {
        $method = new MethodGenerator(
            'fromBool',
            [
                new ParameterGenerator($argumentName, 'bool'),
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
                new ParameterGenerator($argumentName, 'bool'),
            ],
            MethodGenerator::FLAG_PRIVATE,
            new BodyGenerator($this->parser, \sprintf('$this->%s = $%s;', $argumentName, $argumentName))
        );

        return new ClassMethod($method);
    }

    public function methodToBool(string $argumentName): NodeVisitor
    {
        $method = new MethodGenerator(
            'toBool',
            [],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, 'return $this->' . $argumentName . ';')
        );
        $method->setReturnType('bool');

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
            new BodyGenerator($this->parser, 'return $this->' . $argumentName . " ? 'TRUE' : 'FALSE';")
        );
        $method->setReturnType('string');

        return new ClassMethod($method);
    }
}
