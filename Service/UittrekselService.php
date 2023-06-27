<?php

namespace CommonGateway\NaamgebruikVrijBRPBundle\Service;

use App\Entity\ObjectEntity;
use CommonGateway\CoreBundle\Service\MappingService;
use App\Service\ObjectEntityService;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * This Service handles the mapping of a zgw zaak for a uittreksel to xml soap uittreksel
 *
 * @author Barry Brands <barry@conduction.nl>
 */
class UittrekselService
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
    private ObjectEntityService $objectEntityService;

    /**
     * @var array Action data (ZGW Zaak for Uittreksel)
     */
    private array $data;

    /**
     * @var array Action configuration
     */
    private array $configuration;

    /**
     * Construct a UittrekselService.
     *
     * @param ZgwToVrijbrpService $zgwToVrijbrpService ZgwToVrijbrpService.
     */
    public function __construct(
        MappingService $mappingService,
        ZgwToVrijbrpService $zgwToVrijbrpService,
        LoggerInterface $actionLogger,
        EntityManagerInterface $entityManager,
        ObjectEntityService $objectEntityService
    ) {
        $this->mappingService = $mappingService;
        $this->zgwToVrijbrpService = $zgwToVrijbrpService;
        $this->logger = $actionLogger;
        $this->entityManager = $entityManager;
        $this->objectEntityService = $objectEntityService;
    } //end __construct()

    /**
     * This function gets the mee emigranten from the zgwZaak with the given properties (simXml elementen and Stuf extraElementen).
     *
     * @param array $zaakEigenschappen The zaak eigenschappen.
     *
     * @return array uittrekselBetrokkene
     */
    public function getUittrekselBetrokkene(array $zaakEigenschappen): array
    {
        if(isset($zaakEigenschappen["UITTREKSELS.UITTREKSEL.BSN"])) {
            $uittrekselBetrokkene = [
                'uit:UittrekselBetrokkene' => [
                    'uit:Burgerservicenummer' => $zaakEigenschappen["UITTREKSELS.UITTREKSEL.BSN"],
                    'uit:Uittrekselcode' => $zaakEigenschappen["UITTREKSELS.UITTREKSEL.CODE"],
                    'uit:IndicatieGratis' => 'false'
                ]
            ];

            return $uittrekselBetrokkene;
        }

        $uittrekselBetrokkene = [];
        $index = 1;

        while (isset($zaakEigenschappen["UITTREKSELS.UITTREKSEL.$index.BSN"])) {
            $uittrekselBetrokkene[] = [
                'uit:UittrekselBetrokkene' => [
                    'uit:Burgerservicenummer' => $zaakEigenschappen["UITTREKSELS.UITTREKSEL.$index.BSN"],
                    'uit:Uittrekselcode' => $zaakEigenschappen["UITTREKSELS.UITTREKSEL.$index.CODE"],
                    'uit:IndicatieGratis' => 'false'
                ]
            ];
            $index++;
        }// end while

        return $uittrekselBetrokkene;
    } //end getUittrekselBetrokkene()

    /**
     * Maps zgw eigenschappen to vrijbrp soap uittreksel.
     *
     * @param ObjectEntity $object The zgw case ObjectEntity.
     * @param array $output The output data
     *
     * @return array
     */
    public function getUittrekselProperties(ObjectEntity $object, array $output): array
    {
        $this->logger->info('Do additional mapping with case properties');

        $properties = ['all'];
        $zaakEigenschappen = $this->zgwToVrijbrpService->getZaakEigenschappen($object, $properties);
        $bsn = $this->zgwToVrijbrpService->getBsnFromRollen($object);
        $output['soapenv:Body']['dien:AanvraagRequest']['dien:UittrekselaanvraagRequest']['uit:Aanvraaggegevens']['uit:BurgerservicenummerAanvrager'] = $bsn;
        $output['soapenv:Body']['dien:AanvraagRequest']['dien:UittrekselaanvraagRequest']['uit:Aanvraaggegevens']['uit:UittrekselBetrokkenen'] = $this->getUittrekselBetrokkene($zaakEigenschappen);
        $output['soapenv:Body']['dien:AanvraagRequest']['dien:UittrekselaanvraagRequest']['uit:Contactgegevens'] = [
            'com:Emailadres' => $zaakEigenschappen['EMAILADRES'],
            'com:TelefoonnummerPrive' => $zaakEigenschappen['TELEFOONNUMMER']
        ];

        $this->logger->info('Done with additional mapping');

        return $output;
    }//end getUittrekselProperties()

    
    /**
     * Main function which maps and posts the xml soap uittreksel.
     * 
     * @var array $data          The ZGW Zaak for a uittreksel.
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

        $objectArray = $this->objectEntityService->toArray($object);

        // Do mapping with Zaak ObjectEntity as array.
        $objectArray = $this->mappingService->mapping($mapping, $objectArray);

        $foundBody = false;
        $objectArray = $this->getUittrekselProperties($object, $objectArray, $foundBody);

        // Create synchronization.
        $synchronization = $this->zgwToVrijbrpService->getSynchronization($object, $source, $synchronizationEntity, $mapping);

        $this->logger->debug("Synchronize (Zaak) Object to: {$source->getLocation()}");
        // Todo: change synchronize function so it can also push to a source and not only pull from a source:
        // $this->syncService->synchronize($synchronization, $objectArray);

        // Todo: temp way of doing this without updated synchronize() function...
        if ($this->zgwToVrijbrpService->synchronizeTemp($synchronization, $objectArray, '')) {
            // Return empty array on error for when we got here through a command.
            return [];
        }// end if

        return $data;
    }//end zgwToVrijbrpHandler()
}
