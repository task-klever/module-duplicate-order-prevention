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
     * Check for duplicate order before placing
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
            $paymentMethodCode = $paymentMethod->getMethod();

            if ($this->isDuplicateOrder($customerEmail, $grandTotal, $paymentMethodCode)) {
                $this->logger->info(
                    'Klever_DuplicateOrderPrevention: Blocked duplicate order attempt',
                    [
                        'customer_email' => $customerEmail,
                        'grand_total' => $grandTotal,
                        'payment_method' => $paymentMethodCode
                    ]
                );
                throw new LocalizedException(__($this->helper->getErrorMessage()));
            }
        } catch (LocalizedException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error(
                'Klever_DuplicateOrderPrevention: Error checking duplicate order',
                ['exception' => $e->getMessage()]
            );
        }
    }

    /**
     * Check if this is a duplicate order
     */
    private function isDuplicateOrder(
        ?string $customerEmail,
        float $grandTotal,
        ?string $paymentMethod
    ): bool {
        if (empty($customerEmail)) {
            return false;
        }

        if (!$this->helper->shouldCheckCustomerEmail()
            && !$this->helper->shouldCheckGrandTotal()
            && !$this->helper->shouldCheckPaymentMethod()
        ) {
            return false;
        }

        $timeWindow = $this->helper->getTimeWindow();
        $fromTime = date('Y-m-d H:i:s', strtotime("-{$timeWindow} minutes"));

        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('created_at', ['gteq' => $fromTime]);

        if ($this->helper->shouldCheckCustomerEmail()) {
            $collection->addFieldToFilter('customer_email', $customerEmail);
        }

        if ($this->helper->shouldCheckGrandTotal()) {
            $collection->addFieldToFilter('grand_total', $grandTotal);
        }

        if ($this->helper->shouldCheckPaymentMethod() && $paymentMethod) {
            $collection->getSelect()->join(
                ['payment' => $collection->getTable('sales_order_payment')],
                'main_table.entity_id = payment.parent_id',
                []
            )->where('payment.method = ?', $paymentMethod);
        }

        // Exclude canceled orders from check (optional - you might want to include them)
        // $collection->addFieldToFilter('state', ['neq' => \Magento\Sales\Model\Order::STATE_CANCELED]);

        return $collection->getSize() > 0;
    }
}
