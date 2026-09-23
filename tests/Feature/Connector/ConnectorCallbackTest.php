<?php

namespace Tests\Feature\Connector;

use App\Modules\Connector\Domain\Connector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConnectorCallbackTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function creating_a_connector_with_callback_url_returns_the_secret_once(): void
    {
        $organization = $this->createOrganization();

        $response = $this->postJson('/api/connectors', [
            'organization_id' => $organization->getId(),
            'name' => 'Mi App',
            'callback_url' => 'https://app.example.com/webhooks/nexo',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.callback_url', 'https://app.example.com/webhooks/nexo');

        $secret = $response->json('data.callback_secret');
        $this->assertStringStartsWith('whsec_', $secret);

        $connector = Connector::find($response->json('data.id'));
        $this->assertSame($secret, $connector->getCallbackSecret());
        $this->assertNotSame($secret, $connector->getRawOriginal('callback_secret'), 'Secret must be stored encrypted');

        $this->getJson("/api/connectors/{$connector->getId()}")
            ->assertStatus(200)
            ->assertJsonMissingPath('data.callback_secret');
    }

    #[Test]
    public function rotating_the_secret_returns_a_new_one(): void
    {
        $connector = $this->createConnector($this->createOrganization(), [
            'callback_url' => 'https://app.example.com/hook',
            'callback_secret' => 'whsec_old',
        ]);

        $response = $this->putJson("/api/connectors/{$connector->getId()}", [
            'name' => $connector->getName(),
            'active' => true,
            'rotate_callback_secret' => true,
        ]);

        $response->assertStatus(200);
        $new = $response->json('data.callback_secret');
        $this->assertNotNull($new);
        $this->assertNotSame('whsec_old', $new);
        $this->assertSame($new, $connector->refresh()->getCallbackSecret());
    }

    #[Test]
    public function removing_the_callback_url_clears_the_secret(): void
    {
        $connector = $this->createConnector($this->createOrganization(), [
            'callback_url' => 'https://app.example.com/hook',
            'callback_secret' => 'whsec_old',
        ]);

        $this->putJson("/api/connectors/{$connector->getId()}", [
            'name' => $connector->getName(),
            'active' => true,
            'callback_url' => null,
        ])->assertStatus(200);

        $connector->refresh();
        $this->assertNull($connector->getCallbackUrl());
        $this->assertNull($connector->getCallbackSecret());
    }

    public static function unsafeUrls(): array
    {
        return [
            'plain http' => ['http://app.example.com/hook'],
            'localhost' => ['https://localhost/hook'],
            'private ip' => ['https://192.168.1.10/hook'],
            'loopback ip' => ['https://127.0.0.1/hook'],
            'metadata ip' => ['https://169.254.169.254/latest'],
        ];
    }

    #[Test]
    #[DataProvider('unsafeUrls')]
    public function rejects_unsafe_callback_urls(string $url): void
    {
        $this->postJson('/api/connectors', [
            'organization_id' => $this->createOrganization()->getId(),
            'name' => 'Mi App',
            'callback_url' => $url,
        ])->assertStatus(422);
    }
}
