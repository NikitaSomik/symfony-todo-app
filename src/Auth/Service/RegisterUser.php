<?php

declare(strict_types=1);

namespace App\Auth\Service;

use App\Auth\Contract\UserRegistered;
use App\Auth\DTO\RegisterDTO;
use App\Auth\Entity\User;
use App\Auth\Exception\EmailAlreadyTakenException;
use App\Auth\ValueObject\Email;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class RegisterUser
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $em,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(RegisterDTO $dto): User
    {
        $user = new User();
        $user->setEmail(new Email($dto->email));
        $user->setPassword($this->passwordHasher->hashPassword($user, $dto->password));

        try {
            return $this->em->wrapInTransaction(function () use ($user): User {
                $this->em->persist($user);
                // The id comes from the database, and what registration sets off needs it.
                $this->em->flush();

                $this->eventDispatcher->dispatch(new UserRegistered($user->id()));

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            throw new EmailAlreadyTakenException();
        }
    }
}
