<?php declare(strict_types=1);

namespace Px86\CategoryNotifier\Service;

use Psr\Log\LoggerInterface;
use Px86\CategoryNotifier\Core\Content\CategorySubscription\CategorySubscriptionEntity;
use Shopware\Core\Content\Mail\Service\MailService;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class NotificationService
{
    public function __construct(
        private readonly EntityRepository $categoryRepository,
        private readonly RouterInterface $router,
        private readonly MailService $mailService,
        private readonly EntityRepository $mailTemplateRepository,
        private readonly EntityRepository $salesChannelRepository,
        private readonly LoggerInterface $logger,
        private readonly EntityRepository $salutationRepository,
        private readonly EntityRepository $salesChannelDomainRepository,
        private readonly EntityRepository $productRepository
    ) {
    }

    public function sendNewProductNotification(
        CategorySubscriptionEntity $subscription,
        ProductEntity $product,
        Context $context
    ): void {
        $salesChannelId = $subscription->getSalesChannelId();
        $languageId = $subscription->getLanguageId();

        if (!$salesChannelId) {
            $salesChannel = $this->getFirstActiveSalesChannel($context);
            $salesChannelId = $salesChannel?->getId();
        }

        if (!$salesChannelId) {
            return;
        }

        if ($languageId !== $context->getLanguageId()) {
            $context = new Context(
                $context->getSource(),
                $context->getRuleIds(),
                $context->getCurrencyId(),
                [$languageId, Defaults::LANGUAGE_SYSTEM]
            );
        }

        $product = $this->productRepository->search(new Criteria([$product->getId()]), $context)->first();
        if (!$product) {
            return;
        }

        $category = $this->categoryRepository->search(
            new Criteria([$subscription->getCategoryId()]),
            $context
        )->first();

        if (!$category) {
            return;
        }

        $salesChannel = $this->salesChannelRepository->search(new Criteria([$salesChannelId]), $context)->first();
        if (!$salesChannel) {
            return;
        }

        $domainUrl = $this->resolveDomainUrl($salesChannelId, $languageId);

        $productUrl = $domainUrl . '/detail/' . $product->getId();
        $unsubscribePath = '/category-notifier/unsubscribe/' . urlencode($subscription->getEmail()) . '/' . $subscription->getCategoryId();
        $unsubscribeUrl = $domainUrl . $unsubscribePath;

        $mailTemplate = $this->loadMailTemplate('px86_category_notifier.new_product', $context);
        if (!$mailTemplate) {
            return;
        }

        $salutation = null;
        if ($subscription->getSalutationId()) {
            $salutation = $this->salutationRepository->search(
                new Criteria([$subscription->getSalutationId()]),
                $context
            )->first();
        }

        $data = [
            'recipients' => [
                $subscription->getEmail() => $subscription->getFirstName()
                    ? $subscription->getFirstName() . ' ' . $subscription->getLastName()
                    : $subscription->getEmail()
            ],
            'salesChannelId' => $salesChannel->getId(),
            'templateId' => $mailTemplate->getId(),
            'customFields' => [],
            'contentHtml' => $mailTemplate->getContentHtml(),
            'contentPlain' => $mailTemplate->getContentPlain(),
            'subject' => $mailTemplate->getSubject(),
            'senderName' => $mailTemplate->getSenderName() ?? $salesChannel->getName(),
        ];

        $templateData = [
            'firstName' => $subscription->getFirstName(),
            'lastName' => $subscription->getLastName(),
            'email' => $subscription->getEmail(),
            'salutation' => $salutation,
            'product' => $product,
            'category' => $category,
            'productUrl' => $productUrl,
            'unsubscribeUrl' => $unsubscribeUrl,
            'salesChannel' => $salesChannel,
        ];

        $this->mailService->send($data, $context, $templateData);
    }

    public function sendConfirmationEmail(
        string $email,
        string $confirmToken,
        ?string $firstName,
        SalesChannelContext $salesChannelContext,
        string $categoryId,
        ?string $lastName = null,
        ?string $salutationId = null
    ): void {
        $salesChannel = $salesChannelContext->getSalesChannel();
        $context = $salesChannelContext->getContext();

        $category = $this->categoryRepository->search(new Criteria([$categoryId]), $context)->first();
        if (!$category) {
            throw new \RuntimeException('Category not found: ' . $categoryId);
        }

        $salutation = null;
        if ($salutationId) {
            $salutation = $this->salutationRepository->search(new Criteria([$salutationId]), $context)->first();
        }

        $confirmUrl = $this->resolveConfirmUrl($salesChannel->getId(), $context->getLanguageId(), $confirmToken);

        $mailTemplate = $this->loadMailTemplate('px86_category_notifier.confirmation', $context);
        if (!$mailTemplate) {
            $mailTemplate = $this->loadMailTemplate('px86_category_notifier.confirmation', Context::createDefaultContext());
        }

        if (!$mailTemplate) {
            throw new \RuntimeException('Confirmation mail template not found');
        }

        $data = [
            'recipients' => [$email => $firstName ?? $email],
            'salesChannelId' => $salesChannel->getId(),
            'templateId' => $mailTemplate->getId(),
            'customFields' => [],
            'contentHtml' => $mailTemplate->getContentHtml(),
            'contentPlain' => $mailTemplate->getContentPlain(),
            'subject' => $mailTemplate->getSubject(),
            'senderName' => $mailTemplate->getSenderName() ?? $salesChannel->getName(),
        ];

        $templateData = [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
            'salutation' => $salutation,
            'category' => $category,
            'confirmUrl' => $confirmUrl,
            'salesChannel' => $salesChannel,
        ];

        try {
            $this->mailService->send($data, $context, $templateData);
        } catch (\Throwable $e) {
            $this->logger->error('Category Notifier: Failed to send confirmation email', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function resolveDomainUrl(string $salesChannelId, string $languageId): string
    {
        $domainCriteria = new Criteria();
        $domainCriteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
        $domainCriteria->addFilter(new EqualsFilter('languageId', $languageId));
        $domainCriteria->setLimit(1);

        $domainEntity = $this->salesChannelDomainRepository->search($domainCriteria, Context::createDefaultContext())->first();

        if (!$domainEntity) {
            $fallbackCriteria = new Criteria();
            $fallbackCriteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
            $fallbackCriteria->setLimit(1);
            $domainEntity = $this->salesChannelDomainRepository->search($fallbackCriteria, Context::createDefaultContext())->first();
        }

        if ($domainEntity) {
            $url = rtrim($domainEntity->getUrl(), '/');
            if (preg_match('/^https?:\/\//', $url)) {
                return $url;
            }
            $this->logger->warning('Category Notifier: Invalid domain URL, using fallback', ['url' => $url]);
        }

        $this->logger->warning('Category Notifier: No domain found for SalesChannel', ['salesChannelId' => $salesChannelId]);

        return 'http://localhost';
    }

    private function resolveConfirmUrl(string $salesChannelId, string $languageId, string $confirmToken): string
    {
        $domainCriteria = new Criteria();
        $domainCriteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
        $domainCriteria->addFilter(new EqualsFilter('languageId', $languageId));
        $domainCriteria->setLimit(1);

        $domainEntity = $this->salesChannelDomainRepository->search($domainCriteria, Context::createDefaultContext())->first();

        if ($domainEntity) {
            $domainUrl = rtrim($domainEntity->getUrl(), '/');

            return $domainUrl . '/category-notifier/confirm/' . $confirmToken;
        }

        return $this->router->generate(
            'px86_category_notifier_confirm',
            ['token' => $confirmToken],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    private function loadMailTemplate(string $technicalName, Context $context)
    {
        $criteria = new Criteria();
        $criteria->addAssociation('mailTemplateType');
        $criteria->addFilter(new EqualsFilter('mailTemplateType.technicalName', $technicalName));
        $criteria->setLimit(1);

        return $this->mailTemplateRepository->search($criteria, $context)->first();
    }

    private function getFirstActiveSalesChannel(Context $context)
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('active', true));

        return $this->salesChannelRepository->search($criteria, $context)->first();
    }
}
