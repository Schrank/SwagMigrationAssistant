<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\Test\Profile\Shopware\Premapping;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\StateMachineEntity;
use SwagMigrationAssistant\Migration\Connection\SwagMigrationConnectionEntity;
use SwagMigrationAssistant\Migration\Gateway\GatewayRegistry;
use SwagMigrationAssistant\Migration\MigrationContext;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\Premapping\PremappingStruct;
use SwagMigrationAssistant\Profile\Shopware\Gateway\Local\ShopwareLocalGateway;
use SwagMigrationAssistant\Profile\Shopware\Premapping\TransactionStateReader;
use SwagMigrationAssistant\Profile\Shopware55\Shopware55Profile;

class TransactionStateReaderTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var MigrationContextInterface
     */
    private $migrationContext;

    /**
     * @var TransactionStateReader
     */
    private $reader;

    /**
     * @var Context
     */
    private $context;

    public function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $smRepoMock = $this->createMock(EntityRepository::class);
        $stateMachine = new StateMachineEntity();
        $stateMachine->setId(Uuid::randomHex());
        $stateMachine->setName('Payment state');
        $smRepoMock->method('search')->willReturn(new EntitySearchResult(1, new EntityCollection([$stateMachine]), null, new Criteria(), $this->context));

        $smsRepoMock = $this->createMock(EntityRepository::class);
        $stateOpen = new StateMachineStateEntity();
        $stateOpen->setId(Uuid::randomHex());
        $stateOpen->setName('Open');
        $stateOpen->setTechnicalName('open');

        $stateClosed = new StateMachineStateEntity();
        $stateClosed->setId(Uuid::randomHex());
        $stateClosed->setName('Paid');
        $stateClosed->setTechnicalName('paid');

        $smsRepoMock->method('search')->willReturn(new EntitySearchResult(2, new EntityCollection([$stateOpen, $stateClosed]), null, new Criteria(), $this->context));

        $connection = new SwagMigrationConnectionEntity();
        $connection->setId(Uuid::randomHex());
        $connection->setProfileName(Shopware55Profile::PROFILE_NAME);
        $connection->setGatewayName(ShopwareLocalGateway::GATEWAY_NAME);
        $connection->setCredentialFields([]);
        $connection->setPremapping([]);

        $this->migrationContext = new MigrationContext(
            new Shopware55Profile(),
            $connection
        );

        $gatewayMock = $this->createMock(ShopwareLocalGateway::class);
        $gatewayMock->method('readTable')->willReturn([
            ['id' => '1', 'name' => 'open', 'description' => 'Open', 'group' => 'payment', 'mail' => 0],
            ['id' => '2', 'name' => 'completely_paid', 'description' => 'Completely paid', 'group' => 'payment', 'mail' => 0],
            ['id' => '3', 'name' => 'no_description', 'description' => '', 'group' => 'payment', 'mail' => 0],
        ]);

        $gatewayRegistryMock = $this->createMock(GatewayRegistry::class);
        $gatewayRegistryMock->method('getGateway')->willReturn($gatewayMock);

        $this->reader = new TransactionStateReader($smRepoMock, $smsRepoMock, $gatewayRegistryMock);
    }

    public function testGetPremapping(): void
    {
        $result = $this->reader->getPremapping($this->context, $this->migrationContext);

        static::assertInstanceOf(PremappingStruct::class, $result);
        static::assertCount(3, $result->getMapping());
        static::assertCount(2, $result->getChoices());

        $choices = $result->getChoices();
        static::assertSame('Paid', $choices[1]->getDescription());
    }

    public function testPremappingChoicesWithEmptyDescription(): void
    {
        $result = $this->reader->getPremapping($this->context, $this->migrationContext);

        static::assertInstanceOf(PremappingStruct::class, $result);
        $mapping = $result->getMapping();
        static::assertSame('no_description', $mapping[2]->getDescription());
    }
}
