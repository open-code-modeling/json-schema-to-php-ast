<?php

declare(strict_types=1);

namespace OpenCodeModelingTest\JsonSchemaToPhpAst\ValueObject;

use OpenCodeModeling\JsonSchemaToPhp\Type\NumberType;
use OpenCodeModeling\JsonSchemaToPhpAst\ValueObject\NumberFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;

final class NumberFactoryTest extends BaseTestCase
{
    private NumberFactory $integerFactory;

    public function setUp(): void
    {
        parent::setUp();
        $this->integerFactory = new NumberFactory($this->parser, true);
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
        $this->assertCode($this->voFactory->nodeVisitors(NumberType::fromDefinition(['type' => 'number'])));
    }

    /**
     * @test
     */
    public function it_generates_code_via_value_object_factory_with_class_builder(): void
    {
        $classBuilder = $this->voFactory->classBuilder(
            NumberType::fromDefinition(['type' => 'number'])
        );
        $classBuilder->setName('NumberVO');

        $this->assertCode(
            $classBuilder->generate($this->parser)
        );
    }

    /**
     * @param array<NodeVisitor> $nodeVisitors
     */
    private function assertCode(array $nodeVisitors): void
    {
        $ast = $this->parser->parse('<?php final class NumberVO {}');

        $nodeTraverser = new NodeTraverser();

        foreach ($nodeVisitors as $nodeVisitor) {
            $nodeTraverser->addVisitor($nodeVisitor);
        }

        $expected = <<<'EOF'
<?php

final class NumberVO
{
    private float $number;
    public static function fromFloat(float $number) : self
    {
        return new self($number);
    }
    private function __construct(float $number)
    {
        $this->number = $number;
    }
    public function toFloat() : float
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
