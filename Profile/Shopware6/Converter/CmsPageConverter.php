<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\Profile\Shopware6\Converter;

use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;

abstract class CmsPageConverter extends ShopwareConverter
{
    protected function convertData(array $data): ConvertStruct
    {
        $converted = $data;

        // handle locked default layouts
        if (isset($converted['locked']) && $converted['locked'] === true) {
            $this->mappingService->mapLockedCmsPageUuidByNameAndType(
                \array_column($converted['translations'], 'name'),
                $converted['type'],
                $data['id'],
                $this->connectionId,
                $this->migrationContext,
                $this->context
            );

            return new ConvertStruct(null, $data);
        }

        if (isset($converted['translations'])) {
            $this->updateAssociationIds(
                $converted['translations'],
                DefaultEntities::LANGUAGE,
                'languageId',
                DefaultEntities::CMS_PAGE
            );

            $converted['id'] = $this->mappingService->getCmsPageUuidByNames(\array_column($converted['translations'], 'name'), $data['id'], $this->connectionId, $this->migrationContext, $this->context);
        }

        $this->mainMapping = $this->getOrCreateMappingMainCompleteFacade(
            DefaultEntities::CMS_PAGE,
            $data['id'],
            $converted['id']
        );

        if (isset($data['previewMediaId'])) {
            $converted['previewMediaId'] = $this->getMappingIdFacade(DefaultEntities::MEDIA, $data['previewMediaId']);
        }

        if (isset($converted['sections'])) {
            $this->processSubentities($converted['sections']);
        }

        if (isset($data['categories'])) {
            $this->updateAssociationIds(
                $converted['categories'],
                DefaultEntities::CATEGORY,
                'id',
                DefaultEntities::CMS_PAGE
            );
        }

        return new ConvertStruct($converted, null, $this->mainMapping['id'] ?? null);
    }

    protected function processSubentities(array &$sections): void
    {
        foreach ($sections as &$section) {
            if (isset($section['blocks'])) {
                foreach ($section['blocks'] as &$block) {
                    if (isset($block['slots'])) {
                        foreach ($block['slots'] as &$slot) {
                            if (isset($slot['translations'])) {
                                $this->updateAssociationIds(
                                    $slot['translations'],
                                    DefaultEntities::LANGUAGE,
                                    'languageId',
                                    DefaultEntities::CMS_PAGE
                                );
                            }

                            if (isset($slot['backgroundMediaId'])) {
                                $slot['backgroundMediaId'] = $this->getMappingIdFacade(DefaultEntities::MEDIA, $slot['backgroundMediaId']);
                            }
                        }
                        unset($slot);
                    }

                    if (isset($block['backgroundMediaId'])) {
                        $block['backgroundMediaId'] = $this->getMappingIdFacade(DefaultEntities::MEDIA, $block['backgroundMediaId']);
                    }
                }
                unset($block);
            }

            if (isset($section['backgroundMediaId'])) {
                $section['backgroundMediaId'] = $this->getMappingIdFacade(DefaultEntities::MEDIA, $section['backgroundMediaId']);
            }
        }
    }
}
