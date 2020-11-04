<?php

/**
 * @see       https://github.com/open-code-modeling/json-schema-to-php-ast for the canonical source repository
 * @copyright https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/COPYRIGHT.md
 * @license   https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/LICENSE.md MIT License
 */

declare(strict_types=1);

namespace OpenCodeModeling\JsonSchemaToPhpAst\ValueObject;

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

    /**
     * @param string $name
     * @param string $outputFormat
     * @return array<NodeVisitor>
     */
    public function nodeVisitorsFromNative(string $name, string $outputFormat = DATE_ATOM): array
    {
        $nodeVisitors = $this->propertyFactory->nodeVisitorFromNative($name, 'DateTimeImmutable');

        \array_unshift(
            $nodeVisitors,
            new ClassConstant(
                new IdentifierGenerator(
                    'OUTPUT_FORMAT',
                    new ClassConstGenerator(
                        'OUTPUT_FORMAT',
                        $outputFormat,
                        ClassConstGenerator::FLAG_PRIVATE
                    )
                )
            )
        );

        $nodeVisitors[] = $this->methodFromDateTime($name);
        $nodeVisitors[] = $this->methodFromString($name);
        $nodeVisitors[] = $this->methodMagicConstruct($name);
        $nodeVisitors[] = $this->methodToString($name);
        $nodeVisitors[] = $this->methodDateTime($name);
        $nodeVisitors[] = $this->methodMagicToString();
        $nodeVisitors[] = $this->methodEnsureUtc($name);

        return $nodeVisitors;
    }

    public function methodFromDateTime(string $argumentName): NodeVisitor
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

        return new ClassMethod($method);
    }

    public function methodFromString(string $argumentName): NodeVisitor
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

        return new ClassMethod($method);
    }

    public function methodMagicConstruct(string $argumentName): NodeVisitor
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

        return new ClassMethod($method);
    }

    public function methodToString(string $argumentName): NodeVisitor
    {
        $method = new MethodGenerator(
            'toString',
            [],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, 'return $this->' . $argumentName . '->format(self::OUTPUT_FORMAT);')
        );
        $method->setReturnType('string');

        return new ClassMethod($method);
    }

    public function methodDateTime(string $argumentName): NodeVisitor
    {
        $method = new MethodGenerator(
            'dateTime',
            [],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, 'return $this->' . $argumentName . ';')
        );
        $method->setTyped($this->typed);
        $method->setReturnType('DateTimeImmutable');

        return new ClassMethod($method);
    }

    public function methodMagicToString(): NodeVisitor
    {
        $method = new MethodGenerator(
            '__toString',
            [],
            MethodGenerator::FLAG_PUBLIC,
            new BodyGenerator($this->parser, 'return $this->toString();')
        );
        $method->setTyped($this->typed);
        $method->setReturnType('string');

        return new ClassMethod($method);
    }

    public function methodEnsureUtc(string $argumentName): NodeVisitor
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

        return new ClassMethod($method);
    }
}
