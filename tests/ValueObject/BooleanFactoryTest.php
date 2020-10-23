<?php

declare(strict_types=1);

namespace OpenCodeModelingTest\JsonSchemaToPhpAst\ValueObject;

use OpenCodeModeling\JsonSchemaToPhp\Type\BooleanType;
use OpenCodeModeling\JsonSchemaToPhpAst\ValueObject\BooleanFactory;
use OpenCodeModeling\JsonSchemaToPhpAst\ValueObjectFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\PrettyPrinterAbstract;
use PHPUnit\Framework\TestCase;

final class BooleanFactoryTest extends TestCase
{
    private Parser $parser;
    private PrettyPrinterAbstract $printer;
    private BooleanFactory $integerFactory;

    public function setUp(): void
    {
        $this->parser = (new ParserFactory())->create(ParserFactory::ONLY_PHP7);
        $this->printer = new Standard(['shortArraySyntax' => true]);
        $this->integerFactory = new BooleanFactory($this->parser, true);
    }

    /**
     * @test
     */
    public function it_generates_code_from_native(): void
    {
        $this->assertCode($this->integerFactory->nodeVisitorsFromNative('boolean'));
    }

    /**
     * @test
     */
    public function it_generates_code_via_value_object_factory(): void
    {
        $voFactory = new ValueObjectFactory($this->parser, true);
        $this->assertCode($voFactory->nodeVisitors(BooleanType::fromDefinition(['type' => 'boolean'])));
    }

    /**
     * @param array<NodeVisitor> $nodeVisitors
     */
    private function assertCode(array $nodeVisitors): void
    {
        $ast = $this->parser->parse('<?php final class BooleanVO {}');

        $nodeTraverser = new NodeTraverser();

        foreach ($nodeVisitors as $nodeVisitor) {
            $nodeTraverser->addVisitor($nodeVisitor);
        }

        $expected = <<<'EOF'
<?php

final class BooleanVO
{
    private bool $boolean;
    public static function fromBool(bool $boolean) : self
    {
        return new self($boolean);
    }
    private function __construct(bool $boolean)
    {
        $this->boolean = $boolean;
    }
    public function toBool() : bool
    {
        return $this->boolean;
    }
    public function equals($other) : bool
    {
        if (!$other instanceof self) {
            return false;
        }
        return $this->boolean === $other->boolean;
    }
    public function __toString() : string
    {
        return $this->boolean ? 'TRUE' : 'FALSE';
    }
}
EOF;

        $this->assertSame($expected, $this->printer->prettyPrintFile($nodeTraverser->traverse($ast)));
    }
}
