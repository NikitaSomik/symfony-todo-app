<?php

declare(strict_types=1);

namespace App\Auth\Service;

use App\Auth\DTO\RegisterDTO;
use App\Auth\Entity\User;
use App\Auth\Exception\EmailAlreadyTakenException;
use App\Auth\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class RegisterUser
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function handle(RegisterDTO $dto): User
    {
        if ($this->userRepository->findOneByEmail($dto->email)) {
            throw new EmailAlreadyTakenException();
        }

        $user = new User();
        $user->setEmail($dto->email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $dto->password));

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
