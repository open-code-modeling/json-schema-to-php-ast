<?php

/**
 * @see       https://github.com/open-code-modeling/json-schema-to-php-ast for the canonical source repository
 * @copyright https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/COPYRIGHT.md
 * @license   https://github.com/open-code-modeling/json-schema-to-php-ast/blob/master/LICENSE.md MIT License
 */

declare(strict_types=1);

namespace OpenCodeModelingTest\JsonSchemaToPhpAst\Common;

use OpenCodeModeling\JsonSchemaToPhpAst\Common\IteratorFactory;
use OpenCodeModelingTest\JsonSchemaToPhpAst\ValueObject\BaseTestCase;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;

final class IteratorFactoryTest extends BaseTestCase
{
    private IteratorFactory $iteratorFactory;

    public function setUp(): void
    {
        parent::setUp();
        $this->iteratorFactory = new IteratorFactory($this->parser, true, $this->propertyNameFilter);
    }

    /**
     * @test
     */
    public function it_generates_code_from_native(): void
    {
        $this->assertCode($this->iteratorFactory->nodeVisitorsFromNative('reasonTypes', 'ReasonType'));
    }

    /**
     * @test
     */
    public function it_generates_code_from_native_with_class_builder(): void
    {
        $this->assertCode(
            $this->iteratorFactory->classBuilderFromNative('reasonTypes', 'ReasonType')
                ->setName('IteratorVO')
                ->generate($this->parser)
        );
    }

    /**
     * @param array<NodeVisitor> $nodeVisitors
     */
    private function assertCode(array $nodeVisitors): void
    {
        $ast = $this->parser->parse('<?php final class IteratorVO {}');

        $nodeTraverser = new NodeTraverser();

        foreach ($nodeVisitors as $nodeVisitor) {
            $nodeTraverser->addVisitor($nodeVisitor);
        }

        $expected = <<<'EOF'
<?php

final class IteratorVO implements \Iterator, \Countable
{
    private int $position = 0;
    /**
     * @var ReasonType[]
     */
    private array $reasonTypes;
    public function rewind() : void
    {
        $this->position = 0;
    }
    public function current() : ReasonType
    {
        return $this->reasonTypes[$this->position];
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
        return isset($this->reasonTypes[$this->position]);
    }
    public function count() : int
    {
        return count($this->reasonTypes);
    }
}
EOF;

        $this->assertSame($expected, $this->printer->prettyPrintFile($nodeTraverser->traverse($ast)));
    }
}
