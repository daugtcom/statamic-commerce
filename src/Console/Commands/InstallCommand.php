<?php

namespace Daugt\Commerce\Console\Commands;

use Daugt\Commerce\Blueprints\ProductBlueprint;
use Daugt\Commerce\Blueprints\ProductCollection;
use Daugt\Commerce\Blueprints\CategoryBlueprint;
use Daugt\Commerce\Blueprints\CategoryTaxonomy;
use Daugt\Commerce\Console\AsciiArt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Statamic\Console\RunsInPlease;
use Statamic\Facades\Addon;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;

class InstallCommand extends Command {
    use RunsInPlease;


    protected $signature = 'statamic:daugt-commerce:install';

    protected $description = 'Installs Commerce Addon.';

    public function handle(
        ProductCollection $productCollection,
        ProductBlueprint $productBlueprint,
        CategoryTaxonomy $categoryTaxonomy,
        CategoryBlueprint $categoryBlueprint
    ): void
    {
        $this->output->write((new AsciiArt())());

        if (! $this->ensureStripeSecretConfigured()) {
            return;
        }

        $this->createStructures(
            $productCollection,
            $productBlueprint,
            $categoryTaxonomy,
            $categoryBlueprint
        );

        Artisan::call('statamic:daugt-commerce:fetch-stripe-tax-codes');
    }

    private function createStructures(
        ProductCollection $productCollection,
        ProductBlueprint $productBlueprint,
        CategoryTaxonomy $categoryTaxonomy,
        CategoryBlueprint $categoryBlueprint
    ): self {
        $includeAccess = $this->accessIsReady();
        $accessCollections = $includeAccess ? $this->accessTargetCollections() : [];

        if ($includeAccess && empty($accessCollections)) {
            $this->warn('Access addon is installed, but no target collections are configured. Access fields were skipped.');
        }

        $collection = $productCollection();
        $collection->save();

        $blueprint = $productBlueprint($accessCollections, $includeAccess);
        $blueprint->setHandle(sprintf(
            'collections/%s/%s',
            $collection->handle(),
            Str::singular($collection->handle())
        ));
        Blueprint::save($blueprint);

        $taxonomy = $categoryTaxonomy();
        $taxonomy->save();

        $taxonomyBlueprint = $categoryBlueprint();
        $taxonomyBlueprint->setHandle(sprintf(
            'taxonomies/%s/%s',
            $taxonomy->handle(),
            Str::singular($taxonomy->handle())
        ));
        Blueprint::save($taxonomyBlueprint);

        $this->info('Collections, taxonomies, and blueprints created!');

        return $this;
    }

    private function ensureStripeSecretConfigured(): bool
    {
        if (config('statamic.daugt-commerce.stripe.secret')) {
            return true;
        }

        $this->warn('STRIPE_SECRET is not set. Set it in your .env and re-run this command.');
        return false;
    }

    private function accessIsReady(): bool
    {
        $addon = Addon::get('daugtcom/statamic-access');

        if (! $addon) {
            return false;
        }

        return Collection::find('entitlements') !== null;
    }

    private function accessTargetCollections(): array
    {
        $collections = config('statamic.daugt-access.entitlements.target_collections', []);

        if (! is_array($collections)) {
            return [];
        }

        $collections = array_filter($collections, fn ($value) => is_string($value) && $value !== '');

        return array_values(array_filter(
            $collections,
            fn (string $handle) => Collection::find($handle) !== null
        ));
    }

}
