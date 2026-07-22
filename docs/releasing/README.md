# Releasing MigrationSystem

How to publish a new version. A "release" is a **git tag** (`vX.Y.Z`) plus a **published
GitHub Release**. The app's self-updater checks the **latest published GitHub Release** and
prompts running installs to update.

Choose your path:

- **[Manual release](manual.md)** — one command + publish the Release on GitHub's website
  (no extra tools). Start here.
- **[Release with GitHub CLI](github-cli.md)** — install `gh` once, then releasing is a
  single command with nothing to click.

---

## Prerequisites (both paths)

- You are on the **`main`** branch with a **clean working tree** (everything committed).
- `git`, `php`, and `composer` are available, and you can **push** to the repository.
- `node` + `npm` are installed (needed by the self-updater on the machines that update, not to
  cut the release).

## Versioning (Semantic Versioning)

Versions are `MAJOR.MINOR.PATCH`:

| Bump | When | Example |
| --- | --- | --- |
| **PATCH** | Bug fixes only, no new features | `1.0.0` → `1.0.1` |
| **MINOR** | New features, backward-compatible | `1.0.1` → `1.1.0` |
| **MAJOR** | Breaking changes | `1.1.0` → `2.0.0` |

> The v2 milestone (models + relationships) is a **MAJOR** bump.

## The one command

Both paths use:

```bash
composer release X.Y.Z
```

which automatically:

1. verifies the working tree is clean and the tag doesn't already exist,
2. writes `VERSION`,
3. rolls the `CHANGELOG.md` **[Unreleased]** section into a dated `## [X.Y.Z]` section,
4. commits `Release vX.Y.Z`,
5. creates the annotated tag `vX.Y.Z`,
6. pushes `main` **and** the tag,
7. **if `gh` is installed**, publishes the GitHub Release too (otherwise it prints the manual step).

Flags: `--no-push` (do everything locally, don't push), `--force` (skip the confirmation prompt).

## Quick checklist

1. Add your changes under **`## [Unreleased]`** in `CHANGELOG.md` as you work.
2. Commit & push everything (clean tree).
3. `composer release X.Y.Z`.
4. Publish the GitHub Release — **automatic with `gh`**, otherwise do it on the website
   (see [manual.md](manual.md)).

## After a release

Installs on an older version will see an **"Update available → vX.Y.Z"** prompt. Clicking
**Update** pulls `main`, installs dependencies, rebuilds assets, migrates, and clears caches in
the background (see the app's in-app Documentation → Self-update).

## Troubleshooting

- **"Working tree is not clean"** — commit or stash your changes first, then re-run.
- **"Tag vX.Y.Z already exists"** — pick a new version, or delete the tag
  (`git tag -d vX.Y.Z && git push origin :refs/tags/vX.Y.Z`) if it was a mistake.
- **Update prompt not showing** — a **published** GitHub Release must exist (a bare tag is not
  enough). The check is cached ~30 minutes.
