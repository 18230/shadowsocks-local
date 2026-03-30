# Packagist Publishing Guide

## Recommended First Release

- Repository: `https://github.com/18230/shadowsocks-local`
- Composer package name: `18230/shadowsocks-local`
- First Packagist version tag: `v0.1.1`

## Before You Submit

Make sure these are already true:

- The repository is public
- `composer.json` is committed at the repository root
- The `name` field is final, because Packagist package names cannot be changed later
- You do not set a `version` field in `composer.json`
- The release tag already exists in GitHub

## Submit the Package

1. Sign in to [Packagist](https://packagist.org/) and open the [Submit page](https://packagist.org/packages/submit).
2. Paste the public repository URL:
   `https://github.com/18230/shadowsocks-local`
3. Submit the package and wait for the first crawl to finish.

After submission, Packagist should discover the existing `v0.1.1` tag automatically.

## Configure Auto Updates

Packagist recommends enabling automatic updates from GitHub so new tags and pushes are indexed quickly.

Preferred Packagist-managed setup:

1. Sign in to Packagist with GitHub, or connect your GitHub account from your Packagist profile.
2. Make sure the Packagist GitHub application has access to this repository.
3. Trigger a manual account sync from Packagist if the hook is not installed automatically.

This repository also includes a workflow-based fallback:

- Workflow: `.github/workflows/packagist-sync.yml`
- Trigger: pushes to `main`, version tags, and manual runs
- Required GitHub secret: `PACKAGIST_API_TOKEN`
- Optional GitHub repository variable: `PACKAGIST_USERNAME`
  If you do not set it, the workflow falls back to the GitHub repository owner name.

The workflow uses Packagist's official generic update endpoint:

```text
POST https://packagist.org/api/update-package?username=USERNAME&apiToken=API_TOKEN
```

with a request body like:

```json
{"repository":{"url":"https://github.com/18230/shadowsocks-local"}}
```

This workflow keeps the package metadata fresh on Packagist, but the warning banner on the package page can still remain until a native GitHub webhook is configured.

Manual GitHub webhook setup is also possible with the values documented by Packagist:

- Payload URL: `https://packagist.org/api/github?username=PACKAGIST_USERNAME`
- Content type: `application/json`
- Secret: your Packagist API token
- Events: `push`

For this repository you can create or update the native GitHub webhook with one of the bundled scripts:

- PowerShell: `scripts/setup-packagist-github-hook.ps1`
- POSIX shell: `scripts/setup-packagist-github-hook.sh`

Example:

```powershell
$env:PACKAGIST_USERNAME = '18230'
$env:PACKAGIST_API_TOKEN = 'your-packagist-api-token'
.\scripts\setup-packagist-github-hook.ps1
```

```bash
export PACKAGIST_USERNAME=18230
export PACKAGIST_API_TOKEN=your-packagist-api-token
./scripts/setup-packagist-github-hook.sh
```

After the native webhook is in place, the "This package is not auto-updated" warning on Packagist should disappear.

## Verify the Published Package

After Packagist finishes indexing:

1. Open the package page and confirm the repository, README, and `v0.1.1` tag are visible.
2. Test installation in a clean directory:

```bash
composer require 18230/shadowsocks-local:^0.1
```

3. Run:

```bash
php vendor/bin/ss-local doctor --help
```

## Recommended Follow-up

- Add or verify Packagist version/download badges after the package page is live
- Create or update the GitHub Release for `v0.1.1`
- If you publish future tags, keep the changelog updated before tagging
