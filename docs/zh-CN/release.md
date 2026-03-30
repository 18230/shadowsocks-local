# 发布检查清单

## 第一次公开发布前

- 确认 `composer.json` 中最终的包名
- 如果以后要补 `support` 元数据，确认最终仓库地址
- 检查许可证和更新日志

## 本地验证

运行：

```bash
composer validate --strict
composer test
php bin/ss-local doctor --config=examples/node.example.yaml
php bin/ss-local --help
```

然后再检查：

- [Packagist 发布说明](packagist.md)

建议再做一次烟雾测试：

1. 用真实节点启动本地代理
2. 用 `curl --proxy socks5h://127.0.0.1:1080` 请求一个 HTTPS 地址
3. 再用 PHP `curl` 请求同一个地址

## Packagist 发布流程

1. 在 Git 里打版本标签
2. 推送标签到远端仓库
3. 在 Packagist 提交或刷新仓库
4. 在干净目录里验证 `composer require vendor/package`

## 建议的发版资产

- `README.md`
- `README.zh-CN.md`
- `CHANGELOG.md`
- `LICENSE`
- 示例配置和启动脚本
- `.github/workflows/ubuntu-ci.yml`
- 如果打算长期公开维护，建议在最终仓库补 CI 配置
