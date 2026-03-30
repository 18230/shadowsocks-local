# Packagist 发布说明

## 推荐的首个版本

- 仓库地址：`https://github.com/18230/shadowsocks-local`
- Composer 包名：`18230/shadowsocks-local`
- 首个可提交到 Packagist 的版本标签：`v0.1.1`

## 提交前确认

建议先确认这些条件都满足：

- 仓库已经公开
- `composer.json` 已经提交到仓库根目录
- `name` 字段已经最终确定，因为 Packagist 包名之后不能再改
- `composer.json` 不要手动写 `version` 字段
- GitHub 上已经存在发布标签

## 提交到 Packagist

1. 登录 [Packagist](https://packagist.org/) 并打开 [Submit 页面](https://packagist.org/packages/submit)。
2. 填入公开仓库地址：
   `https://github.com/18230/shadowsocks-local`
3. 提交后等待 Packagist 首次抓取完成。

提交成功后，Packagist 应该会自动识别仓库里已有的 `v0.1.1` 标签。

## 配置自动更新

Packagist 官方推荐为 GitHub 仓库开启自动更新，这样以后 push 和新 tag 会更快同步。

推荐做法：

1. 通过 GitHub 登录 Packagist，或者在 Packagist 个人资料里绑定 GitHub 账号。
2. 确保 Packagist 的 GitHub App 对当前仓库有访问权限。
3. 如果没有自动装好 hook，可以在 Packagist 里手动触发一次账号同步。

如果你不想给 Packagist 自动配置权限，也可以手动加 webhook：

- Payload URL：`https://packagist.org/api/github?username=PACKAGIST_USERNAME`
- Content type：`application/json`
- Secret：你的 Packagist API Token
- Events：`push`

## 发布后验证

等 Packagist 索引完成后，建议检查：

1. 包页面是否正确显示仓库、README 和 `v0.1.0`
2. 在空目录里执行安装测试：

```bash
composer require 18230/shadowsocks-local:^0.1
```

3. 然后执行：

```bash
php vendor/bin/ss-local doctor --help
```

## 后续建议

- 等包页面上线后，再补 Packagist 版本和下载量 badge
- 为 `v0.1.1` 创建或更新 GitHub Release
- 后续每次打 tag 前先更新 changelog
