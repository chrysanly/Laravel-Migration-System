# Release with GitHub CLI (`gh`)

With the GitHub CLI installed and authenticated, `composer release` publishes the GitHub
Release **automatically** — no website clicks. Set this up once.

Prerequisites and version rules: see the [releasing overview](README.md).

---

## Step 1 — Install `gh` (Windows)

Pick one:

**winget (recommended)**

```powershell
winget install --id GitHub.cli
```

**Scoop**

```powershell
scoop install gh
```

**Installer**

Download and run the Windows installer from <https://cli.github.com/>.

Then **open a new terminal** and verify:

```bash
gh --version
```

## Step 2 — Authenticate (once)

```bash
gh auth login
```

Answer the prompts:

- **What account?** → `GitHub.com`
- **Preferred protocol?** → `HTTPS`
- **Authenticate Git with your GitHub credentials?** → `Yes`
- **How to authenticate?** → `Login with a web browser` (copy the one-time code, paste it in
  the browser that opens).

Verify:

```bash
gh auth status        # should show you're logged in to github.com
```

> The token needs access to this repository (the default `repo` scope from `gh auth login`
> is sufficient).

## Step 3 — Release

Same one command as always:

```bash
composer release 1.1.0
```

Because `gh` is now installed, after pushing the tag it also runs:

```bash
gh release create v1.1.0 --title v1.1.0 --generate-notes
```

so the GitHub Release is published for you. **Nothing to click.**

## Verifying

```bash
gh release view v1.1.0          # shows the published release
gh release list                 # v1.1.0 marked Latest
```

Or in the app: `https://migration-system.test/system/update/check`.

## Doing just the release step manually with `gh`

If a tag already exists and you only want to publish/refresh the Release:

```bash
# generated notes
gh release create v1.1.0 --title "v1.1.0" --generate-notes

# or notes from the changelog / a file
gh release create v1.1.0 --title "v1.1.0" --notes-file NOTES.md
```

## Troubleshooting

- **`gh: command not found`** → open a **new** terminal after installing (PATH refresh), or
  reinstall.
- **`gh auth status` shows not logged in** → run `gh auth login` again.
- **Release step fails but tag pushed** → publish manually on the website (see
  [manual.md](manual.md) Step 4); your tag is already there.
