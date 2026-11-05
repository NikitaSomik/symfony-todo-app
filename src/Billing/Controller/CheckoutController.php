<?php

declare(strict_types=1);

namespace App\Billing\Controller;

use App\Auth\Contract\AuthenticatedUser;
use App\Billing\DTO\CheckoutDTO;
use App\Billing\Service\StartCheckout;
use App\Shared\Api\JsonApiResponse;
use App\Shared\Api\ResourceItem;
use App\Workspace\Contract\WorkspaceAccess;
use App\Workspace\Contract\WorkspacePermission;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Uid\Uuid;

#[OA\Tag(name: 'Billing')]
final class CheckoutController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaces,
        private readonly StartCheckout $startCheckout,
    ) {
    }

    #[Route('/api/v1/workspaces/{id}/billing/checkout', name: 'api_billing_checkout', requirements: ['id' => Requirement::UUID_V7], methods: ['POST'], format: 'json')]
    #[OA\Post(summary: 'Open a payment page to buy a plan for a workspace', description: 'Owners only. The plan changes once Stripe reports the payment, not when the page opens.')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: CheckoutDTO::class)))]
    #[OA\Response(response: 201, description: 'A Stripe Checkout page; send the customer to `attributes.url`')]
    #[OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401)]
    #[OA\Response(response: 403, description: 'The user may not manage billing in this workspace')]
    #[OA\Response(response: 404, description: 'Workspace not found, or the user is not a member of it')]
    #[OA\Response(response: 409, description: 'The workspace already has a paid plan')]
    #[OA\Response(ref: '#/components/responses/ValidationError', response: 422)]
    public function checkout(string $id, #[MapRequestPayload(acceptFormat: 'json')] CheckoutDTO $dto, #[CurrentUser] AuthenticatedUser $user): JsonResponse
    {
        $workspaceId = Uuid::fromString($id);
        $permissions = $this->workspaces->permissionsOf($workspaceId, $user->id());

        if ([] === $permissions) {
            throw new NotFoundHttpException(sprintf('Workspace "%s" not found.', $id));
        }

        if (!in_array(WorkspacePermission::MANAGE_BILLING, $permissions, true)) {
            throw new AccessDeniedException();
        }

        $page = $this->startCheckout->handle($workspaceId, $dto->plan());

        return JsonApiResponse::one(
            new ResourceItem(type: 'checkout_sessions', id: $page->id, attributes: ['url' => $page->url]),
            Response::HTTP_CREATED,
        );
    }
}
