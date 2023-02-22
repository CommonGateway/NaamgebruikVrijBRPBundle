<?php

namespace CommonGateway\NaamgebruikVrijBRPBundle\ActionHandler;

use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use CommonGateway\NaamgebruikVrijBRPBundle\Service\SimXmlToZgwService;
use CommonGateway\NaamgebruikVrijBRPBundle\Service\ZdsToZgwService;

/**
 * Sim xml to a zgw zaak
 */
class SimXmlZaakActionHandler implements ActionHandlerInterface
{
    /**
     * @var SimXmlToZgwService
     */
    private SimXmlToZgwService $simXmlToZgwService;

    /**
     * @param SimXmlToZgwService $simXmlToZgwService The Sim XML to ZGW service
     */
    public function __construct(SimXmlToZgwService $simXmlToZgwService)
    {
        $this->simXmlToZgwService = $simXmlToZgwService;
    }//end __construct()

    /**
     *  This function returns the required configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @return array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'         => 'https://xml.nl/xml.creerzaak.handler.json',
            '$schema'     => 'https://json-schema.org/draft/2020-12/schema',
            'title'       => 'SimXmlZaakActionHandler',
            'description' => 'This is a action ...',
        ];
    }//end getConfiguration()

    /**
     * This function runs ...
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        return $this->simXmlToZgwService->zaakActionHandler($data, $configuration);
    }//end run()
}
