<?php

namespace CommonGateway\NaamgebruikVrijBRPBundle\Service;

use App\Entity\Entity;
use App\Entity\Mapping;
use App\Entity\ObjectEntity;
use CommonGateway\CoreBundle\Service\CacheService;
use CommonGateway\CoreBundle\Service\MappingService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

/**
 *  This class handles the interaction with componentencatalogus.commonground.nl.
 */
class SimXmlToZgwService
{
    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var MappingService
     */
    private MappingService $mappingService;

    /**
     * @var SymfonyStyle
     */
    private SymfonyStyle $io;

    /**
     * @var CacheService
     */
    private CacheService $cacheService;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var array
     */
    private array $configuration;

    /**
     * @param EntityManagerInterface $entityManager  The Entity Manager
     * @param MappingService         $mappingService The MappingService
     * @param CacheService           $cacheService   The CacheService
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        MappingService $mappingService,
        CacheService $cacheService,
        LoggerInterface $actionLogger
    ) {
        $this->entityManager = $entityManager;
        $this->mappingService = $mappingService;
        $this->cacheService = $cacheService;
        $this->logger = $actionLogger;
    }//end __construct()

    /**
     * Set symfony style in order to output to the console.
     *
     * @param SymfonyStyle $io
     *
     * @return self
     */
    public function setStyle(SymfonyStyle $io): self
    {
        $this->io = $io;
        $this->mappingService->setStyle($io);

        return $this;
    }//end setStyle()

    /**
     * Receives a document and maps it to a ZGW EnkelvoudigInformatieObject.
     *
     * @param array $data   The inbound data for the case
     * @param array $config The configuration for the action
     *
     * @return array
     */
    public function geheimhoudingActionHandler(array $data, array $config): array
    {
        $this->logger->info('Populating geheimhouding');
        $this->configuration = $config;

        var_dump('test geheimhouding');

        return $data;
    }//end documentActionHandler()
}
