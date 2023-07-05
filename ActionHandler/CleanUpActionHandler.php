<?php

namespace CommonGateway\NaamgebruikVrijBRPBundle\ActionHandler;

use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use CommonGateway\NaamgebruikVrijBRPBundle\Service\CleanUpService;

/**
 * Haalt applications op van de componenten catalogus.
 */
class CleanUpActionHandler implements ActionHandlerInterface
{
    /**
     * @var CleanUpService
     */
    private CleanUpService $cleanUpService;

    /**
     * @param CleanUpService $cleanUpService The ZdsToZgwService
     */
    public function __construct(CleanUpService $cleanUpService)
    {
        $this->cleanUpService = $cleanUpService;
    }//end __construct()

    /**
     *  This function returns the required configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'        => 'https://commongateway.nl/clearSchema.actionHandler.json',
            '$schema'    => 'https://json-schema.org/draft/2020-12/schema',
            'title'      => 'ClearSchemaHandler',
            'description'=> 'This is a action to create objects from the fetched applications from the componenten catalogus.',
            'properties' => [
                'source' => [
                    'type'        => 'objectType',
                    'description' => 'The reference of the schema we want to clear.',
                    'example'     => 'https://vng.opencatalogi.nl/schemas/zrc.zaak.schema.json',
                    'required'    => true,
                    '$ref'        => 'https://commongroundgateway.nl/commongroundgateway.gateway.entity.json',
                ],
                'mapping' => [
                    'type'        => 'retentionPeriod',
                    'description' => 'The period for which the object type should be retained.',
                    'example'     => 'PT1H',
                    'required'    => true,
                ]
            ]
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
        return $this->cleanUpService->cleanUp($data, $configuration);
    }//end run()
}
