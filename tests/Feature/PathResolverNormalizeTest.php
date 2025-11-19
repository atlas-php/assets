<?php

declare(strict_types=1);

namespace Atlas\Assets\Support {

    use Atlas\Assets\Tests\Support\PregReplaceHook;

    if (! function_exists(__NAMESPACE__.'\preg_replace')) {
        /**
         * @param  mixed  $pattern
         * @param  mixed  $replacement
         * @param  mixed  $subject
         * @param  int  $limit
         * @param  int  $count
         */
        function preg_replace($pattern, $replacement, $subject, $limit = -1, &$count = null)
        {
            if (PregReplaceHook::$shouldReturnNull) {
                PregReplaceHook::$shouldReturnNull = false;

                return null;
            }

            return \preg_replace($pattern, $replacement, $subject, $limit, $count);
        }
    }
}

namespace Atlas\Assets\Tests\Feature {

    use Atlas\Assets\Support\PathResolver;
    use Atlas\Assets\Tests\Support\PregReplaceHook;
    use Atlas\Assets\Tests\TestCase;
    use Illuminate\Http\UploadedFile;

    /**
     * Class PathResolverNormalizeTest
     *
     * Ensures normalizePath fallback logic is covered for error scenarios.
     * PRD Reference: Atlas Assets Overview — Path Resolution.
     */
    final class PathResolverNormalizeTest extends TestCase
    {
        public function test_normalize_path_recovers_original_input_when_regex_returns_null(): void
        {
            config()->set('atlas-assets.path.resolver', null);
            config()->set('atlas-assets.path.pattern', 'folder\\\\nested//{original_name}.{extension}');

            PregReplaceHook::$shouldReturnNull = true;

            try {
                $resolver = $this->app->make(PathResolver::class);

                $file = UploadedFile::fake()->create('Example.PDF', 5, 'application/pdf');

                self::assertSame('folder'.'\\\\'.'nested/example.pdf', $resolver->resolve($file));
            } finally {
                PregReplaceHook::$shouldReturnNull = false;
            }
        }
    }
}
