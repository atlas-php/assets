<?php

declare(strict_types=1);

namespace Atlas\Assets\Tests\Feature;

use Atlas\Assets\Models\Asset;
use Atlas\Assets\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * Class AssetModelTest
 *
 * Verifies the Atlas Assets migration, model configuration, and factories.
 * PRD Reference: Atlas Assets Overview — Database Schema.
 */
final class AssetModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_assets_table_is_created_with_expected_columns(): void
    {
        $table = config('atlas-assets.tables.assets');
        $connection = config('database.default');

        self::assertTrue(Schema::connection($connection)->hasTable($table));

        $columns = [
            'id',
            'group_id',
            'user_id',
            'model_type',
            'model_id',
            'type',
            'sort_order',
            'file_mime_type',
            'file_ext',
            'file_path',
            'file_size',
            'name',
            'original_file_name',
            'label',
            'category',
            'created_at',
            'updated_at',
            'deleted_at',
        ];

        foreach ($columns as $column) {
            self::assertTrue(
                Schema::connection($connection)->hasColumn($table, $column),
                sprintf('Failed asserting column [%s] exists.', $column)
            );
        }
    }

    public function test_asset_model_respects_configured_table_and_connection(): void
    {
        config()->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        config()->set('atlas-assets.tables.assets', 'custom_assets');
        config()->set('atlas-assets.database.connection', 'testbench');

        $asset = new Asset;

        self::assertSame('custom_assets', $asset->getTable());
        self::assertSame('testbench', $asset->getConnectionName());
    }

    public function test_asset_factory_creates_records(): void
    {
        $asset = Asset::factory()->create();

        self::assertNotNull($asset->id);
        self::assertNotEmpty($asset->file_path);
        self::assertIsInt($asset->sort_order);
        self::assertIsString($asset->file_mime_type);
        self::assertIsString($asset->file_ext);
        self::assertTrue($asset->type === null || is_int($asset->type));
    }

    public function test_has_label_category_and_owner_helpers(): void
    {
        $user = new class
        {
            public int $id = 55;
        };

        config()->set('auth.providers.users.model', $user::class);

        $asset = Asset::factory()->make([
            'label' => 'hero',
            'category' => 'images',
            'user_id' => 5,
        ]);

        self::assertTrue($asset->hasLabel());
        self::assertTrue($asset->hasCategory());
        self::assertTrue($asset->hasOwner());

        $asset->label = null;
        $asset->category = null;
        $asset->user_id = null;

        self::assertFalse($asset->hasLabel());
        self::assertFalse($asset->hasCategory());
        self::assertFalse($asset->hasOwner());
    }

    public function test_model_and_user_relationships_can_be_resolved(): void
    {
        $asset = new Asset;
        $asset->model_type = RelationshipModel::class;
        $asset->model_id = 1;

        self::assertInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphTo::class, $asset->model());
        self::assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $asset->user());
    }
}

/**
 * @internal helper for relation tests
 */
class RelationshipModel extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'relationship_models';
}
