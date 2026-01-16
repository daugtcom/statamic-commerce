<?php

namespace Daugt\Commerce\Tests;

use Daugt\Commerce\Entries\InvoiceEntry;
use Daugt\Commerce\Services\InvoiceUrlResolver;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Stripe\StripeClient;

class InvoiceUrlResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $collection = CollectionFacade::make(InvoiceEntry::COLLECTION);
        $collection->entryClass(InvoiceEntry::class);
        $collection->save();
    }

    public function test_resolves_invoice_pdf_url_from_stripe(): void
    {
        config()->set('statamic.daugt-commerce.payment.provider', 'stripe');
        config()->set('statamic.daugt-commerce.payment.providers.stripe.config.secret', 'sk_test');

        $this->app->instance(StripeClient::class, new InvoiceFakeStripeClient('https://example.test/invoice.pdf'));

        $entry = Entry::make()->collection(InvoiceEntry::COLLECTION);
        $entry->set(InvoiceEntry::STRIPE_INVOICE_ID, 'in_123');
        $entry->saveQuietly();

        $entry = Entry::find($entry->id());
        $this->assertInstanceOf(InvoiceEntry::class, $entry);

        $resolver = new InvoiceUrlResolver();

        $this->assertSame('https://example.test/invoice.pdf', $resolver->resolve($entry));
    }
}

class InvoiceFakeStripeClient
{
    public InvoiceFakeInvoicesService $invoices;

    public function __construct(string $url)
    {
        $this->invoices = new InvoiceFakeInvoicesService($url);
    }
}

class InvoiceFakeInvoicesService
{
    public function __construct(private string $url)
    {
    }

    public function retrieve(string $invoiceId, array $params = []): object
    {
        return (object) ['invoice_pdf' => $this->url];
    }
}
