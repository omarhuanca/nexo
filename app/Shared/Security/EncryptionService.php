<?php

namespace App\Shared\Security;

class EncryptionService
{
    public function encrypt(string $data): string
    {
        return encrypt($data);
    }

    public function decrypt(string $encryptedData): string
    {
        return decrypt($encryptedData);
    }
}