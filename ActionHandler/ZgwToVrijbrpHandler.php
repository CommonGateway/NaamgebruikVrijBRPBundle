<?php

namespace CommonGateway\NaamgebruikVrijBRPBundle\ActionHandler;

use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use CommonGateway\NaamgebruikVrijBRPBundle\Service\ZgwToVrijbrpService;
use Exception;

/**
 * This ActionHandler handles the mapping and sending of ZGW zaak data to the Vrijbrp api with a corresponding Service.
 * Should be the same as GeboorteVrijBRPBundle->ZgwToVrijbrpHandler
 *
 * @author Wilco Louwerse <wilco@conduction.nl>
 */
class ZgwToVrijbrpHandler implements ActionHandlerInterface
{
    /**
     * @var ZgwToVrijbrpService The ZgwToVrijbrpService that will handle code for this Handler.
     */
    private ZgwToVrijbrpService $zgwToVrijbrpService;

    /**
     * Construct a ZgwToVrijbrpHandler.
     *
     * @param ZgwToVrijbrpService $zgwToVrijbrpService The ZgwToVrijbrpService that will handle code for this Handler.
     */
    public function __construct(ZgwToVrijbrpService $zgwToVrijbrpService)
    {
        $this->zgwToVrijbrpService = $zgwToVrijbrpService;
    }//end __construct()

    /**
     * This function returns the requered configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @return array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'         => 'https://vrijbrp.nl/vrijbrp.zaak.handler.json',
            '$schema'     => 'https://json-schema.org/draft/2020-12/schema',
            'title'       => 'ZgwToVrijbrpHandler',
            'description' => 'This handler posts zaak eigenschappen from ZGW to VrijBrp',
            'required'    => ['source', 'mapping', 'zaakEntity'],
            'properties'  => [
                'source' => [
                    'type'        => 'string',
                    'description' => 'The reference of the Source we will send a request to, reference of an existing Source object',
                    'example'     => 'https://vrijbrp.nl/source/vrijbrp.soap.source.json',
                    'required'    => true,
                    '$ref'        => 'https://commongroundgateway.nl/commongroundgateway.gateway.entity.json',
                ],
                'mapping' => [
                    'type'        => 'string',
                    'description' => 'The reference of the mapping we will use before sending the data to the source',
                    'example'     => 'https://vrijbrp.nl/mapping/vrijbrp.ZgwToVrijbrp.mapping.json',
                    'required'    => true,
                    '$ref'        => 'https://commongroundgateway.nl/commongroundgateway.mapping.entity.json',
                ],
                'synchronizationEntity' => [
                    'type'        => 'string',
                    'description' => 'The reference of the entity we use as trigger for this handler, we need this to find a synchronization object',
                    'example'     => 'https://vng.opencatalogi.nl/schemas/zrc.zaak.schema.json',
                    'required'    => true,
                    '$ref'        => 'https://commongroundgateway.nl/commongroundgateway.entity.entity.json',
                ],
            ],
        ];
    }//end getConfiguration()
    
    /**
     * This function will call the handler function to the corresponding service of this Handler.
     *
     * @param array $data The data from the call
     * @param array $configuration The configuration from the call
     *
     * @return array
     * @throws Exception
     */
    public function run(array $data, array $configuration): array
    {
        return $this->zgwToVrijbrpService->zgwToVrijbrpHandler($data, $configuration);
    }//end run()
}
