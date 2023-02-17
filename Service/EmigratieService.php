<?php

namespace CommonGateway\NaamgebruikVrijBRPBundle\Service;

use App\Entity\ObjectEntity;
use CommonGateway\NaamgebruikVrijBRPBundle\Service\ZgwToVrijbrpService;
use Psr\Log\LoggerInterface;

/**
 * This Service handles the mapping of the emigratie
 *
 * @author Barry Brands <barry@conduction.nl>
 */
class EmigratieService
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
     * Construct a EmigratieService.
     *
     * @param ZgwToVrijbrpService $zgwToVrijbrpService ZgwToVrijbrpService.
     */
    public function __construct(
        ZgwToVrijbrpService $zgwToVrijbrpService,
        LoggerInterface $mappingLogger
    ) {
        $this->zgwToVrijbrpService = $zgwToVrijbrpService;
        $this->mappingLogger = $mappingLogger;
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
        $meeEmigranten = [];
        $index = 1;
        while (isset($zaakEigenschappen["MEEVERHUIZENDE_GEZINSLEDEN.MEEVERHUIZEND_GEZINSLID.$index.BSN"])) {
            $meeEmigranten[] = [
                'MeeEmigrant' => [
                    'emig:Burgerservicenummer' => $zaakEigenschappen["MEEVERHUIZENDE_GEZINSLEDEN.MEEVERHUIZEND_GEZINSLID.$index.BSN"]
                ]
            ];
            $index++;
        }

        return $meeEmigranten;
    } //end getMeeEmigranten()


    /**
     * This function gets the adressen from the zgwZaak with the given eigenschappen (simXml elementen and Stuf extraElementen).
     *
     * @param array $zaakEigenschappen The zaak eigenschappen.
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
        }

        return $adressen;
    } //end getAdressen()

    /**
     * Maps zgw eigenschappen to vrijbrp soap emigratie.
     *
     * @param ObjectEntity $object The zgw case ObjectEntity.
     * @param array $output The output data
     *
     * @return array
     */
    public function getEmigratieProperties(ObjectEntity $object, array $output): array
    {
        $this->mappingLogger->info('Do additional mapping with case properties');

        $properties = ['all'];
        $zaakEigenschappen = $this->zgwToVrijbrpService->getZaakEigenschappen($object, $properties);
        $bsn = $this->zgwToVrijbrpService->getBsnFromRollen($object);
        $output['soapenv:Body']['dien:EmigratieaanvraagRequest']['emig:Aanvraaggegevens']['emig:BurgerservicenummerAanvrager'] = $bsn;
        $output['soapenv:Body']['dien:EmigratieaanvraagRequest']['emig:Aanvraaggegevens']['emig:AdresBuitenland'] = $this->getAdressen($zaakEigenschappen);
        $output['soapenv:Body']['dien:EmigratieaanvraagRequest']['emig:Aanvraaggegevens']['emig:MeeEmigranten'] = $this->getMeeEmigranten($zaakEigenschappen);
        $output['soapenv:Body']['dien:EmigratieaanvraagRequest']['emig:Aanvraaggegevens']['emig:LandcodeEmigratie'] = $zaakEigenschappen['LANDCODE'];
        $output['soapenv:Body']['dien:EmigratieaanvraagRequest']['emig:Aanvraaggegevens']['emig:Emigratiedatum'] = $zaakEigenschappen['DATUM_VERTREK'];
        $contactGegevens = [
            'com:Emailadres' => $zaakEigenschappen['EMAILADRES']
        ];
        isset($zaakEigenschappen['TELEFOONNUMMER']) && $contactGegevens['com:TelefoonnummerPrive'] = $zaakEigenschappen['TELEFOONNUMMER'];
        $output['soapenv:Body']['dien:EmigratieaanvraagRequest']['emig:Contactgegevens'] = $contactGegevens;
        $this->mappingLogger->info('Done with additional mapping');

        return $output;
    } //end getGeheimhoudingProperties()
}
