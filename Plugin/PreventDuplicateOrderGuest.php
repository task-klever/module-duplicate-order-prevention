<?php
/**
 * Klever Tech Solution
 * Duplicate Order Prevention Module
 */

declare(strict_types=1);

namespace Klever\DuplicateOrderPrevention\Plugin;

use Klever\DuplicateOrderPrevention\Helper\Data as Helper;
use Magento\Checkout\Model\GuestPaymentInformationManagement;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\GuestCartRepositoryInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Psr\Log\LoggerInterface;

class PreventDuplicateOrderGuest
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
     * @var GuestCartRepositoryInterface
     */
    private GuestCartRepositoryInterface $cartRepository;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    public function __construct(
        Helper $helper,
        OrderCollectionFactory $orderCollectionFactory,
        GuestCartRepositoryInterface $cartRepository,
        LoggerInterface $logger
    ) {
        $this->helper = $helper;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->cartRepository = $cartRepository;
        $this->logger = $logger;
    }

    /**
     * Check for incomplete order before placing a new one (guest checkout)
     *
     * @param GuestPaymentInformationManagement $subject
     * @param string $cartId
     * @param string $email
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @return void
     * @throws LocalizedException
     */
    public function beforeSavePaymentInformationAndPlaceOrder(
        GuestPaymentInformationManagement $subject,
        $cartId,
        $email,
        PaymentInterface $paymentMethod,
        AddressInterface $billingAddress = null
    ): void {
        if (!$this->helper->isEnabled()) {
            return;
        }

        try {
            $quote = $this->cartRepository->get($cartId);
            $grandTotal = (float)$quote->getGrandTotal();

            $incompleteOrder = $this->findIncompleteOrder($email, $grandTotal);

            if ($incompleteOrder) {
                $incrementId = $incompleteOrder->getIncrementId();
                $this->logger->info(
                    'Klever_DuplicateOrderPrevention: Incomplete guest order found, prompting customer to complete it',
                    [
                        'customer_email' => $email,
                        'grand_total' => $grandTotal,
                        'existing_order' => $incrementId
                    ]
                );
                throw new LocalizedException(
                    __($this->helper->getErrorMessage($incrementId))
                );
            }
        } catch (LocalizedException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error(
                'Klever_DuplicateOrderPrevention: Error checking for incomplete guest orders',
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
