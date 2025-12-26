<script src="https://js.stripe.com/v3/"></script>

<script type="module">
    const stripe = Stripe("{{$stripe_key}}");

    const checkout = await stripe.initEmbeddedCheckout({
        clientSecret: "{{$stripe_client_secret}}",
    });

    // Mount Checkout
    checkout.mount('#checkout');
</script>
<div id="checkout" class="w-full h-full">
    <!-- Checkout will insert the payment form here -->
</div>