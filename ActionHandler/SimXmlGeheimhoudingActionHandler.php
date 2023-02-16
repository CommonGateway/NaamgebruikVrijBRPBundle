<?php

namespace CommonGateway\NaamgebruikVrijBRPBundle\ActionHandler;

use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use CommonGateway\NaamgebruikVrijBRPBundle\Service\SimXmlToZgwService;

/**
 * Haalt applications op van de componenten catalogus.
 */
class SimXmlGeheimhoudingActionHandler implements ActionHandlerInterface
{
    /**
     * @var SimXmlToZgwService
     */
    private SimXmlToZgwService $simXmlToZgwService;

    /**
     * @param SimXmlToZgwService $simXmlToZgwService The SimXmlToZgwService
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
            '$id'         => 'https://simxml.nl/simxml.creergeheimhouding.handler.json',
            '$schema'     => 'https://json-schema.org/draft/2020-12/schema',
            'title'       => 'SimXmlGeheimhoudingActionHandler',
            'description' => 'This is a action to map sim xml to zgw zaak geheimhouding.',
        ];
    }//end getConfiguration()

    /**
     * This function runs the application to gateway service plugin.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        return $this->simXmlToZgwService->geheimhoudingActionHandler($data, $configuration);
    }//end run()
}
