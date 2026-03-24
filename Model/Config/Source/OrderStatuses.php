<?php
/**
 * Klever Tech Solution
 * Duplicate Order Prevention Module
 */

declare(strict_types=1);

namespace Klever\DuplicateOrderPrevention\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Sales\Model\Order;

class OrderStatuses implements OptionSourceInterface
{
    /**
     * Return order state options for multiselect
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Order::STATE_PENDING_PAYMENT, 'label' => __('Pending Payment')],
            ['value' => Order::STATE_PAYMENT_REVIEW, 'label' => __('Payment Review')],
            ['value' => Order::STATE_CANCELED, 'label' => __('Canceled')],
            ['value' => Order::STATE_CLOSED, 'label' => __('Closed')],
            ['value' => Order::STATE_HOLDED, 'label' => __('On Hold')],
            ['value' => 'fraud', 'label' => __('Suspected Fraud')],
        ];
    }
}
