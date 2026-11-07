<?php

declare(strict_types=1);

namespace App\Tests\Application\Billing;

use App\Billing\Repository\PayerRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class PayerRepositoryTest extends KernelTestCase
{
    private function payers(): PayerRepository
    {
        return static::getContainer()->get(PayerRepository::class);
    }

    #[Test]
    public function aWorkspaceWithoutACustomerShouldGetTheOneSaved(): void
    {
        $workspaceId = Uuid::v7();

        self::assertNull($this->payers()->findCustomerId($workspaceId));
        self::assertSame('cus_first', $this->payers()->saveOrGetExisting($workspaceId, 'cus_first'));
        self::assertSame('cus_first', $this->payers()->findCustomerId($workspaceId));
    }

    #[Test]
    public function aCustomerSavedSecondShouldGiveWayToTheFirst(): void
    {
        $workspaceId = Uuid::v7();
        $this->payers()->saveOrGetExisting($workspaceId, 'cus_first');

        self::assertSame('cus_first', $this->payers()->saveOrGetExisting($workspaceId, 'cus_second'));
        self::assertSame('cus_first', $this->payers()->findCustomerId($workspaceId));
    }

    #[Test]
    public function workspacesShouldKeepTheirOwnCustomers(): void
    {
        $first = Uuid::v7();
        $second = Uuid::v7();

        $this->payers()->saveOrGetExisting($first, 'cus_first');
        $this->payers()->saveOrGetExisting($second, 'cus_second');

        self::assertSame('cus_first', $this->payers()->findCustomerId($first));
        self::assertSame('cus_second', $this->payers()->findCustomerId($second));
    }
}
