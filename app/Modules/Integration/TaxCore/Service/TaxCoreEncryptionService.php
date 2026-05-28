<?php

namespace App\Modules\Integration\TaxCore\Service;

use App\Shared\Security\EncryptionService;

class TaxCoreEncryptionService
{
    public function __construct(private readonly EncryptionService $encryptionService) {}

    public function encodeCertificate(string $pfxBinary): string
    {
        return $this->encryptionService->encrypt(base64_encode($pfxBinary));
    }

    public function decodeCertificate(string $encryptedValue): string
    {
        return base64_decode($this->encryptionService->decrypt($encryptedValue));
    }

    public function encodePassword(string $password): string
    {
        return $this->encryptionService->encrypt($password);
    }

    public function decodePassword(string $encryptedPassword): string
    {
        return $this->encryptionService->decrypt($encryptedPassword);
    }

    public function encodePac(string $pac): string
    {
        return $this->encryptionService->encrypt($pac);
    }

    public function decodePac(string $encryptedPac): string
    {
        return $this->encryptionService->decrypt($encryptedPac);
    }
}
