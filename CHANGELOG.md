# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.13] - 2026-01-20

### Changes
- Add tags and load balancing support (#34)

## [1.0.12] - 2026-01-19

### Changes
- refactor: enhance CustomJobController and update API schemas
- chore: remove unused API documentation for endpoint comparison

## [1.0.11] - 2026-01-19

### Changes
- Remove API Endpoint Review document as it is no longer needed for endpoint comparison and implementation tracking.
- chore: improve code readability with additional blank lines

## [1.0.10] - 2026-01-19

### Changes
- chore: add blank lines for code readability in multiple files

## [1.0.9] - 2026-01-18

### Changes
- Enhance instance management and monitoring capabilities
- Add instance management and metrics collection features

## [1.0.8] - 2026-01-18

### Changes
- Implement load balancing and instance monitoring features

## [1.0.7] - 2026-01-18

### Changes
- Refactor tag synchronization in FileController

## [1.0.6] - 2026-01-18

### Changes
- Refactor tag synchronization in FileController
- Update tag synchronization logic in FileController
- Add default user data in migration for example media entries

## [1.0.5] - 2026-01-18

### Changes
- Refactor video job processing and improve API error handling

## [1.0.4] - 2026-01-18

### Changes
- Refactor video job processing and enhance API error handling

## [1.0.3] - 2026-01-18

### Changes
- Enhance video job processing and improve API error responses
- Refactor video processing logic and enhance error handling

## [1.0.2] - 2026-01-18

### Changes
- Add beat matching music video functionality

## [1.0.1] - 2026-01-14

### Changes
- Add automated release workflow with semantic versioning and changelog generation (#33)
- Create *.instructions.md
- Implement video operations, batch processing, and preset management APIs (#32)
- Add soundtrack ranges and attach-audio API (#25)
- Fix failing tests: authentication, queue config, and route ordering (#31)
- Rename SD instances to Generator instances and update services, migrations, and tests (#26)
- Performance optimization: N+1 queries, caching, and database indexes (#24)
- Remove obsolete implementation documentation files (#23)
- Update dependencies and fix esbuild security vulnerability (#22)
- Add ComfyUI workflow processing endpoints with separated client library (#21)
- Complete unfinished GPU credit enrollment, add performance indexes, and comprehensive tests (#20)
- Fix authentication status code and enum usage in payment tests (#19)
- Implementation: Authorization, security fixes, Stripe payment integration, and test coverage (#18)
- [WIP] Fix user data management tests for product table error (#17)
- Add frame extraction and video stitching for job extension (#16)
- [WIP] Add user data amount display and purge options (#15)
- Implement async video processing with file system watching and concurrent job support (#14)
- Fix: Restore SD instance routes lost in merge conflict
- Add missing controlnet column migration
- Fix test_generate_vid2vid_with_all_parameters 500 error
- Address code review feedback: remove extra blank lines and use custom exception
- Fix tests - remove validation tests that conflict with middleware, all tests passing
- Fix remaining test failures
- Add SD instance management: model, migration, controller, service, and tests
- Change all vimage references to mage throughout the codebase
- Fix test failures in VideojobGenerateParametersTest
- Initial plan
- Initial plan
- Address code review feedback
- Add comprehensive test coverage and performance optimizations
- Fix CI: Add git identity configuration to conflict-check workflow
- Add improvements summary document
- Address code review feedback
- Add comprehensive code review findings document
- Fix route duplication and improve code quality
- Initial plan
- Add CI workflow and harden video processing helpers
- Fix missing braces after else statements for consistent code style
- Fix type hints, duplicate fields, routes, and coding style
- Initial plan
- Rename Docker directory to lowercase
- Add processing status endpoints and DeforumationQT UI
- Update routes more logical
- Update README.md
- Update docker-compose.yml
- Add soundtrack uploads and refresh docker images
- Fix video job endpoints and extend API coverage
- Align deforum unique ID tests with extension context
- Add deforum extension tests and update docs
- Improve deforum queue handling and extend support
- Add Python script tests to CI workflow
- Add load-balanced WebUI client support and tests
- Add WebUI API documentation
- Add helper script for rebasing onto main
- Add merge conflict check workflow
- Improve merge conflict handling guidance
- Add rebase guidance to README
- Refactor video job flow and add docker helper
- Add feature tests for videojob API endpoints
- Stuff
- Update
- Stuff
- Fixes for processing deforum
- Fixes for deforum
- Deforum
- Gixes
- Update deforum
- Update deforum support
- Add deforum
- Fixes
- Replace notfound image
- Update
- Move from bitbucket

## [1.0.0] - 2026-01-14

### Added
- Initial release
- Video operations, batch processing, and preset management APIs
