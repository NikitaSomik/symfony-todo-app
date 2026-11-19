<?php

declare(strict_types=1);

namespace App\Billing\Controller;

use App\Billing\Api\Documentation\BillingResponseSchema;
use App\Billing\Query\WorkspacePlans;
use App\Billing\Security\WorkspaceContext;
use App\Billing\Security\WorkspaceContextValueResolver;
use App\Shared\Api\Documentation\JsonApiContent;
use App\Shared\Api\JsonApiResponse;
use App\Shared\Api\ResourceItem;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'Billing')]
final class BillingController extends AbstractController
{
    public function __construct(
        private readonly WorkspacePlans $plans,
    ) {
    }

    #[Route('/api/v1/workspaces/{id}/billing', name: 'api_billing_show', requirements: ['id' => Requirement::UUID_V7], methods: ['GET'], format: 'json')]
    #[OA\Get(summary: 'Read a workspace\'s plan and the status of its subscription', description: 'Every member. After Checkout, an owner asks again until the status shows the payment.')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(response: 200, description: 'The plan and the status, from our copy of the subscription', content: new JsonApiContent(ref: new Model(type: BillingResponseSchema::class)))]
    #[OA\Response(ref: '#/components/responses/UnauthorizedError', response: 401)]
    #[OA\Response(response: 404, description: 'Workspace not found, or the user is not a member of it')]
    public function __invoke(
        #[ValueResolver(WorkspaceContextValueResolver::class)]
        WorkspaceContext $workspace,
    ): JsonResponse {
        $billing = $this->plans->billingOf($workspace->workspaceId);

        return JsonApiResponse::one(new ResourceItem(
            type: 'billing',
            id: $workspace->workspaceId->toRfc4122(),
            attributes: ['plan' => $billing->plan->value, 'status' => $billing->status?->value],
        ));
    }
}
