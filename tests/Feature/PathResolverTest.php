<?php

declare(strict_types=1);

namespace Atlas\Assets\Tests\Feature;

use Atlas\Assets\Support\PathResolver;
use Atlas\Assets\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Class PathResolverTest
 *
 * Exercises the configurable storage path resolver logic.
 * PRD Reference: Atlas Assets Overview — Path Resolution.
 */
final class PathResolverTest extends TestCase
{
    public function test_pattern_resolver_substitutes_known_placeholders(): void
    {
        config()->set('atlas-assets.path.resolver', null);
        config()->set('atlas-assets.path.pattern', '{model_type}/{model_id}/{group_id}/{user_id}/{original_name}.{extension}');

        $model = new ExampleModel;
        $model->id = 42;

        $file = UploadedFile::fake()->create('Final Report.PDF', 120, 'application/pdf');

        $resolver = $this->app->make(PathResolver::class);

        $path = $resolver->resolve($file, $model, ['user_id' => 7, 'group_id' => 19]);

        self::assertSame('example_model/42/19/7/final_report.pdf', $path);
    }

    public function test_pattern_resolver_handles_null_model_and_missing_attributes(): void
    {
        config()->set('atlas-assets.path.resolver', null);
        config()->set('atlas-assets.path.pattern', 'uploads/{model_type}/{model_id}/{user_id}/{uuid}.{extension}');

        $file = UploadedFile::fake()->create('image.PNG', 10, 'image/png');

        $resolver = $this->app->make(PathResolver::class);

        $path = $resolver->resolve($file, null, []);

        self::assertMatchesRegularExpression('/^uploads\/[a-f0-9-]{36}\.png$/', $path);
    }

    public function test_pattern_resolver_supports_date_and_random_placeholders(): void
    {
        config()->set('atlas-assets.path.resolver', null);
        config()->set('atlas-assets.path.pattern', 'archives/{date:Y/m}/{random}.{extension}');

        Carbon::setTestNow(Carbon::create(2024, 1, 15, 12));

        $file = UploadedFile::fake()->create('archive.zip', 10);

        $resolver = $this->app->make(PathResolver::class);

        $path = $resolver->resolve($file);

        self::assertMatchesRegularExpression('/^archives\/2024\/01\/[a-z0-9]{16}\.zip$/', $path);
    }

    public function test_callback_resolver_overrides_pattern(): void
    {
        config()->set('atlas-assets.path.pattern', '{uuid}.{extension}');
        config()->set('atlas-assets.path.resolver', static function ($model, UploadedFile $file): string {
            return 'custom/'.$file->getClientOriginalName();
        });

        $file = UploadedFile::fake()->create('Report.docx', 20);

        $resolver = $this->app->make(PathResolver::class);

        self::assertSame('custom/Report.docx', $resolver->resolve($file));
    }

    public function test_pattern_resolver_uses_file_name_placeholder(): void
    {
        config()->set('atlas-assets.path.resolver', null);
        config()->set('atlas-assets.path.pattern', '{file_name}.{extension}');

        $file = UploadedFile::fake()->create('Quarterly Report 2024.PDF', 10, 'application/pdf');

        $resolver = $this->app->make(PathResolver::class);

        self::assertSame('quarterly_report_2024.pdf', $resolver->resolve($file));
    }

    public function test_callback_resolver_must_return_non_empty_string(): void
    {
        config()->set('atlas-assets.path.resolver', static fn (): string => '');

        $file = UploadedFile::fake()->create('empty.txt', 1);

        $resolver = $this->app->make(PathResolver::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('path resolver must return a non-empty string');

        $resolver->resolve($file);
    }
}

/**
 * @internal helper for tests
 */
class ExampleModel extends Model
{
    protected $table = 'examples';

    /**
     * @var array<int, string>
     */
    protected $guarded = [];
}
