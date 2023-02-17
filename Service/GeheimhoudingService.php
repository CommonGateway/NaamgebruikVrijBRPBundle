<?php

namespace CommonGateway\NaamgebruikVrijBRPBundle\Service;

use App\Entity\ObjectEntity;
use CommonGateway\NaamgebruikVrijBRPBundle\Service\ZgwToVrijbrpService;
use CommonGateway\CoreBundle\Service\MappingService;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * This Service handles the mapping of a zgw zaak for a geheimhouding to xml soap geheimhouding
 *
 * @author Barry Brands <barry@conduction.nl>
 */
class GeheimhoudingService
{
    /**
     * @var MappingService
     */
    private MappingService $mappingService;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var ZgwToVrijbrpService
     */
    private ZgwToVrijbrpService $zgwToVrijbrpService;

    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var array Action data (ZGW Zaak for Geheimhouding)
     */
    private array $data;

    /**
     * @var array Action configuration
     */
    private array $configuration;

    /**
     * Construct a GeheimhoudingService.
     *
     * @param ZgwToVrijbrpService $zgwToVrijbrpService ZgwToVrijbrpService.
     */
    public function __construct(
        MappingService $mappingService,
        ZgwToVrijbrpService $zgwToVrijbrpService,
        LoggerInterface $actionLogger,
        EntityManagerInterface $entityManager
    ) {
        $this->mappingService = $mappingService;
        $this->zgwToVrijbrpService = $zgwToVrijbrpService;
        $this->logger = $actionLogger;
        $this->entityManager = $entityManager;
    } //end __construct()

    /**
     * Maps zgw eigenschappen to vrijbrp soap geheimhouding.
     *
     * @param ObjectEntity $object The zgw case ObjectEntity.
     * @param array $output The output data
     *
     * @return array
     */
    public function getGeheimhoudingProperties(ObjectEntity $object, array $output): array
    {
        $this->logger->info('Do additional mapping with case properties');

        $properties = ['CODE_GEHEIMHOUDING', 'BSN_GEHEIMHOUDING', 'EMAILADRES', 'TELEFOONNUMMER'];
        $zaakEigenschappen = $this->zgwToVrijbrpService->getZaakEigenschappen($object, $properties);
        $bsn = $this->zgwToVrijbrpService->getBsnFromRollen($object);
        $output['soapenv:Body']['dien:GeheimhoudingaanvraagRequest']['geh:Aanvraaggegevens']['geh:BurgerservicenummerAanvrager'] = $bsn;
        $output['soapenv:Body']['dien:GeheimhoudingaanvraagRequest']['geh:Aanvraaggegevens']['geh:GeheimhoudingBetrokkenen'] = [
            'geh:GeheimhoudingBetrokkene' => [
                'geh:Burgerservicenummer' => $zaakEigenschappen['BSN_GEHEIMHOUDING'],
                'geh:CodeGeheimhouding' => $zaakEigenschappen['CODE_GEHEIMHOUDING'],
            ]
        ];
        $output['soapenv:Body']['dien:GeheimhoudingaanvraagRequest']['geh:Contactgegevens'] = [
            'com:Emailadres' => $zaakEigenschappen['EMAILADRES'],
            'com:TelefoonnummerPrive' => $zaakEigenschappen['TELEFOONNUMMER']
        ];

        $this->logger->info('Done with additional mapping');

        return $output;
    }//end getGeheimhoudingProperties()

    
    /**
     * Main function which maps and posts the xml soap geheimhouding.
     * 
     * @var array $data          The ZGW Zaak for a geheimhouding.
     * @var array $configuration The action configuration.
     *  
     * @return array $data Standard returns data the function was entered with.
     */
    public function zgwToVrijbrpHandler(array $data, array $configuration): array
    {
        $this->logger->info('Converting ZGW object to VrijBRP');
        $this->configuration = $configuration;
        $this->data = $data;

        $source = $this->zgwToVrijbrpService->getSource($configuration['source']);
        $mapping = $this->zgwToVrijbrpService->getMapping($configuration['mapping']);
        $synchronizationEntity = $this->zgwToVrijbrpService->getEntity($configuration['synchronizationEntity']);
        if ($source === null
            || $mapping === null
            || $synchronizationEntity === null
        ) {
            return [];
        }// end if

        $dataId = $data['object']['_self']['id'];


        $object = $this->entityManager->getRepository('App:ObjectEntity')->find($dataId);
        $this->logger->debug("(Zaak) Object with id $dataId was created");

        $this->logger->debug("Object to array");
        $objectArray = $object->toArray();

        // Do mapping with Zaak ObjectEntity as array.
        $this->logger->debug("Mapping zaak to xml soap");
        $objectArray = $this->mappingService->mapping($mapping, $objectArray);

        $foundBody = false;
        $this->logger->debug("Manually mapping some properties");
        $objectArray = $this->getGeheimhoudingProperties($object, $objectArray, $foundBody);

        // Create synchronization.
        $this->logger->debug("Getting synchronization");
        $synchronization = $this->zgwToVrijbrpService->getSynchronization($object, $source, $synchronizationEntity, $mapping);

        $this->logger->debug("Synchronize (Zaak) Object to: {$source->getLocation()}");
        // Todo: change synchronize function so it can also push to a source and not only pull from a source:
        // $this->syncService->synchronize($synchronization, $objectArray);

        // Todo: temp way of doing this without updated synchronize() function...
        if ($this->zgwToVrijbrpService->synchronizeTemp($synchronization, $objectArray, $source->getLocation())) {
            // Return empty array on error for when we got here through a command.
            return [];
        }// end if

        return $data;
    }//end zgwToVrijbrpHandler()
}
