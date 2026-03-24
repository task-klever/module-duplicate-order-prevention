<?php
/**
 * Klever Tech Solution
 * Duplicate Order Prevention Module
 */

declare(strict_types=1);

namespace Klever\DuplicateOrderPrevention\Plugin;

use Klever\DuplicateOrderPrevention\Helper\Data as Helper;
use Magento\Checkout\Model\PaymentInformationManagement;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Psr\Log\LoggerInterface;

class PreventDuplicateOrder
{
    /**
     * @var Helper
     */
    private Helper $helper;

    /**
     * @var OrderCollectionFactory
     */
    private OrderCollectionFactory $orderCollectionFactory;

    /**
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $cartRepository;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    public function __construct(
        Helper $helper,
        OrderCollectionFactory $orderCollectionFactory,
        CartRepositoryInterface $cartRepository,
        LoggerInterface $logger
    ) {
        $this->helper = $helper;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->cartRepository = $cartRepository;
        $this->logger = $logger;
    }

    /**
     * Check for incomplete order before placing a new one
     *
     * @param PaymentInformationManagement $subject
     * @param int $cartId
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @return void
     * @throws LocalizedException
     */
    public function beforeSavePaymentInformationAndPlaceOrder(
        PaymentInformationManagement $subject,
        $cartId,
        PaymentInterface $paymentMethod,
        AddressInterface $billingAddress = null
    ): void {
        if (!$this->helper->isEnabled()) {
            return;
        }

        try {
            $quote = $this->cartRepository->getActive($cartId);
            $customerEmail = $quote->getCustomerEmail();
            $grandTotal = (float)$quote->getGrandTotal();

            $incompleteOrder = $this->findIncompleteOrder($customerEmail, $grandTotal);

            if ($incompleteOrder) {
                $incrementId = $incompleteOrder->getIncrementId();
                $orderUrl = $this->helper->getCustomerOrderUrl((int)$incompleteOrder->getId());
                $this->logger->info(
                    'Klever_DuplicateOrderPrevention: Incomplete order found, prompting customer to complete it',
                    [
                        'customer_email' => $customerEmail,
                        'grand_total' => $grandTotal,
                        'existing_order' => $incrementId
                    ]
                );
                throw new LocalizedException(
                    __($this->helper->getErrorMessage($incrementId, $orderUrl))
                );
            }
        } catch (LocalizedException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error(
                'Klever_DuplicateOrderPrevention: Error checking for incomplete orders',
                ['exception' => $e->getMessage()]
            );
        }
    }

    /**
     * Find an incomplete order matching the current checkout
     *
     * @return \Magento\Sales\Model\Order|null
     */
    private function findIncompleteOrder(?string $customerEmail, float $grandTotal)
    {
        if (empty($customerEmail)) {
            return null;
        }

        if (!$this->helper->shouldCheckCustomerEmail() && !$this->helper->shouldCheckGrandTotal()) {
            return null;
        }

        $timeWindow = $this->helper->getTimeWindow();
        $fromTime = date('Y-m-d H:i:s', strtotime("-{$timeWindow} minutes"));
        $statuses = $this->helper->getOrderStatuses();

        if (empty($statuses)) {
            return null;
        }

        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('created_at', ['gteq' => $fromTime]);
        $collection->addFieldToFilter('state', ['in' => $statuses]);

        if ($this->helper->shouldCheckCustomerEmail()) {
            $collection->addFieldToFilter('customer_email', $customerEmail);
        }

        if ($this->helper->shouldCheckGrandTotal()) {
            $collection->addFieldToFilter('grand_total', $grandTotal);
        }

        $collection->setOrder('created_at', 'DESC');
        $collection->setPageSize(1);

        $order = $collection->getFirstItem();

        if ($order && $order->getId()) {
            return $order;
        }

        return null;
    }
}
