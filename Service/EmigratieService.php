<?php

namespace CommonGateway\NaamgebruikVrijBRPBundle\Service;

use App\Entity\ObjectEntity;
use CommonGateway\NaamgebruikVrijBRPBundle\Service\ZgwToVrijbrpService;
use CommonGateway\CoreBundle\Service\MappingService;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * This Service handles the mapping of a zgw zaak for a emigratie to xml soap emigratie
 *
 * @author Barry Brands <barry@conduction.nl>
 */
class EmigratieService
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
     * @var array Action data (ZGW Zaak for Emigratie)
     */
    private array $data;

    /**
     * @var array Action configuration
     */
    private array $configuration;

    /**
     * Construct a EmigratieService.
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
     * This function gets the mee emigranten from the zgwZaak with the given properties (simXml elementen and Stuf extraElementen).
     *
     * @param array $zaakEigenschappen The zaak eigenschappen.
     *
     * @return array meeEmigranten
     */
    public function getMeeEmigranten(array $zaakEigenschappen): array
    {
        if (isset($zaakEigenschappen["MEEVERHUIZENDE_GEZINSLEDEN.MEEVERHUIZEND_GEZINSLID.BSN"])) {
            return [
                'MeeEmigrant' => [
                    'emig:Burgerservicenummer' => $zaakEigenschappen["MEEVERHUIZENDE_GEZINSLEDEN.MEEVERHUIZEND_GEZINSLID.BSN"]
                ]
            ];
        }// end if

        $meeEmigranten = [];
        $index = 1;
        while (isset($zaakEigenschappen["MEEVERHUIZENDE_GEZINSLEDEN.MEEVERHUIZEND_GEZINSLID.$index.BSN"])) {
            $meeEmigranten[] = [
                'MeeEmigrant' => [
                    'emig:Burgerservicenummer' => $zaakEigenschappen["MEEVERHUIZENDE_GEZINSLEDEN.MEEVERHUIZEND_GEZINSLID.$index.BSN"]
                ]
            ];
            $index++;
        }// end while

        return $meeEmigranten;
    } //end getMeeEmigranten()


    /**
     * This function gets the adressen from the zgwZaak with the given eigenschappen (simXml elementen and Stuf extraElementen).
     *
     * @param array  $zaakEigenschappen The zaak eigenschappen.
     *
     * @return array adressen
     */
    public function getAdressen(array $zaakEigenschappen): array
    {
        $adressen = [];
        $index = 1;
        while (isset($zaakEigenschappen["ADRESREGEL$index"])) {
            $adressen["emig:AdresBuitenland$index"] = $zaakEigenschappen["ADRESREGEL$index"];
            $index++;
        }// end while

        return $adressen;
    } //end getAdressen()

    /**
     * Maps zgw eigenschappen to vrijbrp soap emigratie.
     *
     * @param ObjectEntity  $object The zgw case ObjectEntity.
     * @param array $output The output data
     *
     * @return array
     */
    public function getEmigratieProperties(ObjectEntity $object, array $output): array
    {
        $this->logger->info('Do additional mapping with case properties');

        $properties = ['all'];
        $zaakEigenschappen = $this->zgwToVrijbrpService->getZaakEigenschappen($object, $properties);
        $bsn = $this->zgwToVrijbrpService->getBsnFromRollen($object);
        $output['soapenv:Body']['dien:AanvraagRequest']['dien:EmigratieaanvraagRequest']['emig:Aanvraaggegevens']['emig:BurgerservicenummerAanvrager'] = $bsn;
        $output['soapenv:Body']['dien:AanvraagRequest']['dien:EmigratieaanvraagRequest']['emig:Aanvraaggegevens']['emig:Emigratiedatum'] = $zaakEigenschappen['DATUM_VERTREK'];
        $output['soapenv:Body']['dien:AanvraagRequest']['dien:EmigratieaanvraagRequest']['emig:Aanvraaggegevens']['emig:LandcodeEmigratie'] = $zaakEigenschappen['LANDCODE'];
        $output['soapenv:Body']['dien:AanvraagRequest']['dien:EmigratieaanvraagRequest']['emig:Aanvraaggegevens']['emig:AdresBuitenland'] = $this->getAdressen($zaakEigenschappen);
        $output['soapenv:Body']['dien:AanvraagRequest']['dien:EmigratieaanvraagRequest']['emig:Aanvraaggegevens']['emig:MeeEmigranten'] = $this->getMeeEmigranten($zaakEigenschappen);
        $contactGegevens = [
            'com:Emailadres' => $zaakEigenschappen['EMAILADRES']
        ];
        isset($zaakEigenschappen['TELEFOONNUMMER']) && $contactGegevens['com:TelefoonnummerPrive'] = $zaakEigenschappen['TELEFOONNUMMER'];
        $output['soapenv:Body']['dien:AanvraagRequest']['dien:EmigratieaanvraagRequest']['emig:Contactgegevens'] = $contactGegevens;
        $this->logger->info('Done with additional mapping');

        return $output;
    } //end getEmigratieProperties()

    
    /**
     * Main function which maps and posts the xml soap emigratie.
     * 
     * @var array $data          The ZGW Zaak for a emigratie.
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

        $objectArray = $object->toArray();

        // Do mapping with Zaak ObjectEntity as array.
        $objectArray = $this->mappingService->mapping($mapping, $objectArray);

        $foundBody = false;
        $objectArray = $this->getEmigratieProperties($object, $objectArray, $foundBody);

        // Create synchronization.
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
