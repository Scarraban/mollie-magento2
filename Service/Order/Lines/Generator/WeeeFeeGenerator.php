<?php
/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Mollie\Payment\Service\Order\Lines\Generator;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Mollie\Payment\Helper\General;

class WeeeFeeGenerator implements GeneratorInterface
{
    /**
     * @var General
     */
    private $mollieHelper;

    /**
     * @var bool
     */
    private $forceBaseCurrency;

    /**
     * @var string|null
     */
    private $currency;

    public function __construct(General $mollieHelper)
    {
        $this->mollieHelper = $mollieHelper;
    }

    public function process(OrderInterface $order, array $orderLines): array
    {
        $this->forceBaseCurrency = (bool)$this->mollieHelper->useBaseCurrency($order->getStoreId());
        $this->currency = $this->forceBaseCurrency ? $order->getBaseCurrencyCode() : $order->getOrderCurrencyCode();

        if ($orderLine = $this->getWeeeFeeOrderLine($order)) {
            $orderLines[] = $orderLine;
        }

        return $orderLines;
    }

    private function getWeeeFeeOrderLine(OrderInterface $order): ?array
    {
        $total = 0.0;
        $weeeItems = $this->getWeeeItems($order);
        if (!$weeeItems) {
            return null;
        }

        /** @var OrderItemInterface $item */
        foreach ($weeeItems as $item) {
            $amount = $item->getWeeeTaxAppliedAmount();
            if ($this->forceBaseCurrency) {
                $amount = $item->getBaseWeeeTaxAppliedAmount();
            }

            $total += $amount;
        }

        return [
            'type' => 'surcharge',
            'name' => $this->getTitle($weeeItems),
            'quantity' => 1,
            'unitPrice' => $this->mollieHelper->getAmountArray($this->currency, $total),
            'totalAmount' => $this->mollieHelper->getAmountArray($this->currency, $total),
            'vatRate' => 0,
            'vatAmount' => $this->mollieHelper->getAmountArray($this->currency, 0.0),
        ];
    }

    private function getWeeeItems(OrderInterface $order): array
    {
        return array_filter($order->getItems(), function (OrderItemInterface $item) {
            return $item->getWeeeTaxAppliedAmount();
        });
    }

    private function getTitle(array $items): string
    {
        /** @var OrderItemInterface $item */
        foreach ($items as $item) {
            $json = json_decode($item->getWeeeTaxApplied(), true);

            if (!$json) {
                continue;
            }

            foreach ($json as $applied) {
                if (isset($applied['title'])) {
                    return $applied['title'];
                }
            }
        }

        return 'FPT';
    }
}