<?php

namespace Daugt\Commerce\Actions;

use Daugt\Commerce\Entries\InvoiceEntry;
use Illuminate\Support\Collection;
use Statamic\Actions\Action;
use Statamic\Actions\Concerns\MakesZips;
use Statamic\Support\Str;

class DownloadInvoicesZip extends Action
{
    use MakesZips;

    protected $icon = 'download';
    protected $confirm = false;
    private ?Collection $files = null;

    public static function title()
    {
        return __('PDF Download');
    }

    public function visibleTo($item)
    {
        return config('statamic.daugt-commerce.payment.provider') === 'stripe'
            && $item instanceof InvoiceEntry;
    }

    public function authorize($user, $invoice)
    {
        return $user->can('view', $invoice);
    }

    public function run($items, $values)
    {
        $files = $this->buildFiles($items);

        if ($files->isEmpty()) {
            throw new \Exception(__('No invoice PDFs available for download.'));
        }

        $this->files = $files;
    }

    public function download($items, $values)
    {
        if (! $this->files || $this->files->isEmpty()) {
            return false;
        }

        return $this->makeZipResponse($this->zipName($items), $this->files);
    }

    private function buildFiles(Collection $items): Collection
    {
        $files = collect();
        $index = 1;

        foreach ($items as $invoice) {
            if (! $invoice instanceof InvoiceEntry) {
                $index++;
                continue;
            }

            $pdfUrl = $this->resolveInvoicePdfUrl($invoice);
            if (! $pdfUrl) {
                $index++;
                continue;
            }

            $stream = @fopen($pdfUrl, 'r');
            if (! is_resource($stream)) {
                $index++;
                continue;
            }

            $base = $this->filenameBase($invoice, $index);
            $filename = $this->uniqueFilename($files, $base, 'pdf');
            $files->put($filename, $stream);
            $index++;
        }

        return $files;
    }

    private function resolveInvoicePdfUrl(InvoiceEntry $invoice): ?string
    {
        return $invoice->invoiceUrl();
    }

    private function filenameBase(InvoiceEntry $invoice, int $index): string
    {
        $identifier = $invoice->invoiceNumber() ?? $invoice->stripeInvoiceId() ?? (string) $invoice->id();
        $identifier = trim((string) $identifier);
        $slug = Str::slug($identifier, '-');

        if ($slug === '') {
            $slug = (string) $index;
        }

        return "invoice-{$slug}";
    }

    private function uniqueFilename(Collection $files, string $base, string $extension): string
    {
        $filename = "{$base}.{$extension}";
        $suffix = 2;

        while ($files->has($filename)) {
            $filename = "{$base}-{$suffix}.{$extension}";
            $suffix++;
        }

        return $filename;
    }

    private function zipName(Collection $items): string
    {
        if ($items->count() === 1) {
            $invoice = $items->first();
            if ($invoice instanceof InvoiceEntry) {
                $base = $this->filenameBase($invoice, 1);
                return "{$base}.zip";
            }
        }

        return 'invoices.zip';
    }
}
