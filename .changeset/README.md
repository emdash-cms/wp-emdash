# Changesets

A pull request that changes the plugin adds a changeset:

```bash
pnpm install
pnpm changeset
```

Pick the bump (patch for fixes, minor for new features) and write one line for the changelog. On merge, the release workflow collects pending changesets into a release pull request. Merging that pull request publishes the new version as a GitHub release with the plugin zip.
