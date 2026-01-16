<script src="https://js.stripe.com/v3/"></script>

<script type="module">
    const stripe = Stripe(@json($stripe_key));
    const shippingOptionsUrl = @json($stripe_shipping_url ?? null);

    const options = {
        clientSecret: @json($stripe_client_secret),
    };

    if (shippingOptionsUrl) {
        options.onShippingDetailsChange = async (event) => {
            const response = await fetch(shippingOptionsUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    checkout_session_id: event.checkoutSessionId,
                    shipping_details: event.shippingDetails,
                }),
            });

            const data = await response.json();

            if (data.type === 'error') {
                return { type: 'reject', errorMessage: data.message };
            }

            return { type: 'accept' };
        };
    }

    const checkout = await stripe.initEmbeddedCheckout(options);

    // Mount Checkout
    checkout.mount('#checkout');
</script>
<div id="checkout">
    <!-- Checkout will insert the payment form here -->
</div>
