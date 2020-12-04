<?php

declare(strict_types=1);

namespace OpenCodeModelingTest\JsonSchemaToPhpAst;

use OpenCodeModeling\CodeAst\Builder\ClassBuilder;
use OpenCodeModeling\CodeAst\Builder\ClassBuilderCollection;
use OpenCodeModeling\CodeAst\Package\ClassInfoList;
use OpenCodeModeling\CodeAst\Package\Psr4Info;
use OpenCodeModeling\Filter\FilterFactory;
use OpenCodeModeling\JsonSchemaToPhp\Type\Type;
use OpenCodeModeling\JsonSchemaToPhpAst\ClassGenerator;
use OpenCodeModeling\JsonSchemaToPhpAst\ValueObjectFactory;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\PrettyPrinterAbstract;
use PHPUnit\Framework\TestCase;

final class ClassGeneratorTest extends TestCase
{
    protected Parser $parser;
    protected PrettyPrinterAbstract $printer;
    protected ValueObjectFactory $valueObjectFactory;
    protected ClassInfoList $classInfoList;
    protected ClassGenerator $classGenerator;

    public function setUp(): void
    {
        $this->parser = (new ParserFactory())->create(ParserFactory::ONLY_PHP7);
        $this->printer = new Standard(['shortArraySyntax' => true]);

        $this->classInfoList = new ClassInfoList(
            new Psr4Info(
                'tmp/',
                'Acme',
                FilterFactory::directoryToNamespaceFilter(),
                FilterFactory::namespaceToDirectoryFilter(),
            )
        );

        $classNameFilter = FilterFactory::classNameFilter();
        $propertyNameFilter = FilterFactory::propertyNameFilter();
        $constantNameFilter = FilterFactory::constantNameFilter();
        $constantValueFilter = FilterFactory::constantValueFilter();
        $methodNameFilter = FilterFactory::methodNameFilter();

        $this->valueObjectFactory = new ValueObjectFactory(
            $this->parser,
            true,
            $classNameFilter,
            $propertyNameFilter,
            $methodNameFilter,
            $constantNameFilter,
            $constantValueFilter
        );

        $this->classGenerator = new ClassGenerator(
            $this->classInfoList,
            $this->valueObjectFactory,
            FilterFactory::classNameFilter(),
            FilterFactory::propertyNameFilter()
        );
    }

    /**
     * @test
     */
    public function it_generates_classes_of_objects(): void
    {
        $json = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'schema_with_objects.json');
        $decodedJson = \json_decode($json, true, 512, \JSON_BIGINT_AS_STRING | \JSON_THROW_ON_ERROR);

        $typeSet = Type::fromDefinition($decodedJson);
        $srcFolder = 'tmp/';

        $classBuilderCollection = ClassBuilderCollection::emptyList();
        $classBuilder = ClassBuilder::fromScratch('Order', 'Acme')->setFinal(true);

        $this->classGenerator->generateClasses($classBuilder, $classBuilderCollection, $typeSet, $srcFolder);

        $this->assertCount(7, $classBuilderCollection);

        $filter = function(string $className) {
            return function(ClassBuilder $classBuilder) use ($className) {
                return $classBuilder->getName() === $className;
            };
        };

        $this->assertOrder($classBuilderCollection->filter(($filter)('Order'))->current());
        $this->assertBillingAddress($classBuilderCollection->filter(($filter)('BillingAddress'))->current());
        $this->assertShippingAddresses($classBuilderCollection->filter(($filter)('ShippingAddresses'))->current());
        $this->assertAddress($classBuilderCollection->filter(($filter)('Address'))->current());
        $this->assertStreetAddress($classBuilderCollection->filter(($filter)('StreetAddress'))->current());
        $this->assertCity($classBuilderCollection->filter(($filter)('City'))->current());
        $this->assertState($classBuilderCollection->filter(($filter)('State'))->current());
    }

    /**
     * @test
     */
    public function it_generates_files(): void
    {
        $json = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . '_files' . DIRECTORY_SEPARATOR . 'schema_with_objects.json');
        $decodedJson = \json_decode($json, true, 512, \JSON_BIGINT_AS_STRING | \JSON_THROW_ON_ERROR);

        $typeSet = Type::fromDefinition($decodedJson);
        $srcFolder = 'tmp/';

        $classBuilderCollection = ClassBuilderCollection::emptyList();
        $classBuilder = ClassBuilder::fromScratch('Order', 'Acme')->setFinal(true);

        $this->classGenerator->generateClasses($classBuilder, $classBuilderCollection, $typeSet, $srcFolder);

        $this->assertCount(7, $classBuilderCollection);

        $this->classGenerator->addGetterMethods($classBuilderCollection, true, FilterFactory::methodNameFilter());
        $this->classGenerator->addClassConstantsForProperties(
            $classBuilderCollection,
            FilterFactory::constantNameFilter(),
            FilterFactory::constantValueFilter()
        );

        $files = $this->classGenerator->generateFiles($classBuilderCollection, $this->parser, $this->printer);

        $this->assertCount(7, $files);

        $this->assertArrayHasKey('tmp/BillingAddress.php', $files);
        $this->assertArrayHasKey('tmp/City.php', $files);
        $this->assertArrayHasKey('tmp/Order.php', $files);
        $this->assertArrayHasKey('tmp/ShippingAddresses.php', $files);
        $this->assertArrayHasKey('tmp/Address.php', $files);
        $this->assertArrayHasKey('tmp/State.php', $files);
        $this->assertArrayHasKey('tmp/StreetAddress.php', $files);

        $this->assertOrderFile($files['tmp/Order.php']);
        $this->assertBillingAddressFile($files['tmp/BillingAddress.php']);
        $this->assertShippingAddressesFile($files['tmp/ShippingAddresses.php']);
        $this->assertAddressFile($files['tmp/Address.php']);
        $this->assertStreetAddressFile($files['tmp/StreetAddress.php']);
        $this->assertCityFile($files['tmp/City.php']);
        $this->assertStateFile($files['tmp/State.php']);
    }

    private function assertOrder(ClassBuilder $classBuilder): void
    {
        $this->assertSame('Order', $classBuilder->getName());
        $this->assertSame('Acme', $classBuilder->getNamespace());
        $this->assertTrue($classBuilder->isFinal());
        $this->assertTrue($classBuilder->isStrict());
        $this->assertTrue($classBuilder->isTyped());

        $properties = $classBuilder->getProperties();
        $this->assertCount(2, $properties);
        $this->assertArrayHasKey('billingAddress', $properties);
        $this->assertArrayHasKey('shippingAddresses', $properties);

        $this->assertSame('billingAddress', $properties['billingAddress']->getName());
        $this->assertSame('Address', $properties['billingAddress']->getType());
        $this->assertTrue($properties['billingAddress']->isTyped());

        $this->assertSame('shippingAddresses', $properties['shippingAddresses']->getName());
        $this->assertSame('ShippingAddresses', $properties['shippingAddresses']->getType());
        $this->assertTrue($properties['shippingAddresses']->isTyped());

        $this->assertCount(2, $classBuilder->getNamespaceImports());
        $this->assertSame(
            ['Acme\\Address' => 'Acme\\Address', 'Acme\\ShippingAddresses' => 'Acme\\ShippingAddresses'],
            $classBuilder->getNamespaceImports()
        );
    }

    private function assertBillingAddress(ClassBuilder $classBuilder): void
    {
        $this->assertSame('BillingAddress', $classBuilder->getName());
        $this->assertSame('Acme', $classBuilder->getNamespace());
        $this->assertTrue($classBuilder->isFinal());
        $this->assertTrue($classBuilder->isStrict());
        $this->assertTrue($classBuilder->isTyped());

        $properties = $classBuilder->getProperties();
        $this->assertCount(3, $properties);
        $this->assertArrayHasKey('city', $properties);
        $this->assertArrayHasKey('federalState', $properties);
        $this->assertArrayHasKey('streetAddress', $properties);

        $this->assertCount(3, $classBuilder->getNamespaceImports());
        $this->assertSame(
            ['Acme\\StreetAddress' => 'Acme\\StreetAddress', 'Acme\\City' => 'Acme\\City', 'Acme\\State' => 'Acme\\State'],
            $classBuilder->getNamespaceImports()
        );
    }

    private function assertShippingAddresses(ClassBuilder $classBuilder): void
    {
        $this->assertSame('ShippingAddresses', $classBuilder->getName());
        $this->assertSame('Acme', $classBuilder->getNamespace());
        $this->assertTrue($classBuilder->isFinal());
        $this->assertTrue($classBuilder->isStrict());
        $this->assertTrue($classBuilder->isTyped());

        $properties = $classBuilder->getProperties();
        $this->assertCount(2, $properties);
        $this->assertArrayHasKey('position', $properties);
        $this->assertArrayHasKey('shippingAddresses', $properties);

        $this->assertSame('shippingAddresses', $properties['shippingAddresses']->getName());
        $this->assertSame('array', $properties['shippingAddresses']->getType());
        $this->assertSame('Address[]', $properties['shippingAddresses']->getTypeDocBlockHint());

        $this->assertCount(1, $classBuilder->getNamespaceImports());
        $this->assertSame(
            ['Acme\\Address' => 'Acme\\Address'],
            $classBuilder->getNamespaceImports()
        );
    }

    private function assertAddress(ClassBuilder $classBuilder): void
    {
        $this->assertSame('Address', $classBuilder->getName());
        $this->assertSame('Acme', $classBuilder->getNamespace());
        $this->assertTrue($classBuilder->isFinal());
        $this->assertTrue($classBuilder->isStrict());
        $this->assertTrue($classBuilder->isTyped());

        $properties = $classBuilder->getProperties();
        $this->assertCount(3, $properties);
        $this->assertArrayHasKey('city', $properties);
        $this->assertArrayHasKey('federalState', $properties);
        $this->assertArrayHasKey('streetAddress', $properties);

        $this->assertCount(3, $classBuilder->getNamespaceImports());
        $this->assertSame(
            ['Acme\\StreetAddress' => 'Acme\\StreetAddress', 'Acme\\City' => 'Acme\\City', 'Acme\\State' => 'Acme\\State'],
            $classBuilder->getNamespaceImports()
        );
    }

    private function assertStreetAddress(ClassBuilder $classBuilder): void
    {
        $this->assertSame('StreetAddress', $classBuilder->getName());
        $this->assertSame('Acme', $classBuilder->getNamespace());
        $this->assertTrue($classBuilder->isFinal());
        $this->assertTrue($classBuilder->isStrict());
        $this->assertTrue($classBuilder->isTyped());

        $this->assertCount(5, $classBuilder->getMethods());

        $properties = $classBuilder->getProperties();
        $this->assertCount(1, $properties);
        $this->assertArrayHasKey('streetAddress', $properties);

        $this->assertSame('streetAddress', $properties['streetAddress']->getName());
        $this->assertSame('string', $properties['streetAddress']->getType());
    }

    private function assertState(ClassBuilder $classBuilder): void
    {
        $this->assertSame('State', $classBuilder->getName());
        $this->assertSame('Acme', $classBuilder->getNamespace());
        $this->assertTrue($classBuilder->isFinal());
        $this->assertTrue($classBuilder->isStrict());
        $this->assertTrue($classBuilder->isTyped());

        $constants = $classBuilder->getConstants();
        $this->assertCount(3, $constants);
        $this->assertArrayHasKey('CHOICES', $constants);
        $this->assertArrayHasKey('DC', $constants);
        $this->assertArrayHasKey('NY', $constants);

        $methods = $classBuilder->getMethods();

        $this->assertCount(8, $methods);
        $this->assertArrayHasKey('dc', $methods);
        $this->assertArrayHasKey('ny', $methods);
        $this->assertArrayHasKey('isOneOf', $methods);

        $properties = $classBuilder->getProperties();
        $this->assertCount(1, $properties);
        $this->assertArrayHasKey('state', $properties);

        $this->assertSame('state', $properties['state']->getName());
        $this->assertSame('string', $properties['state']->getType());
    }

    private function assertCity(ClassBuilder $classBuilder): void
    {
        $this->assertSame('City', $classBuilder->getName());
        $this->assertSame('Acme', $classBuilder->getNamespace());
        $this->assertTrue($classBuilder->isFinal());
        $this->assertTrue($classBuilder->isStrict());
        $this->assertTrue($classBuilder->isTyped());

        $this->assertCount(5, $classBuilder->getMethods());

        $properties = $classBuilder->getProperties();
        $this->assertCount(1, $properties);
        $this->assertArrayHasKey('city', $properties);

        $this->assertSame('city', $properties['city']->getName());
        $this->assertSame('string', $properties['city']->getType());
    }

    private function assertOrderFile(string $code): void
    {
        $expected = <<<'PHP'
<?php

declare (strict_types=1);
namespace Acme;

use Acme\Address;
use Acme\ShippingAddresses;
final class Order
{
    public const BILLING_ADDRESS = 'billing_address';
    public const SHIPPING_ADDRESSES = 'shipping_addresses';
    private Address $billingAddress;
    private ShippingAddresses $shippingAddresses;
    public function billingAddress() : Address
    {
        return $this->billingAddress;
    }
    public function shippingAddresses() : ShippingAddresses
    {
        return $this->shippingAddresses;
    }
}
PHP;
        $this->assertSame($expected, $code);
    }


    private function assertShippingAddressesFile(string $code): void
    {
        $expected = <<<'PHP'
<?php

declare (strict_types=1);
namespace Acme;

use Acme\Address;
final class ShippingAddresses implements \Iterator, \Countable
{
    private int $position = 0;
    /**
     * @var Address[]
     */
    private array $shippingAddresses;
    public function rewind() : void
    {
        $this->position = 0;
    }
    public function current() : Address
    {
        return $this->shippingAddresses[$this->position];
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
        return isset($this->shippingAddresses[$this->position]);
    }
    public function count() : int
    {
        return count($this->shippingAddresses);
    }
    public static function fromArray(array $shippingAddresses) : self
    {
        return new self(...array_map(static function (string $item) {
            return Address::fromString($item);
        }, $shippingAddresses));
    }
    public static function fromItems(Address ...$shipping_addresses) : self
    {
        return new self(...$shipping_addresses);
    }
    public static function emptyList() : self
    {
        return new self();
    }
    private function __construct(Address ...$shipping_addresses)
    {
        $this->shipping_addresses = $shipping_addresses;
    }
    public function add(Address $address) : self
    {
        $copy = clone $this;
        $copy->shipping_addresses[] = $address;
        return $copy;
    }
    public function remove(Address $address) : self
    {
        $copy = clone $this;
        $copy->shipping_addresses = array_values(array_filter($copy->shipping_addresses, static function ($v) {
            return !$v->equals($address);
        }));
        return $copy;
    }
    public function first() : ?Address
    {
        return $this->shippingAddresses[0] ?? null;
    }
    public function last() : ?Address
    {
        if (count($this->shippingAddresses) === 0) {
            return null;
        }
        return $this->shippingAddresses[count($this->shippingAddresses) - 1];
    }
    public function contains(Address $address) : bool
    {
        foreach ($this->shippingAddresses as $existingItem) {
            if ($existingItem->equals($address)) {
                return true;
            }
        }
        return false;
    }
    public function filter(callable $filter) : self
    {
        return new self(...array_values(array_filter($this->shipping_addresses, static function ($v) {
            return $filter($v);
        })));
    }
    /**
     * @return Address[]
     */
    public function items() : array
    {
        return $this->shippingAddresses;
    }
    public function toArray() : array
    {
        return \array_map(static function (Address $address) {
            return $address->toArray();
        }, $this->shippingAddresses);
    }
    /**
     * @param mixed $other
     * @return bool
     */
    public function equals($other) : bool
    {
        if (!$other instanceof self) {
            return false;
        }
        return $this->toArray() === $other->toArray();
    }
}
PHP;

        $this->assertSame($expected, $code);
    }

    private function assertAddressFile(string $code): void
    {
        $expected = <<<'PHP'
<?php

declare (strict_types=1);
namespace Acme;

use Acme\StreetAddress;
use Acme\City;
use Acme\State;
final class Address
{
    public const STREET_ADDRESS = 'street_address';
    public const CITY = 'city';
    public const FEDERAL_STATE = 'federal_state';
    private StreetAddress $streetAddress;
    private City $city;
    private State $federalState;
    public function streetAddress() : StreetAddress
    {
        return $this->streetAddress;
    }
    public function city() : City
    {
        return $this->city;
    }
    public function federalState() : State
    {
        return $this->federalState;
    }
}
PHP;
        $this->assertSame($expected, $code);
    }

    private function assertBillingAddressFile(string $code): void
    {
        $expected = <<<'PHP'
<?php

declare (strict_types=1);
namespace Acme;

use Acme\StreetAddress;
use Acme\City;
use Acme\State;
final class BillingAddress
{
    public const STREET_ADDRESS = 'street_address';
    public const CITY = 'city';
    public const FEDERAL_STATE = 'federal_state';
    private StreetAddress $streetAddress;
    private City $city;
    private State $federalState;
    public function streetAddress() : StreetAddress
    {
        return $this->streetAddress;
    }
    public function city() : City
    {
        return $this->city;
    }
    public function federalState() : State
    {
        return $this->federalState;
    }
}
PHP;
        $this->assertSame($expected, $code);
    }

    private function assertCityFile(string $code): void
    {
        $expected = <<<'PHP'
<?php

declare (strict_types=1);
namespace Acme;

final class City
{
    private string $city;
    public static function fromString(string $city) : self
    {
        return new self($city);
    }
    private function __construct(string $city)
    {
        $this->city = $city;
    }
    public function toString() : string
    {
        return $this->city;
    }
    public function equals($other) : bool
    {
        if (!$other instanceof self) {
            return false;
        }
        return $this->city === $other->city;
    }
    public function __toString() : string
    {
        return $this->city;
    }
}
PHP;
        $this->assertSame($expected, $code);
    }

    private function assertStreetAddressFile(string $code): void
    {
        $expected = <<<'PHP'
<?php

declare (strict_types=1);
namespace Acme;

final class StreetAddress
{
    private string $streetAddress;
    public static function fromString(string $streetAddress) : self
    {
        return new self($streetAddress);
    }
    private function __construct(string $streetAddress)
    {
        $this->streetAddress = $streetAddress;
    }
    public function toString() : string
    {
        return $this->streetAddress;
    }
    public function equals($other) : bool
    {
        if (!$other instanceof self) {
            return false;
        }
        return $this->streetAddress === $other->streetAddress;
    }
    public function __toString() : string
    {
        return $this->streetAddress;
    }
}
PHP;
        $this->assertSame($expected, $code);
    }

    private function assertStateFile(string $code): void
    {
        $expected = <<<'PHP'
<?php

declare (strict_types=1);
namespace Acme;

final class State
{
    private const NY = 'NY';
    private const DC = 'DC';
    public const CHOICES = [self::NY, self::DC];
    private string $state;
    public static function fromString(string $state) : self
    {
        return new self($state);
    }
    public static function ny() : self
    {
        return new self(self::NY);
    }
    public static function dc() : self
    {
        return new self(self::DC);
    }
    private function __construct(string $state)
    {
        if (false === in_array($state, self::CHOICES, true)) {
            throw InvalidState::forState($state);
        }
        $this->state = $state;
    }
    public function toString() : string
    {
        return $this->state;
    }
    public function equals($other) : bool
    {
        if (!$other instanceof self) {
            return false;
        }
        return $this->state === $other->state;
    }
    public function isOneOf(...$state) : bool
    {
        foreach ($state as $otherState) {
            if ($this->equals($otherState)) {
                return true;
            }
        }
        return false;
    }
    public function __toString() : string
    {
        return $this->state;
    }
}
PHP;
        $this->assertSame($expected, $code);
    }
}
