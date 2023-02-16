<?php

namespace CommonGateway\NaamgebruikVrijBRPBundle\Service;

use Adbar\Dot;
use App\Entity\Entity;
use App\Entity\Gateway as Source;
use App\Entity\Mapping;
use App\Entity\ObjectEntity;
use App\Entity\Synchronization;
use App\Service\SynchronizationService;
use CommonGateway\CoreBundle\Service\CallService;
use CommonGateway\CoreBundle\Service\MappingService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use CommonGateway\NaamgebruikVrijBRPBundle\Service\GeheimhoudingService;

/**
 * This Service handles the mapping and sending of ZGW zaak data to the Vrijbrp api.
 * todo: I have written this service as abstract as possible (in the little time i had for this) so that we could
 * todo: maybe use this as a basis for creating a new SynchronizationService->push / syncToSource function.
 *
 * @author Wilco Louwerse <wilco@conduction.nl>
 */
class ZgwToVrijbrpService
{
    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var CallService
     */
    private CallService $callService;

    /**
     * @var SynchronizationService
     */
    private SynchronizationService $syncService;

    /**
     * @var MappingService
     */
    private MappingService $mappingService;

    /**
     * @var SymfonyStyle SymfonyStyle for writing user feedback to console.
     */
    private SymfonyStyle $symfonyStyle;

    /**
     * @var array ActionHandler configuration.
     */
    private array $configuration;

    /**
     * @var array Data of the api call.
     */
    private array $data;

    /**
     * @var Source|null The Source we are using for the outgoing call.
     */
    private ?Source $source;

    /**
     * @var Mapping|null The mapping we are using for the outgoing call.
     */
    private ?Mapping $mapping;

    /**
     * @var Entity|null The entity used for creating a Synchronization object. (and also the entity that triggers the ActionHandler).
     */
    private ?Entity $synchronizationEntity;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $mappingLogger;
    /**
     * @var GeheimhoudingService
     */
    private GeheimhoudingService $geheimhoudingService;

    /**
     * Construct a ZgwToVrijbrpService.
     *
     * @param EntityManagerInterface $entityManager  EntityManagerInterface.
     * @param CallService            $callService    CallService.
     * @param SynchronizationService $syncService    SynchronizationService.
     * @param MappingService         $mappingService MappingService.
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        CallService $callService,
        SynchronizationService $syncService,
        MappingService $mappingService,
        LoggerInterface $actionLogger,
        LoggerInterface $mappingLogger,
        GeheimhoudingService $geheimhoudingService
    ) {
        $this->entityManager = $entityManager;
        $this->callService = $callService;
        $this->syncService = $syncService;
        $this->mappingService = $mappingService;
        $this->logger = $actionLogger;
        $this->mappingLogger = $mappingLogger;
        $this->geheimhoudingService = $geheimhoudingService;
    }//end __construct()

    /**
     * Set symfony style in order to output to the console when running the handler function through a command.
     *
     * @param SymfonyStyle $symfonyStyle SymfonyStyle for writing user feedback to console.
     *
     * @return self This.
     */
    public function setStyle(SymfonyStyle $symfonyStyle): self
    {
        $this->symfonyStyle = $symfonyStyle;
        $this->syncService->setStyle($symfonyStyle);
        $this->mappingService->setStyle($symfonyStyle);

        return $this;
    }//end setStyle()

    /**
     * Gets and sets Source object using the required configuration['source'] to find the correct Source.
     *
     * @return Source|null The Source object we found or null if we don't find one.
     */
    private function setSource(): ?Source
    {
        // Todo: Add FromSchema function to Gateway Gateway.php, so that we can use .json files for sources as well.
        // Todo: ...For this to work, we also need to change CoreBundle installationService.
        // Todo: ...If we do this we can also add and use reference for Gateways / Sources.
        $this->source = $this->entityManager->getRepository('App:Gateway')->findOneBy(['location' => $this->configuration['source']]);
        if ($this->source instanceof Source === false) {
            if (isset($this->symfonyStyle) === true) {
                $this->symfonyStyle->error("No source found with location: {$this->configuration['source']}");
            }
            $this->logger->error("No source found with location: {$this->configuration['source']}");

            return null;
        }

        return $this->source;
    }//end setSource()

    /**
     * Gets and sets a Mapping object using the required configuration['mapping'] to find the correct Mapping.
     *
     * @return Mapping|null The Mapping object we found or null if we don't find one.
     */
    private function setMapping(): ?Mapping
    {
        $this->mapping = $this->entityManager->getRepository('App:Mapping')->findOneBy(['reference' => $this->configuration['mapping']]);
        if ($this->mapping instanceof Mapping === false) {
            if (isset($this->symfonyStyle) === true) {
                $this->symfonyStyle->error("No mapping found with reference: {$this->configuration['mapping']}");
            }
            $this->logger->error("No mapping found with reference: {$this->configuration['mapping']}");

            return null;
        }

        return $this->mapping;
    }//end setMapping()

    /**
     * Gets and sets a synchronizationEntity object using the required configuration['synchronizationEntity'] to find the correct Entity.
     *
     * @return Entity|null The synchronizationEntity object we found or null if we don't find one.
     */
    private function setSynchronizationEntity(): ?Entity
    {
        $this->synchronizationEntity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $this->configuration['synchronizationEntity']]);
        if ($this->synchronizationEntity instanceof Entity === false) {
            if (isset($this->symfonyStyle) === true) {
                $this->symfonyStyle->error("No entity found with reference: {$this->configuration['synchronizationEntity']}");
            }
            $this->logger->error("No entity found with reference: {$this->configuration['conditionEntity']}");

            return null;
        }

        return $this->synchronizationEntity;
    }//end setSynchronizationEntity()

    /**
     * Maps zgw eigenschappen to vrijbrp soap naamgebruik.
     *
     * @param ObjectEntity $object The zgw case ObjectEntity.
     * @param array $output The output data
     *
     * @return array
     */
    private function getNaamgebruikProperties(ObjectEntity $object, array $output): array
    {
        $this->mappingLogger->info('Do additional mapping with case properties');

        $properties = ['bsn', 'gemeentecode', 'sub.telefoonnummer', 'sub.emailadres', 'geselecteerdNaamgebruik'];
        $zaakEigenschappen = $this->getZaakEigenschappen($object, $properties);

        $bsn = $this->getBsnFromRollen($object);

        $naamgebruikBetrokkenen['naam:NaamgebruikBetrokkene'] = [
            'naam:Burgerservicenummer' => $bsn,
            'naam:CodeNaamgebruik'     => $zaakEigenschappen['geselecteerdNaamgebruik'],
        ];

        $output['soapenv:Body']['dien:AanvraagRequest']['dien:NaamgebruikaanvraagRequest']['naam:Aanvraaggegevens'] = [
            'naam:BurgerservicenummerAanvrager' => $bsn,
            'naam:NaamgebruikBetrokkenen'       => $naamgebruikBetrokkenen,
        ];
        $output['soapenv:Body']['dien:AanvraagRequest']['dien:NaamgebruikaanvraagRequest']['naam:Contactgegevens'] = $this->getContactgegevens($zaakEigenschappen);

        $this->mappingLogger->info('Done with additional mapping');

        return $output;
    }//end getNaamgebruikProperties()

    /**
     * This function gets the zaakEigenschappen from the zgwZaak with the given properties (simXml elementen and Stuf extraElementen).
     *
     * @param ObjectEntity $zaakObjectEntity The zaak ObjectEntity.
     * @param array        $properties The properties / eigenschappen we want to get.
     *
     * @return array zaakEigenschappen
     */
    public function getZaakEigenschappen(ObjectEntity $zaakObjectEntity, array $properties): array
    {
        $zaakEigenschappen = [];
        foreach ($zaakObjectEntity->getValue('eigenschappen') as $eigenschap) {
            if (in_array($eigenschap->getValue('naam'), $properties)) {
                $zaakEigenschappen[$eigenschap->getValue('naam')] = $eigenschap->getValue('waarde');
            }
        }

        return $zaakEigenschappen;
    }//end getZaakEigenschappen()

    /**
     * This function gets the bsn of the rol with the betrokkeneType set as natuurlijk_persoon.
     *
     * @param ObjectEntity $zaakObjectEntity The zaak ObjectEntity.
     *
     * @return string bsn of the natuurlijk_persoon
     */
    public function getBsnFromRollen(ObjectEntity $zaakObjectEntity): ?string
    {
        foreach ($zaakObjectEntity->getValue('rollen') as $rol) {
            if ($rol->getValue('betrokkeneType') === 'natuurlijk_persoon') {
                $betrokkeneIdentificatie = $rol->getValue('betrokkeneIdentificatie');

                return $betrokkeneIdentificatie->getValue('inpBsn');
            }
        }

        return null;
    }//end getBsnFromRollen()

    /**
     * Creates a VrijRBP Soap Contactgegevens array with the data of the zgwZaak.
     *
     * @param array $zaakEigenschappen zaakEigenschappen.
     *
     * @return array contactgegevens.
     */
    public function getContactgegevens(array $zaakEigenschappen): array
    {
        return [
            'com:Emailadres'           => $zaakEigenschappen['sub.emailadres'],
            'com:TelefoonnummerPrive'  => $zaakEigenschappen['sub.telefoonnummer'],
            'com:TelefoonnummerWerk'   => null,
            'com:TelefoonnummerMobiel' => null,
        ];
    }//end getContactgegevens()

    /**
     * Handles a ZgwToVrijBrp action.
     *
     * @param array $data          The data from the call.
     * @param array $configuration The configuration from the ActionHandler.
     *
     * @throws Exception
     *
     * @return array Data.
     */
    public function zgwToVrijbrpHandler(array $data, array $configuration): array
    {
        $this->logger->info('Converting ZGW object to VrijBRP');
        $this->configuration = $configuration;
        $this->data = $data;
        if ($this->setSource() === null || $this->setMapping() === null || $this->setSynchronizationEntity() === null) {
            return [];
        }

        $dataId = $data['object']['_self']['id'];

        // Get (zaak) object that was created.
        if (isset($this->symfonyStyle) === true) {
            $this->symfonyStyle->comment("(Zaak) Object with id $dataId was created");
        }
        $this->logger->debug("(Zaak) Object with id $dataId was created");

        $object = $this->entityManager->getRepository('App:ObjectEntity')->find($dataId);
        $objectArray = $object->toArray();
        $zaakTypeId = $objectArray['zaaktype']['identificatie'];

        // Do mapping with Zaak ObjectEntity as array.
        $objectArray = $this->mappingService->mapping($this->mapping, $objectArray);

        // todo: make this a function? when merging all Vrijbrp Bundles:
        switch ($zaakTypeId) {
            case 'B0348': // Naamsgebruik
                $objectArray = $this->getNaamgebruikProperties($object, $objectArray);
                break;
            case 'B0328': // Geheimhouding
                $objectArray = $this->geheimhoudingService->getGeheimhoudingProperties($object, $objectArray);
                break;
            default:
                return [];
        }

        // Create synchronization.
        $synchronization = $this->syncService->findSyncByObject($object, $this->source, $this->synchronizationEntity);
        $synchronization->setMapping($this->mapping);
        $location = $this->configuration['location'] ?? '';

        // Send request to source.
        if (isset($this->symfonyStyle) === true) {
            $this->symfonyStyle->comment("Synchronize (Zaak) Object to: {$this->source->getLocation()}$location");
        }
        $this->logger->debug("Synchronize (Zaak) Object to: {$this->source->getLocation()}$location");

        // Todo: change synchronize function so it can also push to a source and not only pull from a source:
        // $this->syncService->synchronize($synchronization, $objectArray);


        // Todo: temp way of doing this without updated synchronize() function...
        if ($this->synchronizeTemp($synchronization, $objectArray) === [] &&
            isset($this->symfonyStyle) === true) {
            // Return empty array on error for when we got here through a command.
            return [];
        }

        return $data;
    }//end zgwToVrijbrpHandler()

    /**
     * Temporary function as replacement of the $this->syncService->synchronize() function.
     * Because currently synchronize function can only pull from a source and not push to a source.
     * // Todo: temp way of doing this without updated synchronize() function...
     *
     * @param Synchronization $synchronization The synchronization we are going to synchronize.
     * @param array           $objectArray     The object data we are going to synchronize.
     *
     * @return array The response body of the outgoing call, or an empty array on error.
     */
    private function synchronizeTemp(Synchronization $synchronization, array $objectArray): array
    {
        $xmlEncoder = new XmlEncoder(['xml_root_node_name' => 'soapenv:Envelope']);
        $objectString = $xmlEncoder->encode($objectArray, 'xml', ['xml_encoding' => 'utf-8', 'remove_empty_tags' => true]);

        $this->logger->info('Sending message with body '.$objectString);
        try {
            $result = $this->callService->call(
                $this->source,
                $this->configuration['location'] ?? '',
                'POST',
                [
                    'body'    => $objectString,
                    //'query'   => [],
                    //'headers' => [],
                ]
            );
        } catch (Exception|GuzzleException $exception) {
            $this->syncService->ioCatchException(
                $exception,
                [
                    'line',
                    'file',
                    'message' => [
                        'preMessage' => 'Error while doing syncToSource in zgwToVrijbrpHandler: ',
                    ],
                ]
            );
            $this->logger->error('Could not synchronize object. Error message: '.$exception->getMessage().'\nFull Response'. (($exception instanceof ServerException||$exception instanceof ClientException||$exception instanceof RequestException === true) && $exception->getResponse() !== null ? $exception->getResponse()->getBody() : ''));

            return [];
        }//end try
        $this->logger->info('Synchronised object, response: '.$result->getBody()->getContents());

        $body = $this->callService->decodeResponse($this->source, $result);

        $bodyDot = new Dot($body);
        $now = new \DateTime();
        $synchronization->setLastSynced($now);
        $synchronization->setSourceLastChanged($now);
        $synchronization->setLastChecked($now);
        $synchronization->setHash(hash('sha384', serialize($bodyDot->jsonSerialize())));

        return $body;
    }//end synchronizeTemp()
}
