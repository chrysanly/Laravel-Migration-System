# Manual release (no extra tools)

This is the standard flow using only `git` + `composer` and GitHub's website. For a
fully automated flow, see [Release with GitHub CLI](github-cli.md).

Prerequisites and version rules: see the [releasing overview](README.md).

---

## Step 1 — Record what changed

Open `CHANGELOG.md` and add your changes under the **`## [Unreleased]`** heading, e.g.:

```markdown
## [Unreleased]

### Added
- Short description of a new feature.

### Fixed
- Short description of a bug fix.
```

Use `Added` / `Changed` / `Fixed` / `Removed` as needed. You don't add the version number
here — the release command does that.

## Step 2 — Commit and push everything

The release command requires a **clean working tree**:

```bash
git add -A
git commit -m "Describe your changes"
git push
```

Check it's clean:

```bash
git status        # should say "nothing to commit, working tree clean"
git branch        # should show * main
```

## Step 3 — Cut the release

Pick the new version (see the versioning table in the [overview](README.md)) and run:

```bash
composer release 1.1.0
```

Confirm the prompt. This will:

- write `VERSION` → `1.1.0`
- move `[Unreleased]` in `CHANGELOG.md` into `## [1.1.0] - <today>`
- commit `Release v1.1.0`
- create the tag `v1.1.0`
- push `main` and the tag

Because `gh` is not installed in this flow, it will finish by printing a reminder to publish
the Release on GitHub (Step 4).

## Step 4 — Publish the GitHub Release

1. Go to the repository on GitHub:
   `https://github.com/chrysanly/Laravel-Migration-System`
2. Click **Releases** (right sidebar) → **Draft a new release**.
3. **Choose a tag** → select the existing tag **`v1.1.0`**.
4. **Release title:** `v1.1.0`.
5. **Description:** paste the notes for this version from `CHANGELOG.md`
   (or click **Generate release notes**).
6. Leave "Set as the latest release" checked → click **Publish release**.

That's it. The self-updater looks at the **latest published release**, so within ~30 minutes
(cache) installs on older versions will be prompted to update.

---

## Verifying

- On GitHub, the release shows under **Releases** and is marked **Latest**.
- In the app, the version check endpoint reflects it:
  `https://migration-system.test/system/update/check` →
  `{"current":"1.1.0","latest":"1.1.0","update_available":false}` on this (now-updated) machine.

## Manual fallback (without `composer release`)

If you ever need to do it by hand:

```bash
# 1. bump the version file
echo 1.1.0 > VERSION
# 2. edit CHANGELOG.md: rename [Unreleased] to ## [1.1.0] - YYYY-MM-DD and add a new [Unreleased]
# 3. commit, tag, push
git add VERSION CHANGELOG.md
git commit -m "Release v1.1.0"
git tag -a v1.1.0 -m "v1.1.0"
git push origin main --follow-tags
# 4. publish the Release on GitHub (Step 4 above)
```

## Troubleshooting

- **"Working tree is not clean"** → commit/stash first (Step 2).
- **"Tag v1.1.0 already exists"** → choose a new version, or remove the bad tag:
  `git tag -d v1.1.0 && git push origin :refs/tags/v1.1.0`.
- **Push asks for credentials** → sign in to GitHub when prompted (or configure a credential
  helper / personal access token).
