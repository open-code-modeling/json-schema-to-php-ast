<?php

declare(strict_types=1);

namespace OpenCodeModelingTest\JsonSchemaToPhpAst\ValueObject;

use OpenCodeModeling\JsonSchemaToPhp\Type\IntegerType;
use OpenCodeModeling\JsonSchemaToPhpAst\ValueObject\IntegerFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;

final class IntegerFactoryTest extends BaseTestCase
{
    private IntegerFactory $integerFactory;

    public function setUp(): void
    {
        parent::setUp();
        $this->integerFactory = new IntegerFactory($this->parser, true);
    }

    /**
     * @test
     */
    public function it_generates_code_from_native(): void
    {
        $this->assertCode($this->integerFactory->nodeVisitorsFromNative('number'));
    }

    /**
     * @test
     */
    public function it_generates_code_via_value_object_factory(): void
    {
        $this->assertCode($this->voFactory->nodeVisitors(IntegerType::fromDefinition(['type' => 'integer'])));
    }

    /**
     * @test
     */
    public function it_generates_code_via_value_object_factory_with_class_builder(): void
    {
        $classBuilder = $this->voFactory->classBuilder(
            IntegerType::fromDefinition(['type' => 'integer'])
        );
        $classBuilder->setName('IntegerVO');

        $this->assertCode(
            $classBuilder->generate($this->parser)
        );
    }

    /**
     * @param array<NodeVisitor> $nodeVisitors
     */
    private function assertCode(array $nodeVisitors): void
    {
        $ast = $this->parser->parse('<?php final class IntegerVO {}');

        $nodeTraverser = new NodeTraverser();

        foreach ($nodeVisitors as $nodeVisitor) {
            $nodeTraverser->addVisitor($nodeVisitor);
        }

        $expected = <<<'EOF'
<?php

final class IntegerVO
{
    private int $number;
    public static function fromInt(int $number) : self
    {
        return new self($number);
    }
    private function __construct(int $number)
    {
        $this->number = $number;
    }
    public function toInt() : int
    {
        return $this->number;
    }
    public function equals($other) : bool
    {
        if (!$other instanceof self) {
            return false;
        }
        return $this->number === $other->number;
    }
    public function __toString() : string
    {
        return (string) $this->number;
    }
}
EOF;

        $this->assertSame($expected, $this->printer->prettyPrintFile($nodeTraverser->traverse($ast)));
    }
}
