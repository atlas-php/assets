# Atlas Assets Roadmap

This roadmap translates the Atlas Assets PRD into a practical implementation plan. We will work through the phases sequentially, ensuring every deliverable maps directly to the PRD responsibilities and constraints.

## Phase 1 — Package Foundation

* Scaffold the Composer package (namespace, service provider, config publishing) following AGENTS.md.
* Define base configuration (`config/atlas_assets.php`) with disk, visibility, deletion behavior, and path resolver placeholders.
* Document installation instructions and contribution requirements.

## Phase 2 — Database Schema & Model

* Create the `assets` migration with all PRD-defined columns and soft deletes.
* Implement the `Asset` Eloquent model with casts, relationships (user + morph), and attribute helpers.
* Add factories for testing scenarios.

## Phase 3 — Storage Integration & Path Resolution

* Implement a path resolver service that supports placeholder patterns and callback overrides.
* Ensure resolver tolerates null models and missing attributes while providing deterministic outputs.
* Wire resolver into configuration with validation of placeholders and callbacks.

## Phase 4 — Upload & Creation APIs

* Build upload services for generic uploads and model-specific uploads.
* Persist metadata into the `assets` table, including polymorphic data and user-provided labels/categories.
* Store the physical file via the configured disk and resolved path.

## Phase 5 — Retrieval APIs

* Implement `find`, `listForModel`, `listForUser`, `download`, `temporaryUrl`, and `exists`.
* Support filtering options (label/category) to align with consumer needs.
* Ensure temporary URLs respect disk capabilities (S3 vs. others) and fall back gracefully.

## Phase 6 — Update & Replacement APIs

* Implement `replace`, `rename`, `updateLabel`, and `updateCategory`.
* Handle deletion (optional) of displaced files during replacements per configuration.
* Maintain consistency between metadata and stored files.

## Phase 7 — Removal & Purging

* Provide `delete` with optional physical file removal and enforce soft deletes.
* Implement `purge` to permanently delete soft-deleted assets and optionally clear disk artifacts.
* Ensure cleanup respects configuration flags for file deletion.

## Phase 8 — Configuration Enhancements

* Finalize publishable config defaults plus documentation around disk selection, visibility, and path customization.
* Offer helpers for consumers to override path resolution via callbacks or custom services.

## Phase 9 — Quality Assurance

* Cover services with Pest tests (unit + integration) using Orchestra Testbench.
* Add unhappy-path tests (invalid resolver, storage failures, missing files).
* Run Pint, Larastan, and the test suite in CI and before every release.

## Phase 10 — Documentation & Release

* Expand README with installation, configuration, usage, and examples per PRD.
* Maintain CHANGELOG entries for each milestone.
* Tag releases once features reach parity with PRD scope.
