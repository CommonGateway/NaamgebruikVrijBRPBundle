<?php

namespace CommonGateway\NaamgebruikVrijBRPBundle\Service;

use Doctrine\ORM\EntityManagerInterface;

class CleanUpService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }
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