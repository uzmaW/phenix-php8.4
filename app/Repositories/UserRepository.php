<?php

namespace App\Repositories;

use App\Auth\User;
use Phoenix\Database\Connection;
use Phoenix\Database\Repository;

class UserRepository extends Repository
{
    protected string $table = 'users';
    protected string $entity = User::class;

    public function findByEmail(string $email): ?User
    {
        $stmt = Connection::get()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $data ? new User((int) $data['id'], $data['name'], $data['email']) : null;
    }
}
