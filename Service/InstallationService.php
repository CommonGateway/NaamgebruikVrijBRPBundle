<?php

namespace CommonGateway\NaamgebruikVrijBRPBundle\Service;

use App\Entity\Action;
use App\Entity\Cronjob;
use App\Entity\DashboardCard;
use App\Entity\Endpoint;
use App\Entity\Gateway as Source;
use CommonGateway\CoreBundle\Installer\InstallerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The installationService for this bundle.
 *
 * @author Wilco Louwerse <wilco@conduction.nl>, Robert Zondervan <robert@conduction.nl>
 */
class InstallationService implements InstallerInterface
{
    /**
     * @var EntityManagerInterface The EntityManagerInterface.
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var ContainerInterface ContainerInterface.
     */
    private ContainerInterface $container;

    /**
     * @var SymfonyStyle SymfonyStyle for writing user feedback to console.
     */
    private SymfonyStyle $symfonyStyle;

    public const ENDPOINTS = [
        ['path' => 'simxml', 'throws' => ['xml.inbound'], 'name' => 'xml-endpoint', 'methods' => []],
    ];

    public const SOURCES = [
        ['name'             => 'vrijbrp-soap', 'location' => 'https://vrijbrp.nl/personen-zaken-ws/services', 'auth' => 'vrijbrp-jwt',
            'username'      => 'sim-!ChangeMe!', 'password' => '!secret-ChangeMe!', 'accept' => 'application/json',
            'configuration' => ['verify' => false], 'reference' => 'https://vrijbrp.nl/source/vrijbrp.soap.source.json'],
    ];

    /**
     * Construct an InstallationService.
     *
     * @param EntityManagerInterface $entityManager EntityManagerInterface.
     * @param ContainerInterface     $container     ContainerInterface.
     */
    public function __construct(EntityManagerInterface $entityManager, ContainerInterface $container)
    {
        $this->entityManager = $entityManager;
        $this->container = $container;
    }//end __construct()

    /**
     * Set symfony style in order to output to the console.
     *
     * @param SymfonyStyle $symfonyStyle SymfonyStyle for writing user feedback to console.
     *
     * @return self This.
     */
    public function setStyle(SymfonyStyle $symfonyStyle): self
    {
        $this->symfonyStyle = $symfonyStyle;

        return $this;
    }

    /**
     * Install for this bundle.
     *
     * @return void Nothing.
     */
    public function install()
    {
        $this->checkDataConsistency();
    }//end install()

    /**
     * Update for this bundle.
     *
     * @return void Nothing.
     */
    public function update()
    {
        $this->checkDataConsistency();
    }//end update()

    /**
     * Uninstall for this bundle.
     *
     * @return void Nothing.
     */
    public function uninstall()
    {
        // Do some cleanup.
    }//end uninstall()

    /**
     * Create endpoints for this bundle.
     *
     * @param array $endpoints An array of data used to create Endpoints.
     *
     * @return array Created endpoints.
     */
    private function createEndpoints(array $endpoints): array
    {
        $endpointRepository = $this->entityManager->getRepository('App:Endpoint');
        $createdEndpoints = [];
        foreach ($endpoints as $endpoint) {
            $explodedPath = explode('/', $endpoint['path']);
            if ($explodedPath[0] === '') {
                array_shift($explodedPath);
            }

            $pathRegEx = '^'.$endpoint['path'].'$';
            if ($endpointRepository->findOneBy(['pathRegex' => $pathRegEx]) instanceof Endpoint === false) {
                $createdEndpoint = new Endpoint();
                $createdEndpoint->setName($endpoint['name']);
                $createdEndpoint->setPath($explodedPath);
                $createdEndpoint->setPathRegex($pathRegEx);
                $createdEndpoint->setMethods(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);
                $createdEndpoint->setThrows($endpoint['throws']);
                $createdEndpoint->getDefaultContentType('text/xml');
                $createdEndpoints[] = $createdEndpoint;

                $this->entityManager->persist($createdEndpoint);
                $this->entityManager->flush();
            }
        }
        if (isset($this->symfonyStyle) === true) {
            $this->symfonyStyle->writeln(count($createdEndpoints).' Endpoints Created');
        }

        return $createdEndpoints;
    }//end createEndpoints()

    /**
     * Create cronjobs for this bundle.
     *
     * @return void Nothing.
     */
    public function createCronjobs()
    {
        if (isset($this->symfonyStyle) === true) {
            $this->symfonyStyle->writeln(['', '<info>Looking for cronjobs</info>']);
        }
        // We only need 1 cronjob so lets set that.
        $cronjob = $this->entityManager->getRepository('App:Cronjob')->findOneBy(['name' => 'VrijBRP']);
        if ($cronjob instanceof Cronjob === false) {
            $cronjob = new Cronjob();
            $cronjob->setName('VrijBRP');
            $cronjob->setDescription('This cronjob fires all the VrijBRP actions ever 5 minutes');
            $cronjob->setThrows(['vrijbrp.default.listens']);
            $cronjob->setIsEnabled(true);

            $this->entityManager->persist($cronjob);

            if (isset($this->symfonyStyle) === true) {
                $this->symfonyStyle->writeln(['', 'Created a cronjob for '.$cronjob->getName()]);
            }
        } elseif (isset($this->symfonyStyle) === true) {
            $this->symfonyStyle->writeln(['', 'There is already a cronjob for '.$cronjob->getName()]);
        }
    }//end createCronjobs()

    /**
     * Creates the Sources we need.
     *
     * @param array $createSources Data for Sources we want to create.
     *
     * @return array The created sources.
     */
    private function createSources(array $createSources): array
    {
        $sourceRepository = $this->entityManager->getRepository('App:Gateway');
        $sources = [];

        foreach ($createSources as $createSource) {
            if ($sourceRepository->findOneBy(['reference' => $createSource['reference']]) instanceof Source === false) {
                $source = new Source($createSource);
                $source->setName($createSource['name']);
                $source->setReference($createSource['reference']);
                if (array_key_exists('password', $createSource) === true) {
                    $source->setPassword($createSource['password']);
                }
    
                $source->setHeaders(['Content-Type' => $createSource['accept']]);

                $this->entityManager->persist($source);
                $this->entityManager->flush();
                $sources[] = $source;
            }
        }

        if (isset($this->symfonyStyle) === true) {
            $this->symfonyStyle->writeln(count($sources).' Sources Created');
        }

        return $sources;
    }//end createSources()

    /**
     * Check if we need to create or update data for this bundle.
     *
     * @return void Nothing.
     */
    public function checkDataConsistency()
    {
        // Create endpoints.
        $this->createEndpoints($this::ENDPOINTS);

        // Create cronjobs.
        $this->createCronjobs();

        // Create sources.
        $this->createSources($this::SOURCES);

        $this->entityManager->flush();
    }//end checkDataConsistency()
}
