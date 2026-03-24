<?php
/**
 * Klever Tech Solution
 * Duplicate Order Prevention Module
 */

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Klever_DuplicateOrderPrevention',
    __DIR__
);
