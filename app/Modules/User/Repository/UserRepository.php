<?php

namespace App\Modules\User\Repository;

use App\Modules\User\Domain\User;
use App\Shared\Repository\AbstractRepository;

class UserRepository extends AbstractRepository
{
    public function __construct(User $user)
    {
        parent::__construct($user);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }

    public function touchLastLogin(User $user): void
    {
        $user->last_login_at = now();
        $this->save($user);
    }
}