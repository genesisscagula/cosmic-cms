<?php

namespace App\Services;

use Illuminate\Http\Request;

class PaymentCountryResolver
{
    public function country(Request $request): string
    {
        $header = (string) config('payments.routing.country_header', 'CF-IPCountry');

        $country = strtoupper(trim((string) $request->header($header)));

        if ($country === '' || in_array($country, ['XX', 'T1'], true)) {
            $country = strtoupper(trim((string) $request->header('X-Country-Code')));
        }

        return preg_match('/^[A-Z]{2}$/', $country) === 1 ? $country : 'XX';
    }

    public function provider(Request $request, string $productType = 'credits'): string
    {
        /*
         * PayMongo is currently used for Philippine one-time credit purchases.
         * Monthly plans remain on PayPal because the existing PayMongo checkout
         * integration is not a recurring-subscription flow.
         */
        if ($productType === 'plan') {
            return 'paypal';
        }

        $country = $this->country($request);
        $philippines = (string) config('payments.routing.philippines_country_code', 'PH');

        if ($country === $philippines) {
            return (string) config('payments.routing.philippines_provider', 'paymongo');
        }

        return (string) config('payments.routing.international_provider', 'paypal');
    }

    public function payload(Request $request): array
    {
        $country = $this->country($request);

        return [
            'country' => $country,
            'credit_provider' => $this->provider($request, 'credits'),
            'plan_provider' => $this->provider($request, 'plan'),
            'providers' => [
                'paypal' => [
                    'enabled' => (bool) config('payments.paypal.enabled'),
                    'mode' => (string) config('payments.paypal.mode', 'sandbox'),
                ],
                'paymongo' => [
                    'enabled' => (bool) config('payments.paymongo.enabled'),
                ],
            ],
        ];
    }
}
