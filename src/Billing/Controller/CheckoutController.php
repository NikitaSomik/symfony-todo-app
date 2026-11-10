<?php

declare(strict_types=1);

namespace App\Billing\Controller;

use App\Auth\Contract\AuthenticatedUser;
use App\Billing\Api\Documentation\CheckoutSessionResponseSchema;
use App\Billing\DTO\CheckoutDTO;
use App\Billing\Security\BillingVoter;
use App\Billing\Security\WorkspaceContext;
use App\Billing\Security\WorkspaceContextValueResolver;
use App\Billing\Service\StartCheckout;
use App\Shared\Api\Documentation\JsonApiContent;
use App\Shared\Api\JsonApiResponse;
use App\Shared\Api\ResourceItem;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[OA\Tag(name: 'Billing')]
final class CheckoutController extends AbstractController
{
    public function __construct(
        private readonly StartCheckout $startCheckout,
    ) {
    }

    #[Route('/api/v1/workspaces/{id}/billing/checkout', name: 'api_billing_checkout', requirements: ['id' => Requirement::UUID_V7], methods: ['POST'], format: 'json')]
    #[IsGranted(BillingVoter::MANAGE, subject: 'workspace')]
    #[OA\Post(summary: 'Open a payment page to buy a plan for a workspace', description: 'Owners only. The plan changes once Stripe reports the payment, not when the page opens.')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: CheckoutDTO::class)))]
    #[OA\Response(response: 201, description: 'A Stripe Checkout page; send the customer to `attributes.url`', content: new JsonApiContent(ref: new Model(type: CheckoutSessionResponseSchema::class)))]
    #[OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401)]
    #[OA\Response(response: 403, description: 'The user may not manage billing in this workspace')]
    #[OA\Response(response: 404, description: 'Workspace not found, or the user is not a member of it')]
    #[OA\Response(response: 409, description: 'The workspace already has a subscription, paid or being paid, including through another of its pages paid while this one was being opened; the code is `already_subscribed`')]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    #[OA\Response(response: 503, description: 'Stripe did not answer; the code is `payment_provider_unavailable`, and the request can be repeated after `Retry-After` seconds', headers: [new OA\Header(header: 'Retry-After', description: 'Seconds to wait before asking again', schema: new OA\Schema(type: 'integer'))])]
    public function checkout(
        #[MapRequestPayload(acceptFormat: 'json')]
        CheckoutDTO $dto,
        #[ValueResolver(WorkspaceContextValueResolver::class)]
        WorkspaceContext $workspace,
        #[CurrentUser]
        AuthenticatedUser $user,
    ): JsonResponse {
        $page = $this->startCheckout->handle($workspace->workspaceId, $dto->plan(), $user->email());

        return JsonApiResponse::one(
            new ResourceItem(type: 'checkout_sessions', id: $page->id, attributes: ['url' => $page->url]),
            Response::HTTP_CREATED,
        );
    }
}
