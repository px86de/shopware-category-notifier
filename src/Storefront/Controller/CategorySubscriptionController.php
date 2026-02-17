<?php declare(strict_types=1);

namespace Px86\CategoryNotifier\Storefront\Controller;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Px86\CategoryNotifier\Service\NotificationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class CategorySubscriptionController extends StorefrontController
{
    public function __construct(
        private readonly EntityRepository $categorySubscriptionRepository,
        private readonly ValidatorInterface $validator,
        private readonly NotificationService $notificationService,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route(path: '/category-notifier/subscribe', name: 'px86_category_notifier_subscribe', defaults: ['XmlHttpRequest' => true, 'csrf_protected' => false, '_routeScope' => ['storefront']], methods: ['POST'])]
    public function subscribe(Request $request, RequestDataBag $data, SalesChannelContext $context): JsonResponse
    {
        $email = $data->get('email');
        $categoryId = $data->get('categoryId');
        $salutationId = $data->get('salutationId');

        if (empty($salutationId)) {
            $salutationId = null;
        }

        $firstName = $data->get('firstName');
        $lastName = $data->get('lastName');

        // Validierung
        $violations = $this->validator->validate($email, [
            new NotBlank(),
            new Email()
        ]);

        if ($violations->count() > 0) {
            return new JsonResponse([
                'success' => false,
                'message' => $this->trans('px86-category-notifier.subscription.error.invalidEmail')
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!$categoryId || !Uuid::isValid($categoryId)) {
            return new JsonResponse([
                'success' => false,
                'message' => $this->trans('px86-category-notifier.subscription.error.invalidCategory')
            ], Response::HTTP_BAD_REQUEST);
        }

        // Prüfen ob bereits angemeldet
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));
        $criteria->addFilter(new EqualsFilter('categoryId', $categoryId));

        $existing = $this->categorySubscriptionRepository->search($criteria, $context->getContext())->first();

        // Wenn Abonnement existiert und aktiv + bestätigt ist
        if ($existing && $existing->get('active') && $existing->get('confirmed')) {
            return new JsonResponse([
                'success' => false,
                'message' => $this->trans('px86-category-notifier.subscription.error.alreadySubscribed')
            ], Response::HTTP_CONFLICT);
        }

        // Neues Token generieren
        $confirmToken = bin2hex(random_bytes(32));

        try {
            // Wenn deaktiviertes Abonnement existiert, reaktivieren mit neuem Token
            if ($existing) {
                $this->categorySubscriptionRepository->update([
                    [
                        'id' => $existing->getId(),
                        'salutationId' => $salutationId,
                        'firstName' => $firstName,
                        'lastName' => $lastName,
                        'confirmed' => false,
                        'confirmToken' => $confirmToken,
                        'active' => true,
                        'languageId' => $context->getContext()->getLanguageId(),
                        'salesChannelId' => $context->getSalesChannel()->getId(),
                    ]
                ], $context->getContext());
            } else {
                // Neues Abonnement erstellen
                $this->categorySubscriptionRepository->create([
                    [
                        'id' => Uuid::randomHex(),
                        'email' => $email,
                        'categoryId' => $categoryId,
                        'salutationId' => $salutationId,
                        'firstName' => $firstName,
                        'lastName' => $lastName,
                        'confirmed' => false,
                        'confirmToken' => $confirmToken,
                        'active' => true,
                        'languageId' => $context->getContext()->getLanguageId(),
                        'salesChannelId' => $context->getSalesChannel()->getId(),
                    ]
                ], $context->getContext());
            }

            // Bestätigungs-E-Mail senden
            $this->notificationService->sendConfirmationEmail(
                $email,
                $confirmToken,
                $firstName,
                $context,
                $categoryId,
                $lastName,
                $salutationId
            );
        } catch (\Throwable $e) {
            $this->logger->error('Category Subscription Error', [
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'message' => $this->trans('px86-category-notifier.subscription.errorGeneral')
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'success' => true,
            'message' => $this->trans('px86-category-notifier.subscription.success')
        ]);
    }

    #[Route(path: '/category-notifier/confirm/{token}', name: 'px86_category_notifier_confirm', defaults: ['_routeScope' => ['storefront']], methods: ['GET'])]
    public function confirm(string $token, SalesChannelContext $context): Response
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('confirmToken', $token));

        $subscription = $this->categorySubscriptionRepository->search($criteria, $context->getContext())->first();

        if (!$subscription) {
            $this->addFlash('danger', $this->trans('px86-category-notifier.confirmation.error'));
            return $this->redirectToRoute('frontend.home.page');
        }

        $this->categorySubscriptionRepository->update([
            [
                'id' => $subscription->getId(),
                'confirmed' => true,
                'confirmToken' => null,
            ]
        ], $context->getContext());

        $this->addFlash('success', $this->trans('px86-category-notifier.confirmation.success'));
        
        return $this->redirectToRoute('frontend.home.page');
    }

    #[Route(path: '/category-notifier/unsubscribe/{email}/{categoryId}', name: 'px86_category_notifier_unsubscribe', defaults: ['_routeScope' => ['storefront']], methods: ['GET'])]
    public function unsubscribe(string $email, string $categoryId, SalesChannelContext $context): Response
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));
        $criteria->addFilter(new EqualsFilter('categoryId', $categoryId));

        $subscription = $this->categorySubscriptionRepository->search($criteria, $context->getContext())->first();

        if (!$subscription) {
            $this->addFlash('danger', $this->trans('px86-category-notifier.unsubscribe.error.notFound'));
            return $this->redirectToRoute('frontend.home.page');
        }

        $this->categorySubscriptionRepository->update([
            [
                'id' => $subscription->getId(),
                'active' => false,
            ]
        ], $context->getContext());

        $this->addFlash('success', $this->trans('px86-category-notifier.unsubscribe.success'));
        
        return $this->redirectToRoute('frontend.home.page');
    }
}
