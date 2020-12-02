<?php

declare(strict_types=1);

namespace OpenCodeModelingTest\JsonSchemaToPhpAst\ValueObject;

use OpenCodeModeling\JsonSchemaToPhp\Type\StringType;
use OpenCodeModeling\JsonSchemaToPhpAst\ValueObject\StringFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;

final class StringFactoryTest extends BaseTestCase
{
    private StringFactory $stringFactory;

    public function setUp(): void
    {
        parent::setUp();
        $this->stringFactory = new StringFactory($this->parser, true, $this->propertyNameFilter);
    }

    /**
     * @test
     */
    public function it_generates_code_from_native(): void
    {
        $this->assertCode($this->stringFactory->nodeVisitorsFromNative('name'));
    }

    /**
     * @test
     */
    public function it_generates_code_via_value_object_factory(): void
    {
        $this->assertCode($this->voFactory->nodeVisitors(StringType::fromDefinition(['type' => 'string', 'name' => 'name'])));
    }

    /**
     * @test
     */
    public function it_generates_code_via_value_object_factory_with_class_builder(): void
    {
        $classBuilder = $this->voFactory->classBuilder(
            StringType::fromDefinition(['type' => 'string', 'name' => 'name'])
        );
        $classBuilder->setName('StringVO');

        $this->assertCode(
            $classBuilder->generate($this->parser)
        );
    }

    /**
     * @param array<NodeVisitor> $nodeVisitors
     */
    private function assertCode(array $nodeVisitors): void
    {
        $ast = $this->parser->parse('<?php final class StringVO {}');

        $nodeTraverser = new NodeTraverser();

        foreach ($nodeVisitors as $nodeVisitor) {
            $nodeTraverser->addVisitor($nodeVisitor);
        }

        $expected = <<<'EOF'
<?php

final class StringVO
{
    private string $name;
    public static function fromString(string $name) : self
    {
        return new self($name);
    }
    private function __construct(string $name)
    {
        $this->name = $name;
    }
    public function toString() : string
    {
        return $this->name;
    }
    public function equals($other) : bool
    {
        if (!$other instanceof self) {
            return false;
        }
        return $this->name === $other->name;
    }
    public function __toString() : string
    {
        return $this->name;
    }
}
EOF;

        $this->assertSame($expected, $this->printer->prettyPrintFile($nodeTraverser->traverse($ast)));
    }
}
