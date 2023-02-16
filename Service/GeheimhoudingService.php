<?php

namespace CommonGateway\NaamgebruikVrijBRPBundle\Service;

use App\Entity\ObjectEntity;
use CommonGateway\NaamgebruikVrijBRPBundle\Service\ZgwToVrijbrpService;
use Psr\Log\LoggerInterface;

/**
 * This Service handles the mapping of the geheimhouding
 *
 * @author Barry Brands <barry@conduction.nl>
 */
class GeheimhoudingService
{
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $mappingLogger;

    /**
     * @var ZgwToVrijbrpService SymfonyStyle for writing user feedback to console.
     */
    private ZgwToVrijbrpService $zgwToVrijbrpService;

    /**
     * Construct a GeheimhoudingService.
     *
     * @param ZgwToVrijbrpService $zgwToVrijbrpService ZgwToVrijbrpService.
     */
    public function __construct(
        ZgwToVrijbrpService $zgwToVrijbrpService,
        LoggerInterface $mappingLogger
    ) {
        $this->zgwToVrijbrpService = $zgwToVrijbrpService;
        $this->mappingLogger = $mappingLogger;
    }//end __construct()

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
        $this->mappingLogger->info('Do additional mapping with case properties');

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

        $this->mappingLogger->info('Done with additional mapping');

        return $output;
    }//end getGeheimhoudingProperties()
}
