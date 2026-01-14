# Release Automation

This repository uses automated release management via GitHub Actions.

## How It Works

When a pull request is merged to the `main` or `master` branch, the release workflow automatically:

1. **Determines the version bump** based on commit messages:
   - **Major version bump (X.0.0)**: Commit messages containing `BREAKING CHANGE`, `breaking:`, or `major:`
   - **Minor version bump (x.Y.0)**: Commit messages containing `feat:`, `feature:`, or `minor:`
   - **Patch version bump (x.y.Z)**: All other commits (default)

2. **Updates the VERSION file** with the new version number

3. **Generates changelog** from commit messages since the last release

4. **Updates CHANGELOG.md** with the new version and changes

5. **Creates a GitHub Release** with:
   - Tag: `v{version}` (e.g., `v1.2.3`)
   - Release notes from the changelog
   - Automatic asset publishing

6. **Commits the updated files** back to the repository

## Commit Message Convention

To control the version bump, use conventional commit prefixes:

- `feat:` or `feature:` - New features (minor version bump)
- `fix:` - Bug fixes (patch version bump)
- `BREAKING CHANGE:` or `breaking:` - Breaking changes (major version bump)
- `major:` - Force major version bump

Examples:
```
feat: add new user authentication API
fix: resolve database connection timeout issue
BREAKING CHANGE: remove deprecated v1 API endpoints
```

## Manual Release

The workflow runs automatically on merge to main/master. No manual intervention is needed.

## Current Version

The current version is tracked in the `VERSION` file at the root of the repository.

## Changelog

All releases are documented in `CHANGELOG.md` following the [Keep a Changelog](https://keepachangelog.com/) format.
