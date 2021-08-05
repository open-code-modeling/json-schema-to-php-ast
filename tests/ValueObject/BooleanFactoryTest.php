<?php

/**
 * @see       https://github.com/open-code-modeling/json-schema-to-php-ast for the canonical source repository
 * @copyright https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/COPYRIGHT.md
 * @license   https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/LICENSE.md MIT License
 */

declare(strict_types=1);

namespace OpenCodeModelingTest\JsonSchemaToPhpAst\ValueObject;

use OpenCodeModeling\JsonSchemaToPhp\Type\BooleanType;
use OpenCodeModeling\JsonSchemaToPhpAst\ValueObject\BooleanFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;

final class BooleanFactoryTest extends BaseTestCase
{
    private BooleanFactory $integerFactory;

    public function setUp(): void
    {
        parent::setUp();
        $this->integerFactory = new BooleanFactory($this->parser, true, $this->propertyNameFilter);
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
        $this->assertCode($this->voFactory->nodeVisitors(BooleanType::fromDefinition(['type' => 'boolean'])));
    }

    /**
     * @test
     */
    public function it_generates_code_via_value_object_factory_with_class_builder(): void
    {
        $classBuilder = $this->voFactory->classBuilder(
            BooleanType::fromDefinition(['type' => 'boolean'])
        );
        $classBuilder->setName('BooleanVO');

        $this->assertCode(
            $classBuilder->generate($this->parser)
        );
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
    /**
     * @param mixed $other
     */
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
