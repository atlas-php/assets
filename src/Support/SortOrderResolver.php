<?php

declare(strict_types=1);

namespace Atlas\Assets\Support;

use Atlas\Assets\Services\AssetModelService;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;

/**
 * Class SortOrderResolver
 *
 * Calculates the next sequential sort order for an asset scoped by configuration
 * or a custom callback.
 * PRD Reference: Atlas Assets Overview — Sorting & Ordering.
 */
class SortOrderResolver
{
    public function __construct(
        private readonly Repository $config,
        private readonly AssetModelService $assetModelService,
    ) {}

    /**
     * Determine the next sort order value using the configured strategy.
     *
     * @param  array<string, mixed>  $context
     */
    public function next(?Model $model, array $context = []): ?int
    {
        $callback = $this->config->get('atlas-assets.sort.resolver');

        if (is_callable($callback)) {
            $value = $callback($model, $context);

            return $this->normalizeSortOrder($value);
        }

        return $this->incrementFromScopes($context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function incrementFromScopes(array $context): ?int
    {
        $scopes = $this->configuredScopes();

        if ($scopes === []) {
            return null;
        }

        $query = $this->assetModelService->query();

        foreach ($scopes as $column) {
            $value = $context[$column] ?? null;

            if ($value === null || $value === '') {
                $query->whereNull($column);
            } else {
                $query->where($column, $value);
            }
        }

        $max = $query->max('sort_order');

        if (! is_numeric($max)) {
            return 0;
        }

        $order = (int) $max + 1;

        return $order < 0 ? 0 : $order;
    }

    /**
     * @return array<int, string>
     */
    private function configuredScopes(): array
    {
        $scopes = $this->config->get('atlas-assets.sort.scopes');

        if (! is_array($scopes) || $scopes === []) {
            return [];
        }

        return array_values(array_filter(array_map(static function ($scope): ?string {
            if ($scope instanceof \Stringable) {
                $scope = (string) $scope;
            }

            if (! is_string($scope)) {
                return null;
            }

            $value = trim($scope);

            return $value === '' ? null : $value;
        }, $scopes)));
    }

    private function normalizeSortOrder(mixed $value): int
    {
        if ($value instanceof \Stringable) {
            $value = (string) $value;
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                $value = null;
            }
        }

        if ($value === null || ! is_numeric($value)) {
            return 0;
        }

        $order = (int) $value;

        return $order < 0 ? 0 : $order;
    }
}
