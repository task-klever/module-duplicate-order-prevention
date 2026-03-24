<?php
/**
 * Klever Tech Solution
 * Duplicate Order Prevention Module
 */

declare(strict_types=1);

namespace Klever\DuplicateOrderPrevention\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    private const XML_PATH_ENABLED = 'klever_duplicate_order/general/enabled';
    private const XML_PATH_TIME_WINDOW = 'klever_duplicate_order/general/time_window';
    private const XML_PATH_CHECK_GRAND_TOTAL = 'klever_duplicate_order/general/check_grand_total';
    private const XML_PATH_CHECK_CUSTOMER_EMAIL = 'klever_duplicate_order/general/check_customer_email';
    private const XML_PATH_ORDER_STATUSES = 'klever_duplicate_order/general/order_statuses';
    private const XML_PATH_ERROR_MESSAGE = 'klever_duplicate_order/general/error_message';

    /**
     * Check if module is enabled
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get time window in minutes
     */
    public function getTimeWindow(?int $storeId = null): int
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_TIME_WINDOW,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return (int)($value ?: 30);
    }

    /**
     * Check if grand total should be checked
     */
    public function shouldCheckGrandTotal(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_CHECK_GRAND_TOTAL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if customer email should be checked
     */
    public function shouldCheckCustomerEmail(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_CHECK_CUSTOMER_EMAIL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get order statuses to check for incomplete orders
     */
    public function getOrderStatuses(?int $storeId = null): array
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_ORDER_STATUSES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if (empty($value)) {
            return ['canceled', 'closed', 'payment_review', 'pending_payment'];
        }

        return explode(',', $value);
    }

    /**
     * Get error message with order increment ID placeholder support
     * Use %1 in the message as placeholder for order increment ID
     */
    public function getErrorMessage(?string $incrementId = null, ?int $storeId = null): string
    {
        $message = $this->scopeConfig->getValue(
            self::XML_PATH_ERROR_MESSAGE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if (empty($message)) {
            $message = 'You have an incomplete order #%1. Please try completing it with a different payment method instead of placing a new order.';
        }

        if ($incrementId) {
            $message = str_replace('%1', $incrementId, $message);
        }

        return $message;
    }
}
