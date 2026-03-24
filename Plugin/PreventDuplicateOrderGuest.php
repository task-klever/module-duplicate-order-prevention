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
     * Check for duplicate order before placing (guest checkout)
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
            $paymentMethodCode = $paymentMethod->getMethod();

            if ($this->isDuplicateOrder($email, $grandTotal, $paymentMethodCode)) {
                $this->logger->info(
                    'Klever_DuplicateOrderPrevention: Blocked duplicate guest order attempt',
                    [
                        'customer_email' => $email,
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
                'Klever_DuplicateOrderPrevention: Error checking duplicate guest order',
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

        return $collection->getSize() > 0;
    }
}
