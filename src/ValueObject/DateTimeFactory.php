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
use PhpParser\NodeVisitor;
use PhpParser\Parser;

/**
 * This file creates node visitors for a value object of type bool.
 *
 * The following code will be generated:
 *
 *    private const OUTPUT_FORMAT = 'Y-m-d\TH:i:s.uP';
 *
 *    private DateTimeImmutable $dateTime;
 *
 *    public static function fromDateTime(DateTimeImmutable $dateTime): self
 *    {
 *        return new self(self::ensureUtc($dateTime));
 *    }
 *
 *    public static function fromString(string $dateTime): self
 *    {
 *        try {
 *            $dateTimeImmutable = new DateTimeImmutable($dateTime);
 *        } catch (\Exception $e) {
 *            throw new InvalidArgumentException(
 *                sprintf(
 *                    'String "%s" is not supported. Use a date time format which is compatible with ISO 8601.',
 *                    $dateTime
 *                )
 *            );
 *        }
 *
 *        $dateTimeImmutable = self::ensureUtc($dateTimeImmutable);
 *
 *        return new self($dateTimeImmutable);
 *    }
 *
 *    private function __construct(DateTimeImmutable $dateTime)
 *    {
 *        $this->dateTime = $dateTime;
 *    }
 *
 *    public function toString(): string
 *    {
 *        return $this->dateTime->format(self::OUTPUT_FORMAT);
 *    }
 *
 *    public function dateTime(): DateTimeImmutable
 *    {
 *        return $this->dateTime;
 *    }
 *
 *    public function __toString(): string
 *    {
 *        return $this->toString();
 *    }
 *
 *    private static function ensureUtc(DateTimeImmutable $dateTime): DateTimeImmutable
 *    {
 *        if ($dateTime->getTimezone()->getName() !== 'UTC') {
 *            $dateTime = $dateTime->setTimezone(new \DateTimeZone('UTC'));
 *        }
 *
 *        return $dateTime;
 *    }
 */
final class DateTimeFactory
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
     * @param  StringType $typeDefinition
     * @return array<NodeVisitor>
     */
    public function nodeVisitors(StringType $typeDefinition): array
    {
        $name = $typeDefinition->name() ?: 'dateTime';

        return $this->nodeVisitorsFromNative($name);
    }

    public function classBuilder(StringType $typeDefinition): ClassBuilder
    {
        $name = $typeDefinition->name() ?: 'dateTime';

        return $this->classBuilderFromNative($name)->setTyped($this->typed);
    }

    /**
     * @param string $name
     * @param string $outputFormat
     * @return array<NodeVisitor>
     */
    public function nodeVisitorsFromNative(string $name, string $outputFormat = DATE_ATOM): array
    {
        $nodeVisitors = $this->propertyFactory->nodeVisitorFromNative($name, 'DateTimeImmutable');

        $classConstant = $this->classConstant($outputFormat);

        \array_unshift(
            $nodeVisitors,
            new ClassConstant(
                new IdentifierGenerator(
                    $classConstant->getName(),
                    $classConstant
                )
            )
        );

        $nodeVisitors[] = new ClassMethod($this->methodFromDateTime($name));
        $nodeVisitors[] = new ClassMethod($this->methodFromString($name));
        $nodeVisitors[] = new ClassMethod($this->methodMagicConstruct($name));
        $nodeVisitors[] = new ClassMethod($this->methodToString($name));
        $nodeVisitors[] = new ClassMethod($this->methodDateTime($name));
        $nodeVisitors[] = new ClassMethod($this->methodMagicToString());
        $nodeVisitors[] = new ClassMethod($this->methodEnsureUtc($name));

        return $nodeVisitors;
    }

    public function classBuilderFromNative(string $name, string $outputFormat = DATE_ATOM): ClassBuilder
    {
        return ClassBuilder::fromNodes(
            $this->classConstant($outputFormat)->generate(),
            $this->propertyFactory->propertyGenerator($name, 'DateTimeImmutable')->generate(),
            $this->methodFromDateTime($name)->generate(),
            $this->methodFromString($name)->generate(),
            $this->methodMagicConstruct($name)->generate(),
            $this->methodToString($name)->generate(),
            $this->methodDateTime($name)->generate(),
            $this->methodMagicToString()->generate(),
            $this->methodEnsureUtc($name)->generate(),
        )->setTyped($this->typed);
    }

    public function classConstant(string $outputFormat = DATE_ATOM): ClassConstGenerator
    {
        return new ClassConstGenerator(
            'OUTPUT_FORMAT',
            $outputFormat,
            ClassConstGenerator::FLAG_PRIVATE
        );
    }

    public function methodFromDateTime(string $argumentName): MethodGenerator
    {
        $method = new MethodGenerator(
            'fromDateTime',
            [
                new ParameterGenerator($argumentName, 'DateTimeImmutable'),
            ],
            MethodGenerator::FLAG_STATIC | MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, 'return new self(self::ensureUtc($' . $argumentName . '));')
        );
        $method->setTyped($this->typed);
        $method->setReturnType('self');

        return $method;
    }

    public function methodFromString(string $argumentName): MethodGenerator
    {
        $body = <<<PHP
    try {
        \$dateTimeImmutable = new DateTimeImmutable(\$$argumentName);
    } catch (\Exception \$e) {
        throw new InvalidArgumentException(sprintf('String "%s" is not supported. Use a date time format which is compatible with ISO 8601.', \$$argumentName));
    }

    \$dateTimeImmutable = self::ensureUtc(\$dateTimeImmutable);

    return new self(\$dateTimeImmutable);
PHP;

        $method = new MethodGenerator(
            'fromString',
            [
                new ParameterGenerator($argumentName, 'string'),
            ],
            MethodGenerator::FLAG_STATIC | MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, $body)
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
                new ParameterGenerator($argumentName, 'DateTimeImmutable'),
            ],
            MethodGenerator::FLAG_PRIVATE,
            new BodyGenerator($this->parser, \sprintf('$this->%s = $%s;', $argumentName, $argumentName))
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
            new BodyGenerator($this->parser, 'return $this->' . $argumentName . '->format(self::OUTPUT_FORMAT);')
        );
        $method->setTyped($this->typed);
        $method->setReturnType('string');

        return $method;
    }

    public function methodDateTime(string $argumentName): MethodGenerator
    {
        $method = new MethodGenerator(
            'dateTime',
            [],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, 'return $this->' . $argumentName . ';')
        );
        $method->setTyped($this->typed);
        $method->setReturnType('DateTimeImmutable');

        return $method;
    }

    public function methodMagicToString(): MethodGenerator
    {
        $method = new MethodGenerator(
            '__toString',
            [],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, 'return $this->toString();')
        );
        $method->setTyped($this->typed);
        $method->setReturnType('string');

        return $method;
    }

    public function methodEnsureUtc(string $argumentName): MethodGenerator
    {
        $body = <<<'PHP'
        if ($argumentName->getTimezone()->getName() !== 'UTC') {
            $argumentName = $argumentName->setTimezone(new \DateTimeZone('UTC'));
        }

        return $argumentName;
PHP;
        $body = \str_replace('argumentName', $argumentName, $body);

        $method = new MethodGenerator(
            'ensureUtc',
            [
                new ParameterGenerator($argumentName, 'DateTimeImmutable'),
            ],
            MethodGenerator::FLAG_STATIC | MethodGenerator::FLAG_PRIVATE,
            new BodyGenerator($this->parser, $body)
        );
        $method->setTyped($this->typed);
        $method->setReturnType('DateTimeImmutable');

        return $method;
    }
}
