<?php

/**
 * Paystack Magento2 Module using \Magento\Payment\Model\Method\AbstractMethod
 * Copyright (C) 2019 Paystack.com
 * 
 * This file is part of Pstk/Paystack.
 * 
 * Pstk/Paystack is free software => you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http =>//www.gnu.org/licenses/>.
 */

namespace Pstk\Paystack\Controller\Payment;

class Setup extends AbstractPaystackStandard {

    /**
     * Execute view action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute() {
        
        $message = '';
        $order = $this->orderInterface->loadByIncrementId($this->checkoutSession->getLastRealOrder()->getIncrementId());
        if ($order && $this->method->getCode() == $order->getPayment()->getMethod()) {

            try {
                return $this->processAuthorization($order);
            } catch (\Yabacon\Paystack\Exception\ApiException $e) {
                $message = $e->getMessage();
                $order->addStatusToHistory($order->getStatus(), $message);
                $this->orderRepository->save($order);
            }
        }

        $this->redirectToFinal(false, $message);
    }

    protected function processAuthorization(\Magento\Sales\Model\Order $order) {

	// add the fee just before sending
	$fee = new \Yabacon\Paystack\Fee();
	$fee->withPercentage(0.015);        // 1.5%
	$fee->withAdditionalCharge(10000);  // plus 100 NGN
	$fee->withThreshold(250000);        // when total is above 2,500 NGN
	$fee->withCap(200000);              // capped at 2000

	// now let's calculate the fees
        $charges = $fee->calculateFor($order->getGrandTotal() * 100);

	$tranx = $this->paystack->transaction->initialize([
            'first_name' => $order->getCustomerFirstname(),
            'last_name' => $order->getCustomerLastname(),
            'amount' => ($order->getGrandTotal() * 100) + $charges, // in kobo
            'email' => $order->getCustomerEmail(), // unique to customers
            'reference' => $order->getIncrementId(), // unique to transactions
            'currency' => $order->getCurrency(),
            'callback_url' => $this->configProvider->store->getBaseUrl() . "paystack/payment/callback",
            'metadata' => array('custom_fields' => array(
                array(
                    "display_name"=>"Plugin",
                    "variable_name"=>"plugin",
                    "value"=>"magento-2"
                )
            )) 
        ]);

        //var_dump($tranx); die();

        $redirectFactory = $this->resultRedirectFactory->create();
        $redirectFactory->setUrl($tranx->data->authorization_url);


        return $redirectFactory;
    }

}
