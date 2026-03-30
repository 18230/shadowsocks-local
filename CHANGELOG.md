# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog and this project follows Semantic Versioning.

## [Unreleased]

### Added

- Added GitHub Actions Packagist sync workflow for push and tag updates
- Added cross-platform scripts to create the native GitHub webhook for Packagist

### Changed

- Added Packagist badges and expanded publishing documentation

## [0.1.1] - 2026-03-30

### Changed

- Renamed the Composer package to `18230/shadowsocks-local` for Packagist submission
- Finalized the public release metadata and publishing docs

## [0.1.0] - 2026-03-30

### Added

- Pure PHP Shadowsocks local client with a SOCKS5 frontend
- TCP relay support for `aes-256-gcm`
- YAML, JSON, and `ss://` node parsing
- Cross-platform startup scripts for Windows, Linux, and macOS
- Reusable `ProxyService`, `ProxyEndpoint`, and `TlsSettings` helpers
- Laravel and ThinkPHP integration entry points
- Bilingual documentation in English and Simplified Chinese
