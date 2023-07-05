<?php

namespace CommonGateway\NaamgebruikVrijBRPBundle\Service;

use Doctrine\ORM\EntityManagerInterface;

class CleanUpService
{
    private EntityManagerInterface $entityManager;

    /**
     * @param EntityManagerInterface $entityManager The entity manager.
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Removes objects of a specified schema that are older than a specified retention period.
     *
     * @param array $data   The data for the action.
     * @param array $config The configuration for the action.
     *
     * @return array The returned data array
     *
     * @throws \Exception
     */
    public function cleanUp (array $data, array $config): array
    {
        $schemaRef = $config['objectType'];
        $schema = $this->entityManager->getRepository('App:Entity')->findBy(['reference' => $schemaRef]);

        foreach ($schema->getObjects() as $object) {
            $retention = new \DateInterval($config['retentionPeriod']);
            $now       = new \DateTime();

            //if the dateCreated of the object plus the retention is smaller than current time, remove
            if($object->getDateCreated()->add($retention) < $now) {
                $this->entityManager->remove($object);
            }
        }

        return $data;
    }
}