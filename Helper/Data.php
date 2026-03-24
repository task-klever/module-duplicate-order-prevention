<?php
/**
 * Klever Tech Solution
 * Duplicate Order Prevention Module
 */

declare(strict_types=1);

namespace Klever\DuplicateOrderPrevention\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    private const XML_PATH_ENABLED = 'klever_duplicate_order/general/enabled';
    private const XML_PATH_TIME_WINDOW = 'klever_duplicate_order/general/time_window';
    private const XML_PATH_CHECK_PAYMENT_METHOD = 'klever_duplicate_order/general/check_payment_method';
    private const XML_PATH_CHECK_GRAND_TOTAL = 'klever_duplicate_order/general/check_grand_total';
    private const XML_PATH_CHECK_CUSTOMER_EMAIL = 'klever_duplicate_order/general/check_customer_email';
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
     * Check if payment method should be checked
     */
    public function shouldCheckPaymentMethod(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_CHECK_PAYMENT_METHOD,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
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
     * Get error message
     */
    public function getErrorMessage(?int $storeId = null): string
    {
        $message = $this->scopeConfig->getValue(
            self::XML_PATH_ERROR_MESSAGE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return $message ?: (string)__('You have already placed a similar order recently. Please wait before placing another order.');
    }
}
