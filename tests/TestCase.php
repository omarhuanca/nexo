<?php

namespace Tests;

use App\Modules\Connector\Domain\Connector;
use App\Modules\Organization\Domain\Organization;
use App\Modules\Sale\Domain\Sale;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function createOrganization(array $attrs = []): Organization
    {
        return Organization::factory()->create($attrs);
    }

    protected function createConnector(?Organization $organization = null, array $attrs = []): Connector
    {
        if ($organization !== null) {
            $attrs['organization_id'] = $organization->getId();
        }
        return Connector::factory()->create($attrs);
    }

    protected function createSale(?Connector $connector = null, array $attrs = []): Sale
    {
        if ($connector !== null) {
            $attrs['organization_id'] = $connector->getOrganizationId();
            $attrs['connector_id'] = $connector->getId();
        }
        return Sale::factory()->create($attrs);
    }

    protected function createCompletedSale(?Connector $connector = null, array $overrides = []): Sale
    {
        $factory = Sale::factory()->completed();
        if ($connector !== null) {
            $factory = $factory->forConnector($connector);
        }
        return $factory->create($overrides);
    }
}
