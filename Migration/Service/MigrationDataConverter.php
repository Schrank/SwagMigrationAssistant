<?php declare(strict_types=1);

namespace SwagMigrationAssistant\Migration\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\ShopwareHttpException;
use SwagMigrationAssistant\Migration\Converter\ConverterInterface;
use SwagMigrationAssistant\Migration\Converter\ConverterRegistryInterface;
use SwagMigrationAssistant\Migration\DataSelection\DataSet\DataSet;
use SwagMigrationAssistant\Migration\Logging\Log\ExceptionRunLog;
use SwagMigrationAssistant\Migration\Logging\LoggingServiceInterface;
use SwagMigrationAssistant\Migration\Media\MediaFileServiceInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;

class MigrationDataConverter implements MigrationDataConverterInterface
{
    /**
     * @var EntityWriterInterface
     */
    private $entityWriter;

    /**
     * @var ConverterRegistryInterface
     */
    private $converterRegistry;

    /**
     * @var MediaFileServiceInterface
     */
    private $mediaFileService;

    /**
     * @var LoggingServiceInterface
     */
    private $loggingService;

    /**
     * @var EntityDefinition
     */
    private $dataDefinition;

    public function __construct(
        EntityWriterInterface $entityWriter,
        ConverterRegistryInterface $converterRegistry,
        MediaFileServiceInterface $mediaFileService,
        LoggingServiceInterface $loggingService,
        EntityDefinition $dataDefinition
    ) {
        $this->entityWriter = $entityWriter;
        $this->converterRegistry = $converterRegistry;
        $this->mediaFileService = $mediaFileService;
        $this->loggingService = $loggingService;
        $this->dataDefinition = $dataDefinition;
    }

    public function convert(array $data, MigrationContextInterface $migrationContext, Context $context): void
    {
        try {
            $converter = $this->converterRegistry->getConverter($migrationContext);
            $createData = $this->convertData($context, $data, $converter, $migrationContext, $migrationContext->getDataSet());

            if (\count($createData) === 0) {
                $id = $data['id'] ?? 'unknown';

                throw new \Exception('Data of entity ' . $migrationContext->getDataSet()::getEntity() . ' (' . $id . ') could not be converted');
            }

            $converter->writeMapping($context);
            $this->mediaFileService->writeMediaFile($context);
            $this->loggingService->saveLogging($context);

            $this->entityWriter->upsert(
                $this->dataDefinition,
                $createData,
                WriteContext::createFromContext($context)
            );
        } catch (\Exception $exception) {
            $code = $exception->getCode();
            if (is_subclass_of($exception, ShopwareHttpException::class, false)) {
                $code = $exception->getErrorCode();
            }

            $dataSet = $migrationContext->getDataSet();

            $this->loggingService->addLogEntry(new ExceptionRunLog(
                $migrationContext->getRunUuid(),
                $dataSet::getEntity(),
                $exception
            ));
            $this->loggingService->saveLogging($context);
        }
    }

    private function convertData(
        Context $context,
        array $data,
        ConverterInterface $converter,
        MigrationContextInterface $migrationContext,
        DataSet $dataSet
    ): array {
        $runUuid = $migrationContext->getRunUuid();

        $createData = [];
        foreach ($data as $item) {
            try {
                $convertStruct = $converter->convert($item, $context, $migrationContext);
                $convertFailureFlag = empty($convertStruct->getConverted());

                $createData[] = [
                    'entity' => $dataSet::getEntity(),
                    'runId' => $runUuid,
                    'raw' => $item,
                    'converted' => $convertStruct->getConverted(),
                    'unmapped' => $convertStruct->getUnmapped(),
                    'convertFailure' => $convertFailureFlag,
                ];
            } catch (\Exception $exception) {
                $this->loggingService->addLogEntry(new ExceptionRunLog(
                    $runUuid,
                    $dataSet::getEntity(),
                    $exception,
                    $item['id'] ?? null
                ));

                $createData[] = [
                    'entity' => $dataSet::getEntity(),
                    'runId' => $runUuid,
                    'raw' => $item,
                    'converted' => null,
                    'unmapped' => $item,
                    'convertFailure' => true,
                ];
                continue;
            }
        }

        return $createData;
    }
}