# Release Checklist

## Before the First Public Release

- Confirm the final package name in `composer.json`
- Confirm the final repository URL if you plan to add `support` metadata later
- Review the license and changelog

## Local Validation

Run:

```bash
composer validate --strict
composer test
php bin/ss-local doctor --config=examples/node.example.yaml
php bin/ss-local --help
```

Then review:

- [Packagist publishing guide](packagist.md)

Recommended smoke test:

1. Start the local proxy with a real node
2. Request an HTTPS endpoint through `curl --proxy socks5h://127.0.0.1:1080`
3. Request the same endpoint from PHP `curl`

## Packagist Release Flow

1. Tag the release in Git
2. Push the tag to the remote repository
3. Submit or refresh the repository on Packagist
4. Verify `composer require vendor/package` in a clean directory

## Suggested Release Assets

- `README.md`
- `README.zh-CN.md`
- `CHANGELOG.md`
- `LICENSE`
- Example configs and scripts
- `.github/workflows/ubuntu-ci.yml`
- CI configuration in your final repository, if you plan to maintain the package publicly
