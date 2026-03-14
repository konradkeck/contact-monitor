<?php

namespace Tests\Feature;

use App\Models\BrandProduct;
use App\Models\SynchronizerServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandProductsTest extends TestCase
{
    use RefreshDatabase;

    private function createServer(): void
    {
        SynchronizerServer::create([
            'name' => 'Test Server',
            'url' => 'http://localhost:8080',
            'api_token' => 'test-token',
            'ingest_secret' => 'test-secret',
        ]);
    }

    public function test_segmentation_index_returns_200(): void
    {
        $this->createServer();

        $response = $this->get(route('segmentation.index'));

        $response->assertStatus(200);
    }

    public function test_segmentation_index_shows_products(): void
    {
        $this->createServer();

        BrandProduct::create(['name' => 'SuperSaaS', 'slug' => 'supersaas']);

        $response = $this->get(route('segmentation.index'));

        $response->assertStatus(200);
        $response->assertSee('SuperSaaS');
    }

    public function test_segmentation_create_returns_200(): void
    {
        $this->createServer();

        $response = $this->get(route('segmentation.create'));

        $response->assertStatus(200);
    }

    public function test_segmentation_store_creates_product(): void
    {
        $this->createServer();

        $response = $this->post(route('segmentation.store'), [
            'name' => 'New Product',
            'slug' => 'new-product',
        ]);

        $response->assertRedirect(route('segmentation.index'));
        $this->assertDatabaseHas('brand_products', ['name' => 'New Product', 'slug' => 'new-product']);
    }

    public function test_segmentation_store_validation_fails_without_name(): void
    {
        $this->createServer();

        $response = $this->post(route('segmentation.store'), []);

        $response->assertSessionHasErrors('name');
    }

    public function test_segmentation_edit_returns_200(): void
    {
        $this->createServer();

        $product = BrandProduct::create(['name' => 'Editable', 'slug' => 'editable']);

        $response = $this->get(route('segmentation.edit', $product));

        $response->assertStatus(200);
        $response->assertSee('Editable');
    }

    public function test_segmentation_update_changes_product(): void
    {
        $this->createServer();

        $product = BrandProduct::create(['name' => 'Old Name', 'slug' => 'old-name']);

        $response = $this->put(route('segmentation.update', $product), [
            'name' => 'New Name',
            'slug' => 'new-name',
        ]);

        $response->assertRedirect(route('segmentation.index'));
        $this->assertDatabaseHas('brand_products', ['name' => 'New Name']);
    }

    public function test_segmentation_destroy_deletes_product(): void
    {
        $this->createServer();

        $product = BrandProduct::create(['name' => 'To Delete', 'slug' => 'to-delete']);

        $response = $this->delete(route('segmentation.destroy', $product));

        $response->assertRedirect(route('segmentation.index'));
        $this->assertDatabaseMissing('brand_products', ['name' => 'To Delete', 'deleted_at' => null]);
    }
}
