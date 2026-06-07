<?php

namespace App\Repositories;

use Phoenix\Database\Repository;
use Phoenix\Database\Connection;
use App\Auth\User;

class UserRepository extends Repository
{
    protected string $table = 'users';
    protected string $entity = User::class;

    public function findByEmail(string $email): ?User
    {
        $stmt = Connection::get()->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $data ? new User((int)$data['id'], $data['name'], $data['email']) : null;
    }
}
