<?php

namespace Daugt\Commerce\Blueprints;
use Statamic\Facades\Blueprint as BlueprintFacade;
use Statamic\Fields\Blueprint as StatamicBlueprint;

class ProductBlueprint
{
    public function __invoke(): StatamicBlueprint
    {
        return BlueprintFacade::makeFromFields([

        ]);
    }
}
