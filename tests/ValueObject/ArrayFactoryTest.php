<?php

/**
 * @see       https://github.com/open-code-modeling/json-schema-to-php-ast for the canonical source repository
 * @copyright https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/COPYRIGHT.md
 * @license   https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/LICENSE.md MIT License
 */

declare(strict_types=1);

namespace OpenCodeModelingTest\JsonSchemaToPhpAst\ValueObject;

use OpenCodeModeling\JsonSchemaToPhp\Type\ArrayType;
use OpenCodeModeling\JsonSchemaToPhp\Type\Type;
use OpenCodeModeling\JsonSchemaToPhpAst\ValueObject\ArrayFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;

final class ArrayFactoryTest extends BaseTestCase
{
    private ArrayFactory $arrayFactory;

    public function setUp(): void
    {
        parent::setUp();
        $this->arrayFactory = new ArrayFactory(
            $this->parser,
            true,
            $this->classNameFilter,
            $this->propertyNameFilter
        );
    }

    /**
     * @test
     */
    public function it_generates_code_from_native(): void
    {
        $definition = \json_decode(
            \file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'schema_with_array_type_ref.json'),
            true
        );

        $typeSet = Type::fromDefinition($definition);

        /** @var ArrayType $type */
        $type = $typeSet->first();

        $this->assertInstanceOf(ArrayType::class, $type);

        $this->assertCode(
            $this->arrayFactory->nodeVisitorsFromNative('items', ...$type->items())
        );

        $this->assertCode(
            $this->arrayFactory->nodeVisitorsFromNative('items', ...$type->items()),
            $this->getExpectedGeneratedCode()
        );
    }

    /**
     * @test
     */
    public function it_generates_code_from_definition(): void
    {
        $definition = \json_decode(
            \file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'schema_with_array_type_ref.json'),
            true
        );

        $typeSet = Type::fromDefinition($definition);

        /** @var ArrayType $type */
        $type = $typeSet->first();

        $this->assertInstanceOf(ArrayType::class, $type);

        $this->assertCode($this->arrayFactory->nodeVisitors($type));

        $this->assertCode(
            $this->arrayFactory->nodeVisitors($type),
            $this->getExpectedGeneratedCode()
        );
    }

    /**
     * @test
     */
    public function it_generates_code_via_value_object_factory(): void
    {
        $definition = \json_decode(
            \file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'schema_with_array_type_ref.json'),
            true
        );

        $this->assertCode(
            $this->voFactory->nodeVisitors(
                ArrayType::fromDefinition($definition)
            )
        );

        $this->assertCode(
            $this->voFactory->nodeVisitors(
                ArrayType::fromDefinition($definition)
            ),
            $this->getExpectedGeneratedCode()
        );
    }

    /**
     * @test
     */
    public function it_generates_code_via_value_object_factory_with_class_builder(): void
    {
        $definition = \json_decode(
            \file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'schema_with_array_type_ref.json'),
            true
        );

        $classBuilder = $this->voFactory->classBuilder(ArrayType::fromDefinition($definition));
        $classBuilder->setName('ReasonTypeListVO');

        $this->assertCode(
            $classBuilder->generate($this->parser)
        );
        $this->assertCode(
            $classBuilder->generate($this->parser),
            $this->getExpectedGeneratedCode()
        );
    }

    /**
     * @param array<NodeVisitor> $nodeVisitors
     * @param string $code
     */
    private function assertCode(array $nodeVisitors, string $code = '<?php final class ReasonTypeListVO {}'): void
    {
        $ast = $this->parser->parse($code);

        $nodeTraverser = new NodeTraverser();

        foreach ($nodeVisitors as $nodeVisitor) {
            $nodeTraverser->addVisitor($nodeVisitor);
        }

        $this->assertSame($this->getExpectedGeneratedCode(), $this->printer->prettyPrintFile($nodeTraverser->traverse($ast)));
    }

    private function getExpectedGeneratedCode(): string
    {
        return <<<'EOF'
        <?php
        
        final class ReasonTypeListVO implements \Iterator, \Countable
        {
            private int $position = 0;
            /**
             * @var ReasonType[]
             */
            private array $items;
            public function rewind() : void
            {
                $this->position = 0;
            }
            public function current() : ReasonType
            {
                return $this->items[$this->position];
            }
            public function key() : int
            {
                return $this->position;
            }
            public function next() : void
            {
                ++$this->position;
            }
            public function valid() : bool
            {
                return isset($this->items[$this->position]);
            }
            public function count() : int
            {
                return count($this->items);
            }
            public static function fromArray(array $items) : self
            {
                return new self(...array_map(static function (string $item) {
                    return ReasonType::fromString($item);
                }, $items));
            }
            public static function fromItems(ReasonType ...$items) : self
            {
                return new self(...$items);
            }
            public static function emptyList() : self
            {
                return new self();
            }
            private function __construct(ReasonType ...$items)
            {
                $this->items = $items;
            }
            public function add(ReasonType $reasonType) : self
            {
                $copy = clone $this;
                $copy->items[] = $reasonType;
                return $copy;
            }
            public function remove(ReasonType $reasonType) : self
            {
                $copy = clone $this;
                $copy->items = array_values(array_filter($copy->items, static function ($v) use($reasonType) {
                    return !$v->equals($reasonType);
                }));
                return $copy;
            }
            public function first() : ?ReasonType
            {
                return $this->items[0] ?? null;
            }
            public function last() : ?ReasonType
            {
                if (count($this->items) === 0) {
                    return null;
                }
                return $this->items[count($this->items) - 1];
            }
            public function contains(ReasonType $reasonType) : bool
            {
                foreach ($this->items as $existingItem) {
                    if ($existingItem->equals($reasonType)) {
                        return true;
                    }
                }
                return false;
            }
            public function filter(callable $filter) : self
            {
                return new self(...array_values(array_filter($this->items, static function ($v) use($filter) {
                    return $filter($v);
                })));
            }
            /**
             * @return ReasonType[]
             */
            public function items() : array
            {
                return $this->items;
            }
            public function toArray() : array
            {
                return \array_map(static function (ReasonType $reasonType) {
                    return $reasonType->toString();
                }, $this->items);
            }
            /**
             * @param mixed $other
             */
            public function equals($other) : bool
            {
                if (!$other instanceof self) {
                    return false;
                }
                return $this->toArray() === $other->toArray();
            }
        }
        EOF;
    }
}
