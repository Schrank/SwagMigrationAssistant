<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\Migration;

use Shopware\Core\Framework\Struct\Struct;
use SwagMigrationAssistant\Migration\Connection\SwagMigrationConnectionEntity;
use SwagMigrationAssistant\Migration\DataSelection\DataSet\DataSet;
use SwagMigrationAssistant\Migration\Gateway\GatewayInterface;
use SwagMigrationAssistant\Migration\Profile\ProfileInterface;

class MigrationContext extends Struct implements MigrationContextInterface
{
    public const SOURCE_CONTEXT = 'MIGRATION_CONNECTION_CHECK_FOR_RUNNING_MIGRATION';

    /**
     * @var ProfileInterface
     */
    private $profile;

    /**
     * @var SwagMigrationConnectionEntity|null
     */
    private $connection;

    /**
     * @var string
     */
    private $runUuid;

    /**
     * @var DataSet|null
     */
    private $dataSet;

    /**
     * @var int
     */
    private $offset;

    /**
     * @var int
     */
    private $limit;

    /**
     * @var GatewayInterface
     */
    private $gateway;

    public function __construct(
        ProfileInterface $profile,
        ?SwagMigrationConnectionEntity $connection = null,
        string $runUuid = '',
        ?DataSet $dataSet = null,
        int $offset = 0,
        int $limit = 0
    ) {
        $this->profile = $profile;
        $this->connection = $connection;
        $this->runUuid = $runUuid;
        $this->dataSet = $dataSet;
        $this->offset = $offset;
        $this->limit = $limit;
    }

    public function getProfile(): ProfileInterface
    {
        return $this->profile;
    }

    public function getConnection(): ?SwagMigrationConnectionEntity
    {
        return $this->connection;
    }

    public function getRunUuid(): string
    {
        return $this->runUuid;
    }

    public function getDataSet(): ?DataSet
    {
        return $this->dataSet;
    }

    public function setDataSet(DataSet $dataSet): void
    {
        $this->dataSet = $dataSet;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getGateway(): GatewayInterface
    {
        return $this->gateway;
    }

    public function setGateway(GatewayInterface $gateway): void
    {
        $this->gateway = $gateway;
    }
}
