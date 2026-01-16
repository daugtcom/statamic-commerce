<?php

namespace Daugt\Commerce\Controllers;

use Daugt\Commerce\Services\ShippingRateCalculator;
use Daugt\Commerce\Support\AddonEdition;
use Daugt\Commerce\Support\StripePayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\StripeClient;
use Throwable;

class StripeShippingOptionsController
{
    public function __invoke(
        Request $request,
        StripeClient $stripeClient,
        ShippingRateCalculator $calculator
    ): JsonResponse {
        try {
            if (! AddonEdition::isPro()) {
                return $this->errorResponse(__('daugt-commerce::shipping.errors.missing_rate'));
            }

            $checkoutSessionId = (string) $request->input('checkout_session_id', '');
            $shippingDetails = $request->input('shipping_details', []);

            if ($checkoutSessionId === '' || ! is_array($shippingDetails)) {
                return $this->errorResponse(__('daugt-commerce::shipping.errors.missing_rate'));
            }

            $country = $calculator->normalizeCountry(
                StripePayload::string($shippingDetails, 'address.country')
            );

            if ($country === '' || ! $calculator->isCountryAllowed($country)) {
                return $this->errorResponse(__('daugt-commerce::shipping.errors.unavailable'));
            }

            $lineItemsResponse = $stripeClient->checkout->sessions->allLineItems($checkoutSessionId, ['limit' => 100]);
            $lineItems = StripePayload::array($lineItemsResponse, 'data');

            $amount = $calculator->calculateAmountForCountry($country, $lineItems);
            if ($amount === null) {
                return $this->errorResponse(__('daugt-commerce::shipping.errors.missing_rate'));
            }

            $stripeClient->checkout->sessions->update($checkoutSessionId, [
                'collected_information' => [
                    'shipping_details' => $shippingDetails,
                ],
                'shipping_options' => [
                    $calculator->buildStripeShippingOption($amount),
                ],
            ]);

            return response()->json([
                'type' => 'object',
                'value' => [
                    'succeeded' => true,
                ],
            ]);
        } catch (Throwable $exception) {
            return $this->errorResponse($exception->getMessage());
        }
    }

    private function errorResponse(string $message): JsonResponse
    {
        return response()->json([
            'type' => 'error',
            'message' => $message,
        ], 400);
    }
}
