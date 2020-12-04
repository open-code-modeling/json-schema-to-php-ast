<?php

declare(strict_types=1);

namespace OpenCodeModelingTest\JsonSchemaToPhpAst\ValueObject;

use OpenCodeModeling\JsonSchemaToPhp\Type\StringType;
use OpenCodeModeling\JsonSchemaToPhpAst\ValueObject\EnumFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;

final class EnumFactoryTest extends BaseTestCase
{
    private EnumFactory $enumFactory;
    private const ENUM_VALUES = [
        'active',
        'inactive',
    ];

    public function setUp(): void
    {
        parent::setUp();
        $this->enumFactory = new EnumFactory(
            $this->parser,
            true,
            $this->propertyNameFilter,
            $this->methodNameFilter,
            $this->filterConstName,
            $this->filterConstValue
        );
    }

    /**
     * @test
     */
    public function it_generates_code_from_native(): void
    {
        $this->assertCode($this->enumFactory->nodeVisitorsFromNative('status', self::ENUM_VALUES));
    }

    /**
     * @test
     */
    public function it_generates_code_via_value_object_factory(): void
    {
        $this->assertCode(
            $this->voFactory->nodeVisitors(
                StringType::fromDefinition(
                    [
                        'type' => 'string',
                        'name' => 'status',
                        'enum' => self::ENUM_VALUES,
                    ]
                )
            )
        );
    }

    /**
     * @test
     */
    public function it_generates_code_via_value_object_factory_with_class_builder(): void
    {
        $classBuilder = $this->voFactory->classBuilder(
            StringType::fromDefinition(
                [
                    'type' => 'string',
                    'name' => 'status',
                    'enum' => self::ENUM_VALUES,
                ]
            )
        );
        $classBuilder->setName('EnumVO');

        $this->assertCode(
            $classBuilder->generate($this->parser)
        );
    }

    /**
     * @param array<NodeVisitor> $nodeVisitors
     */
    private function assertCode(array $nodeVisitors): void
    {
        $ast = $this->parser->parse('<?php final class EnumVO {}');

        $nodeTraverser = new NodeTraverser();

        foreach ($nodeVisitors as $nodeVisitor) {
            $nodeTraverser->addVisitor($nodeVisitor);
        }

        $expected = <<<'EOF'
<?php

final class EnumVO
{
    private const ACTIVE = 'active';
    private const INACTIVE = 'inactive';
    public const CHOICES = [self::ACTIVE, self::INACTIVE];
    private string $status;
    public static function fromString(string $status) : self
    {
        return new self($status);
    }
    public static function active() : self
    {
        return new self(self::ACTIVE);
    }
    public static function inactive() : self
    {
        return new self(self::INACTIVE);
    }
    private function __construct(string $status)
    {
        if (false === in_array($status, self::CHOICES, true)) {
            throw InvalidStatus::forStatus($status);
        }
        $this->status = $status;
    }
    public function toString() : string
    {
        return $this->status;
    }
    public function equals($other) : bool
    {
        if (!$other instanceof self) {
            return false;
        }
        return $this->status === $other->status;
    }
    public function isOneOf(...$status) : bool
    {
        foreach ($status as $otherStatus) {
            if ($this->equals($otherStatus)) {
                return true;
            }
        }
        return false;
    }
    public function __toString() : string
    {
        return $this->status;
    }
}
EOF;

        $this->assertSame($expected, $this->printer->prettyPrintFile($nodeTraverser->traverse($ast)));
    }
}
