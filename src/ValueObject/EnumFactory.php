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
use OpenCodeModeling\CodeAst\Code\ClassConstGenerator;
use OpenCodeModeling\CodeAst\Code\IdentifierGenerator;
use OpenCodeModeling\CodeAst\Code\MethodGenerator;
use OpenCodeModeling\CodeAst\Code\ParameterGenerator;
use OpenCodeModeling\CodeAst\NodeVisitor\ClassConstant;
use OpenCodeModeling\CodeAst\NodeVisitor\ClassMethod;
use OpenCodeModeling\JsonSchemaToPhp\Type\StringType;
use OpenCodeModeling\JsonSchemaToPhpAst\PropertyFactory;
use PhpParser\Node;
use PhpParser\NodeVisitor;
use PhpParser\Parser;

/**
 * This file creates node visitors for a value object of type string.
 *
 * The following code will be generated:
 *
 * private const ACTIVE = 'active';
 * private const INACTIVE = 'INACTIVE';
 *
 * private string $status;
 *
 * public const CHOICES = [
 *     self::ACTIVE,
 *     self::INACTIVE,
 * ];
 *
 * public static function fromString(string $status): self
 * {
 *     return new self($status);
 * }
 *
 * public static function active(): self
 * {
 *     return new self(self::ACTIVE);
 * }
 *
 * public static function inactive(): self
 * {
 *     return new self(self::INACTIVE);
 * }
 *
 * private function __construct(string $status)
 * {
 *     if (false === in_array($status, self::CHOICES, true)) {
 *         throw InvalidStatus::forStatus($status);
 *     }
 *
 *     $this->status = $status;
 * }
 *
 * public function toString(): string
 * {
 *     return $this->status;
 * }
 *
 * public function equals($other): bool
 * {
 *     if(!$other instanceof self) {
 *         return false;
 *     }
 *
 *     return $this->status === $other->status;
 * }
 *
 * public function isOneOf(EnumTest ...$status): bool
 * {
 *     foreach ($status as $otherStatus) {
 *         if ($this->equals($otherStatus)) {
 *             return true;
 *         }
 *     }
 *
 *     return false;
 * }
 *
 * public function __toString(): string
 * {
 *     return $this->status;
 * }
 */
final class EnumFactory
{
    private Parser $parser;
    private PropertyFactory $propertyFactory;
    private bool $typed;

    /**
     * @var callable
     */
    private $constNameFilter;

    /**
     * @var callable
     */
    private $constValueFilter;

    /**
     * @param Parser $parser
     * @param bool $typed
     * @param callable $constNameFilter Converts the enum value to a valid class constant name
     * @param callable $constValueFilter Converts the enum value to a valid method name
     */
    public function __construct(Parser $parser, bool $typed, callable $constNameFilter, callable $constValueFilter)
    {
        $this->parser = $parser;
        $this->typed = $typed;
        $this->constNameFilter = $constNameFilter;
        $this->constValueFilter = $constValueFilter;
        $this->propertyFactory = new PropertyFactory($typed);
    }

    /**
     * @param StringType $typeDefinition
     * @return array<NodeVisitor>
     */
    public function nodeVisitors(StringType $typeDefinition): array
    {
        $name = $typeDefinition->name() ?: 'text';

        return $this->nodeVisitorsFromNative($name, $typeDefinition->enum() ?? []);
    }

    public function classBuilder(StringType $typeDefinition): ClassBuilder
    {
        $name = $typeDefinition->name() ?: 'text';

        return $this->classBuilderFromNative($name, $typeDefinition->enum() ?? [])->setTyped($this->typed);
    }

    /**
     * @param string $name
     * @param array<mixed> $enumValues
     * @return array<NodeVisitor>
     */
    public function nodeVisitorsFromNative(string $name, array $enumValues): array
    {
        $nodeVisitors = $this->propertyFactory->nodeVisitorFromNative($name, 'string');

        $classConstant = $this->classConstantChoices($enumValues);

        \array_unshift(
            $nodeVisitors,
            new ClassConstant(
                new IdentifierGenerator(
                    $classConstant->getName(),
                    $classConstant
                )
            )
        );

        foreach (\array_reverse($enumValues) as $enumValue) {
            $classConstantEnum = $this->classConstantEnum($enumValue);

            \array_unshift(
                $nodeVisitors,
                new ClassConstant(
                    new IdentifierGenerator(
                        $classConstantEnum->getName(),
                        $classConstantEnum
                    )
                )
            );
        }

        $nodeVisitors[] = new ClassMethod($this->methodFromString($name));

        foreach ($enumValues as $enumValue) {
            $nodeVisitors[] = new ClassMethod($this->methodEnumNamedConstructor($enumValue));
        }

        $nodeVisitors[] = new ClassMethod($this->methodMagicConstruct($name));
        $nodeVisitors[] = new ClassMethod($this->methodToString($name));
        $nodeVisitors[] = new ClassMethod($this->methodEquals($name));
        $nodeVisitors[] = new ClassMethod($this->methodIsOneOf($name, null));
        $nodeVisitors[] = new ClassMethod($this->methodMagicToString($name));

        return $nodeVisitors;
    }

    /**
     * @param string $name
     * @param array<mixed> $enumValues
     * @return ClassBuilder
     */
    public function classBuilderFromNative(string $name, array $enumValues): ClassBuilder
    {
        $nodes = \array_map(function ($enum) {
            return $this->classConstantEnum($enum)->generate();
        }, $enumValues);

        $nodes[] = $this->classConstantChoices($enumValues)->generate();

        $nodes[] = $this->propertyFactory->propertyGenerator($name, 'string')->generate();
        $nodes[] = $this->methodFromString($name)->generate();

        foreach ($enumValues as $enumValue) {
            $nodes[] = $this->methodEnumNamedConstructor($enumValue)->generate();
        }

        $nodes[] = $this->methodMagicConstruct($name)->generate();
        $nodes[] = $this->methodToString($name)->generate();
        $nodes[] = $this->methodEquals($name)->generate();
        $nodes[] = $this->methodIsOneOf($name, null)->generate();
        $nodes[] = $this->methodMagicToString($name)->generate();

        return ClassBuilder::fromNodes(...$nodes)
            ->setTyped($this->typed);
    }

    /**
     * @param array<mixed> $enumValues
     * @return ClassConstGenerator
     */
    public function classConstantChoices(array $enumValues): ClassConstGenerator
    {
        $value = new Node\Expr\Array_(
            \array_map(
                function ($enum) {
                    return new Node\Expr\ConstFetch(new Node\Name('self::' . ($this->constNameFilter)($enum)));
                },
                $enumValues),
            ['kind' => Node\Expr\Array_::KIND_SHORT]
        );

        return new ClassConstGenerator(
            'CHOICES',
            $value,
            ClassConstGenerator::FLAG_PUBLIC
        );
    }

    /**
     * @param mixed $enumValue
     * @return ClassConstGenerator
     */
    public function classConstantEnum($enumValue): ClassConstGenerator
    {
        return new ClassConstGenerator(
            ($this->constNameFilter)($enumValue),
            $enumValue,
            ClassConstGenerator::FLAG_PRIVATE
        );
    }

    public function methodFromString(string $argumentName): MethodGenerator
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

        return $method;
    }

    private function throwExceptionLine(string $argumentName): string
    {
        $name = \ucfirst(($this->constValueFilter)($argumentName));

        return 'throw Invalid' . $name . '::for' . $name . '($' . $argumentName . ');';
    }

    public function methodMagicConstruct(string $argumentName): MethodGenerator
    {
        $exceptionLine = $this->throwExceptionLine($argumentName);

        $body = <<<PHP
    if (false === in_array(\$$argumentName, self::CHOICES, true)) {
        $exceptionLine
    }

    \$this->$argumentName = \$$argumentName;
PHP;

        $method = new MethodGenerator(
            '__construct',
            [
                new ParameterGenerator($argumentName, 'string'),
            ],
            MethodGenerator::FLAG_PRIVATE,
            new BodyGenerator($this->parser, $body)
        );
        $method->setTyped($this->typed);

        return $method;
    }

    public function methodToString(string $argumentName): MethodGenerator
    {
        $method = new MethodGenerator(
            'toString',
            [],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, 'return $this->' . $argumentName . ';')
        );
        $method->setTyped($this->typed);
        $method->setReturnType('string');

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

        $parameter = new ParameterGenerator($argumentName);

        $method = new MethodGenerator(
            'equals',
            [
                $parameter,
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
            new BodyGenerator($this->parser, 'return $this->' . $argumentName . ';')
        );
        $method->setTyped($this->typed);
        $method->setReturnType('string');

        return $method;
    }

    public function methodEnumNamedConstructor(string $enumValue): MethodGenerator
    {
        $method = new MethodGenerator(
            ($this->constValueFilter)($enumValue),
            [],
            MethodGenerator::FLAG_PUBLIC | MethodGenerator::FLAG_STATIC,
            new BodyGenerator($this->parser, \sprintf('return new self(self::%s);', ($this->constNameFilter)($enumValue)))
        );
        $method->setTyped($this->typed);
        $method->setReturnType('self');

        return $method;
    }

    public function methodIsOneOf(string $argumentName, ?string $className): MethodGenerator
    {
        $other = '$other' . \ucfirst($argumentName);

        $body = <<<PHP
    foreach (\$$argumentName as $other) {
        if (\$this->equals($other)) {
            return true;
        }
    }
    return false;
PHP;
        $parameter = (new ParameterGenerator($argumentName))->setVariadic(true);

        if ($className !== null) {
            $parameter->setType($className);
        }

        $method = new MethodGenerator(
            'isOneOf',
            [
                $parameter,
            ],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, $body)
        );
        $method->setTyped($this->typed);
        $method->setReturnType('bool');

        return $method;
    }
}
