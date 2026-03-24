<?php
/**
 * Klever Tech Solution
 * Duplicate Order Prevention Module
 */

declare(strict_types=1);

namespace Klever\DuplicateOrderPrevention\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\UrlInterface;
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
     * Get order view URL for logged-in customer
     */
    public function getCustomerOrderUrl(int $orderId): string
    {
        return $this->_urlBuilder->getUrl('sales/order/view', ['order_id' => $orderId]);
    }

    /**
     * Get guest order lookup URL (Orders and Returns page)
     */
    public function getGuestOrderUrl(): string
    {
        return $this->_urlBuilder->getUrl('sales/guest/form');
    }

    /**
     * Get error message with order increment ID and URL placeholder support
     * Use %1 for order increment ID and %2 for order URL
     */
    public function getErrorMessage(?string $incrementId = null, ?string $orderUrl = null, ?int $storeId = null): string
    {
        $message = $this->scopeConfig->getValue(
            self::XML_PATH_ERROR_MESSAGE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if (empty($message)) {
            $message = 'You have an incomplete order <a href="%2">#%1</a>. Please try completing it with a different payment method instead of placing a new order.';
        }

        if ($incrementId) {
            $message = str_replace('%1', $incrementId, $message);
        }

        if ($orderUrl) {
            $message = str_replace('%2', $orderUrl, $message);
        }

        return $message;
    }
}
