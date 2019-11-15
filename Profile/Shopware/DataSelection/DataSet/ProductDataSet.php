<?php declare(strict_types=1);

namespace SwagMigrationAssistant\Profile\Shopware\DataSelection\DataSet;

use SwagMigrationAssistant\Migration\DataSelection\DataSet\CountingInformationStruct;
use SwagMigrationAssistant\Migration\DataSelection\DataSet\CountingQueryStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Shopware\ShopwareProfileInterface;

class ProductDataSet extends ShopwareDataSet
{
    public static function getEntity(): string
    {
        return DefaultEntities::PRODUCT;
    }

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $migrationContext->getProfile() instanceof ShopwareProfileInterface;
    }

    public function getCountingInformation(?MigrationContextInterface $migrationContext = null): ?CountingInformationStruct
    {
        $information = new CountingInformationStruct(self::getEntity());
        $information->addQueryStruct(new CountingQueryStruct('s_articles_details'));

        return $information;
    }

    public function getApiRoute(): string
    {
        return 'SwagMigrationProducts';
    }

    public function getExtraQueryParameters(): array
    {
        return [];
    }

    public function getMediaUuids(array $converted): ?array
    {
        $mediaUuids = [];
        foreach ($converted as $data) {
            if (isset($data['media'])) {
                foreach ($data['media'] as $media) {
                    if (!isset($media['media'])) {
                        continue;
                    }

                    $mediaUuids[] = $media['media']['id'];
                }
            }

            if (isset($data['manufacturer']['media']['id'])) {
                $mediaUuids[] = $data['manufacturer']['media']['id'];
            }
        }

        return $mediaUuids;
    }
}
