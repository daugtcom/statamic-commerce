<?php

namespace Daugt\Commerce\Blueprints;

use Statamic\Facades\Blueprint as BlueprintFacade;
use Statamic\Fields\Blueprint as StatamicBlueprint;

class CategoryBlueprint
{
    public function __invoke(): StatamicBlueprint
    {
        $blueprint = BlueprintFacade::makeFromFields([
            'description' => [
                'type' => 'markdown',
                'localizable' => true,
                'display' => 'daugt-commerce::taxonomies.categories.fields.description',
            ],
            'image' => [
                'container' => 'assets',
                'max_files' => 1,
                'type' => 'assets',
                'display' => 'daugt-commerce::taxonomies.categories.fields.image',
            ],
        ]);

        $blueprint->ensureFieldPrepended('title', [
            'type' => 'text',
            'required' => true,
            'validate' => ['required'],
            'display' => 'daugt-commerce::taxonomies.categories.fields.title',
        ]);

        $blueprint->ensureField('slug', [
            'type' => 'slug',
            'required' => true,
            'validate' => ['required', 'max:200'],
            'display' => 'daugt-commerce::taxonomies.categories.fields.slug',
        ], 'sidebar');

        $contents = $blueprint->contents();
        $contents['title'] = 'daugt-commerce::taxonomies.categories.blueprint.title';
        $blueprint->setContents($contents);

        return $blueprint;
    }
}
