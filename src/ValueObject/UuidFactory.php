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
use OpenCodeModeling\JsonSchemaToPhp\Type\StringType;
use OpenCodeModeling\JsonSchemaToPhpAst\PropertyFactory;
use PhpParser\NodeVisitor;
use PhpParser\Parser;

/**
 * This file creates node visitors for a value object of type string.
 *
 * The following code will be generated:
 *
 * private UuidInterface $uuid;
 *
 * public static function fromString(string $uuid): self
 * {
 *     return new self(Uuid::fromString($uuid));
 * }
 *
 * private function __construct(UuidInterface $uuid)
 * {
 *     $this->uuid = $uuid;
 * }
 *
 * public function toString(): string
 * {
 *     return $this->uuid;
 * }
 *
 * public function equals($other): bool
 * {
 *     if(!$other instanceof self) {
 *         return false;
 *     }
 *
 *     return $this->uuid === $other->uuid;
 * }
 *
 * public function __toString(): string
 * {
 *     return $this->uuid;
 * }
 */
final class UuidFactory
{
    private Parser $parser;
    private PropertyFactory $propertyFactory;
    private bool $typed;

    /**
     * @var callable
     */
    private $propertyNameFilter;

    public function __construct(Parser $parser, bool $typed, callable $propertyNameFilter)
    {
        $this->parser = $parser;
        $this->typed = $typed;
        $this->propertyNameFilter = $propertyNameFilter;
        $this->propertyFactory = new PropertyFactory($typed, $propertyNameFilter);
    }

    /**
     * @param StringType $typeDefinition
     * @return array<NodeVisitor>
     */
    public function nodeVisitors(StringType $typeDefinition): array
    {
        $name = $typeDefinition->name() ?: 'uuid';

        return $this->nodeVisitorsFromNative($name);
    }

    public function classBuilder(StringType $typeDefinition): ClassBuilder
    {
        $name = $typeDefinition->name() ?: 'uuid';

        return $this->classBuilderFromNative($name);
    }

    /**
     * @param string $name
     * @return array<NodeVisitor>
     */
    public function nodeVisitorsFromNative(string $name): array
    {
        $nodeVisitors = $this->propertyFactory->nodeVisitorFromNative($name, 'UuidInterface');
        $nodeVisitors[] = new ClassMethod($this->methodFromString($name));
        $nodeVisitors[] = new ClassMethod($this->methodMagicConstruct($name));
        $nodeVisitors[] = new ClassMethod($this->methodToString($name));
        $nodeVisitors[] = new ClassMethod($this->methodEquals($name));
        $nodeVisitors[] = new ClassMethod($this->methodMagicToString($name));

        return $nodeVisitors;
    }

    public function classBuilderFromNative(string $name): ClassBuilder
    {
        return ClassBuilder::fromNodes(
            $this->propertyFactory->propertyGenerator($name, 'UuidInterface')->generate(),
            $this->methodFromString($name)->generate(),
            $this->methodMagicConstruct($name)->generate(),
            $this->methodToString($name)->generate(),
            $this->methodEquals($name)->generate(),
            $this->methodMagicToString($name)->generate(),
        )->setTyped($this->typed)
            ->addNamespaceImport(
                'Ramsey\Uuid\Uuid',
                'Ramsey\Uuid\UuidInterface',
            );
    }

    public function methodFromString(string $argumentName): MethodGenerator
    {
        $argumentName = ($this->propertyNameFilter)($argumentName);

        $method = new MethodGenerator(
            'fromString',
            [
                new ParameterGenerator($argumentName, 'string'),
            ],
            MethodGenerator::FLAG_STATIC | MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, 'return new self(Uuid::fromString($' . $argumentName . '));')
        );
        $method->setTyped($this->typed);
        $method->setReturnType('self');

        return $method;
    }

    public function methodMagicConstruct(string $argumentName): MethodGenerator
    {
        $argumentName = ($this->propertyNameFilter)($argumentName);

        $method = new MethodGenerator(
            '__construct',
            [
                new ParameterGenerator($argumentName, 'UuidInterface'),
            ],
            MethodGenerator::FLAG_PRIVATE,
            new BodyGenerator($this->parser, \sprintf('$this->%s = $%s;', $argumentName, $argumentName))
        );
        $method->setTyped($this->typed);

        return $method;
    }

    public function methodToString(string $propertyName): MethodGenerator
    {
        $propertyName = ($this->propertyNameFilter)($propertyName);

        $method = new MethodGenerator(
            'toString',
            [],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, 'return $this->' . $propertyName . '->toString();')
        );
        $method->setTyped($this->typed);
        $method->setReturnType('string');

        return $method;
    }

    public function methodEquals(string $propertyName, string $argumentName = 'other'): MethodGenerator
    {
        $propertyName = ($this->propertyNameFilter)($propertyName);
        $argumentName = ($this->propertyNameFilter)($argumentName);

        $body = <<<PHP
    if(!\$$argumentName instanceof self) {
       return false;
    }

    return \$this->$propertyName === \$$argumentName->$propertyName;
PHP;

        $method = new MethodGenerator(
            'equals',
            [
                (new ParameterGenerator($argumentName))->setTypeDocBlockHint('mixed'),
            ],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, $body)
        );
        $method->setDocBlockComment('');
        $method->setTyped($this->typed);
        $method->setReturnType('bool');

        return $method;
    }

    public function methodMagicToString(string $propertyName): MethodGenerator
    {
        $propertyName = ($this->propertyNameFilter)($propertyName);

        $method = new MethodGenerator(
            '__toString',
            [],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, 'return $this->' . $propertyName . '->toString();')
        );
        $method->setTyped($this->typed);
        $method->setReturnType('string');

        return $method;
    }
}
