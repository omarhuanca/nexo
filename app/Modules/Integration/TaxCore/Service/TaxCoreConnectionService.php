<?php

namespace App\Modules\Integration\TaxCore\Service;

use App\Events\TaxCore\TaxCoreCertUploaded;
use App\Modules\Integration\TaxCore\Domain\TaxCoreConnection;
use App\Modules\Integration\TaxCore\Repository\TaxCoreConnectionRepository;
use Illuminate\Http\UploadedFile;

class TaxCoreConnectionService
{
    public function __construct(
        private readonly TaxCoreConnectionRepository $repository,
        private readonly TaxCoreEncryptionService $encryptionService,
        private readonly TaxCoreCertificateService $certificateService,
    ) {}

    public function connect(int $organizationId, UploadedFile $certificate, string $password, string $pac, string $environment): TaxCoreConnection {
        $binary = file_get_contents($certificate->getRealPath());

        $connection = new TaxCoreConnection();
        $connection->setOrganizationId($organizationId);
        $connection->setCertificateEncrypted($this->encryptionService->encodeCertificate($binary));
        $connection->setCertificatePasswordEncrypted($this->encryptionService->encodePassword($password));
        $connection->setPacEncrypted($this->encryptionService->encodePac($pac));
        $connection->setEnvironment($environment);
        $connection->setActive(true);

        $this->certificateService->assertNotExpired($connection);
        $vsdcUrl = $this->certificateService->extractVsdcUrl($connection);
        $connection->setVsdcUrl($vsdcUrl);

        $this->repository->save($connection);

        event(new TaxCoreCertUploaded(
            $organizationId,
            $connection->getId(),
            $environment,
            $vsdcUrl,
        ));

        return $connection;
    }

    public function findActiveByOrganization(int $organizationId): TaxCoreConnection
    {
        return $this->repository->findActiveByOrganizationId($organizationId);
    }
}