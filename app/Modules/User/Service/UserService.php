<?php

namespace App\Modules\User\Service;

use App\Modules\User\Domain\User;
use App\Modules\User\Repository\UserRepository;

class UserService
{
    public function __construct(private readonly UserRepository $userRepository) {}

    public function findByEmail(string $email): ?User
    {
        return $this->userRepository->findByEmail($email);
    }

    public function recordLogin(User $user): void
    {
        $this->userRepository->touchLastLogin($user);
    }
}