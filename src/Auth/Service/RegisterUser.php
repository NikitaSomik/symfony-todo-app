<?php

declare(strict_types=1);

namespace App\Auth\Service;

use App\Auth\DTO\RegisterDTO;
use App\Auth\Entity\User;
use App\Auth\Exception\EmailAlreadyTakenException;
use App\Auth\ValueObject\Email;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class RegisterUser
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function handle(RegisterDTO $dto): User
    {
        $user = new User();
        $user->setEmail(new Email($dto->email));
        $user->setPassword($this->passwordHasher->hashPassword($user, $dto->password));

        try {
            $this->em->persist($user);
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw new EmailAlreadyTakenException();
        }

        return $user;
    }
}
