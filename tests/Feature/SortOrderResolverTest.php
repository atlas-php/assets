<?php

declare(strict_types=1);

namespace Atlas\Assets\Tests\Feature;

use Atlas\Assets\Models\Asset;
use Atlas\Assets\Support\SortOrderResolver;
use Atlas\Assets\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Class SortOrderResolverTest
 *
 * Covers configuration-driven sort scope handling and callback normalization.
 * PRD Reference: Atlas Assets Overview — Sorting & Ordering.
 */
final class SortOrderResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_next_uses_custom_callback_and_normalizes_stringable_values(): void
    {
        config()->set('atlas-assets.sort.resolver', static function (): \Stringable {
            return new class implements \Stringable
            {
                public function __toString(): string
                {
                    return ' 15 ';
                }
            };
        });

        $resolver = $this->app->make(SortOrderResolver::class);

        self::assertSame(15, $resolver->next(null));

        config()->set('atlas-assets.sort.resolver', null);
    }

    public function test_next_returns_zero_when_callback_returns_blank_string(): void
    {
        config()->set('atlas-assets.sort.resolver', static fn (): string => '   ');

        $resolver = $this->app->make(SortOrderResolver::class);

        self::assertSame(0, $resolver->next(null));

        config()->set('atlas-assets.sort.resolver', null);
    }

    public function test_next_returns_zero_when_callback_returns_non_numeric_string(): void
    {
        config()->set('atlas-assets.sort.resolver', static fn (): string => 'invalid');

        $resolver = $this->app->make(SortOrderResolver::class);

        self::assertSame(0, $resolver->next(null));

        config()->set('atlas-assets.sort.resolver', null);
    }

    public function test_increment_from_scopes_uses_normalized_scope_names(): void
    {
        config()->set('atlas-assets.sort.resolver', null);
        config()->set('atlas-assets.sort.scopes', [
            ' group_id ',
            new class implements \Stringable
            {
                public function __toString(): string
                {
                    return ' label ';
                }
            },
            '',
            123,
        ]);

        Asset::factory()->create([
            'group_id' => 10,
            'label' => 'hero',
            'sort_order' => 2,
        ]);

        Asset::factory()->create([
            'group_id' => 10,
            'label' => 'hero',
            'sort_order' => 4,
        ]);

        $resolver = $this->app->make(SortOrderResolver::class);

        $next = $resolver->next(null, [
            'group_id' => 10,
            'label' => 'hero',
        ]);

        self::assertSame(5, $next);

        config()->set('atlas-assets.sort.scopes', ['model_type', 'model_id', 'type']);
    }
}
