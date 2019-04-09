<?php declare(strict_types=1);

namespace SwagMigrationNext\Profile\Shopware55\Converter;

use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionDefinition;
use Shopware\Core\Content\Configuration\ConfigurationGroupDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductConfigurator\ProductConfiguratorDefinition;
use Shopware\Core\Framework\Context;
use SwagMigrationNext\Migration\Converter\AbstractConverter;
use SwagMigrationNext\Migration\Converter\ConvertStruct;
use SwagMigrationNext\Migration\Logging\LoggingServiceInterface;
use SwagMigrationNext\Migration\Mapping\MappingServiceInterface;
use SwagMigrationNext\Migration\Media\MediaFileServiceInterface;
use SwagMigrationNext\Migration\MigrationContextInterface;
use SwagMigrationNext\Profile\Shopware55\Logging\Shopware55LogTypes;
use SwagMigrationNext\Profile\Shopware55\Shopware55Profile;

class ConfigurationGroupOptionConverter extends AbstractConverter
{
    /**
     * @var MappingServiceInterface
     */
    private $mappingService;

    /**
     * @var ConverterHelperService
     */
    private $helper;

    /**
     * @var string
     */
    private $connectionId;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var LoggingServiceInterface
     */
    private $loggingService;

    /**
     * @var string
     */
    private $runId;

    /**
     * @var MediaFileServiceInterface
     */
    private $mediaFileService;

    /**
     * @var string
     */
    private $locale;

    public function __construct(
        MappingServiceInterface $mappingService,
        ConverterHelperService $converterHelperService,
        MediaFileServiceInterface $mediaFileService,
        LoggingServiceInterface $loggingService
    ) {
        $this->mappingService = $mappingService;
        $this->helper = $converterHelperService;
        $this->loggingService = $loggingService;
        $this->mediaFileService = $mediaFileService;
    }

    public function getSupportedEntityName(): string
    {
        return ConfigurationGroupOptionDefinition::getEntityName();
    }

    public function getSupportedProfileName(): string
    {
        return Shopware55Profile::PROFILE_NAME;
    }

    public function writeMapping(Context $context): void
    {
        $this->mappingService->writeMapping($context);
    }

    public function convert(array $data, Context $context, MigrationContextInterface $migrationContext): ConvertStruct
    {
        $this->context = $context;
        $this->locale = $data['_locale'];
        $this->runId = $migrationContext->getRunUuid();
        $this->connectionId = $migrationContext->getConnection()->getId();
        $productContainerUuids = $this->mappingService->getUuidList(
            $this->connectionId,
            'main_product_options',
            $data['id'],
            $context
        );
        $languageData = $this->mappingService->getDefaultLanguageUuid($this->context);
        $defaultLanguageUuid = $languageData['uuid'];

        $converted = [
            'id' => $this->mappingService->createNewUuid(
                $this->connectionId,
                ConfigurationGroupOptionDefinition::getEntityName(),
                $data['id'],
                $context
            ),

            'group' => [
                'id' => $this->mappingService->createNewUuid(
                    $this->connectionId,
                    ConfigurationGroupDefinition::getEntityName(),
                    $data['group']['id'],
                    $context
                ),
            ],
        ];

        if (isset($data['media'])) {
            $this->getMedia($converted, $data);
        }

        $converted['translations'][$defaultLanguageUuid] = [];
        $this->helper->convertValue($converted['translations'][$defaultLanguageUuid], 'name', $data, 'name', $this->helper::TYPE_STRING);
        $this->helper->convertValue($converted['translations'][$defaultLanguageUuid], 'position', $data, 'position', $this->helper::TYPE_INTEGER);

        $converted['group']['translations'][$defaultLanguageUuid] = [];
        $this->helper->convertValue($converted['group']['translations'][$defaultLanguageUuid], 'name', $data['group'], 'name', $this->helper::TYPE_STRING);
        $this->helper->convertValue($converted['group']['translations'][$defaultLanguageUuid], 'description', $data['group'], 'description', $this->helper::TYPE_STRING);

        foreach ($productContainerUuids as $uuid) {
            $this->getDatasheet($converted, $uuid);
            $this->getConfigurator($converted, $data, $uuid);
        }

        return new ConvertStruct($converted, null);
    }

    private function getMedia(array &$converted, array $data): void
    {
        if (!isset($data['media']['id'])) {
            $this->loggingService->addInfo(
                $this->runId,
                Shopware55LogTypes::PRODUCT_MEDIA_NOT_CONVERTED,
                'Configuration-Group-Option-Media could not be converted',
                'Configuration-Group-Option-Media could not be converted.',
                [
                    'uuid' => $converted['id'],
                    'id' => $data['id'],
                ]
            );

            return;
        }

        $newMedia = [];
        $newMedia['id'] = $this->mappingService->createNewUuid(
            $this->connectionId,
            MediaDefinition::getEntityName(),
            $data['media']['id'],
            $this->context
        );

        if (!isset($data['media']['name'])) {
            $data['media']['name'] = $newMedia['id'];
        }

        $this->mediaFileService->saveMediaFile(
            [
                'runId' => $this->runId,
                'uri' => $data['media']['uri'] ?? $data['media']['path'],
                'fileName' => $data['media']['name'],
                'fileSize' => (int) $data['media']['file_size'],
                'mediaId' => $newMedia['id'],
            ]
        );

        $this->getMediaTranslation($newMedia, $data);
        $this->helper->convertValue($newMedia, 'name', $data['media'], 'name');
        $this->helper->convertValue($newMedia, 'description', $data['media'], 'description');

        $converted['media'] = $newMedia;
    }

    // Todo: Check if this is necessary, because name and description is currently not translatable
    private function getMediaTranslation(array &$media, array $data): void
    {
        $languageData = $this->mappingService->getDefaultLanguageUuid($this->context);
        if ($languageData['createData']['localeCode'] === $this->locale) {
            return;
        }

        $localeTranslation = [];

        $this->helper->convertValue($localeTranslation, 'name', $data['media'], 'name');
        $this->helper->convertValue($localeTranslation, 'description', $data['media'], 'description');

        $localeTranslation['id'] = $this->mappingService->createNewUuid(
            $this->connectionId,
            MediaTranslationDefinition::getEntityName(),
            $data['media']['id'] . ':' . $this->locale,
            $this->context
        );

        $languageData = $this->mappingService->getLanguageUuid($this->connectionId, $this->locale, $this->context);
        if (isset($languageData['createData']) && !empty($languageData['createData'])) {
            $localeTranslation['language']['id'] = $languageData['uuid'];
            $localeTranslation['language']['localeId'] = $languageData['createData']['localeId'];
            $localeTranslation['language']['name'] = $languageData['createData']['localeCode'];
        } else {
            $localeTranslation['languageId'] = $languageData['uuid'];
        }

        $media['translations'][$languageData['uuid']] = $localeTranslation;
    }

    private function getConfigurator(array &$converted, array &$data, string $productContainerUuid): void
    {
        $converted['productConfigurators'][] = [
            'id' => $this->mappingService->createNewUuid(
                $this->connectionId,
                ProductConfiguratorDefinition::getEntityName(),
                $data['id'] . '_' . $productContainerUuid,
                $this->context
            ),

            'productId' => $productContainerUuid,
        ];
    }

    private function getDatasheet(array &$converted, string $productContainerUuid): void
    {
        $converted['productDatasheets'][] = [
            'id' => $productContainerUuid,
        ];
    }
}
