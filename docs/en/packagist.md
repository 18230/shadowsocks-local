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

Preferred setup:

1. Sign in to Packagist with GitHub, or connect your GitHub account from your Packagist profile.
2. Make sure the Packagist GitHub application has access to this repository.
3. Trigger an account sync from Packagist if the hook is not installed automatically.

Manual webhook setup is also possible:

- Payload URL: `https://packagist.org/api/github?username=PACKAGIST_USERNAME`
- Content type: `application/json`
- Secret: your Packagist API token
- Events: `push`

## Verify the Published Package

After Packagist finishes indexing:

1. Open the package page and confirm the repository, README, and `v0.1.0` tag are visible.
2. Test installation in a clean directory:

```bash
composer require 18230/shadowsocks-local:^0.1
```

3. Run:

```bash
php vendor/bin/ss-local doctor --help
```

## Recommended Follow-up

- Add Packagist version/download badges after the package page is live
- Create or update the GitHub Release for `v0.1.1`
- If you publish future tags, keep the changelog updated before tagging
