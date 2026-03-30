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

优先推荐的方式还是让 Packagist 自己管理 GitHub hook：

1. 用 GitHub 账号登录 Packagist，或者在 Packagist 个人资料中绑定 GitHub。
2. 确保 Packagist 的 GitHub App 已经拿到当前仓库权限。
3. 如果 hook 没自动装上，可以在 Packagist 里手动触发一次账号同步。

这个仓库同时内置了一套“工作流自动通知 Packagist”的兜底方案：

- 工作流文件：`.github/workflows/packagist-sync.yml`
- 触发时机：`main` 分支 push、版本 tag push、手动触发
- 必需的 GitHub Secret：`PACKAGIST_API_TOKEN`
- 可选的 GitHub Repository Variable：`PACKAGIST_USERNAME`
  如果不设置，工作流会自动退回到 GitHub 仓库 owner 名称。

工作流调用的是 Packagist 官方通用更新接口：

```text
POST https://packagist.org/api/update-package?username=USERNAME&apiToken=API_TOKEN
```

请求体示例：

```json
{"repository":{"url":"https://github.com/18230/shadowsocks-local"}}
```

这种方式可以保证包信息持续同步，但 Packagist 页面上的“not auto-updated”提示可能依然存在，直到你真正配置了原生 GitHub webhook。

如果你想手动配置原生 GitHub webhook，Packagist 官方给出的参数是：

- Payload URL：`https://packagist.org/api/github?username=PACKAGIST_USERNAME`
- Content type：`application/json`
- Secret：你的 Packagist API Token
- Events：`push`

这个仓库已经内置了一键脚本，可以直接创建或更新这个原生 hook：

- PowerShell：`scripts/setup-packagist-github-hook.ps1`
- Shell：`scripts/setup-packagist-github-hook.sh`

示例：

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

GitHub CLI 当前登录账号还需要带上 `admin:repo_hook` scope。如果你现在的 `gh` 登录没有这个权限，先执行：

```bash
gh auth refresh -h github.com -s admin:repo_hook
```

原生 webhook 配好后，Packagist 页面上的 “This package is not auto-updated” 提示通常就会消失。

## 发布后验证

等 Packagist 索引完成后，建议检查：

1. 包页面是否正确显示仓库、README 和 `v0.1.1`
2. 在空目录里执行安装测试：

```bash
composer require 18230/shadowsocks-local:^0.1
```

3. 然后执行：

```bash
php vendor/bin/ss-local doctor --help
```

## 后续建议

- 包页面上线后，补齐或确认 Packagist 版本和下载量 badge
- 为 `v0.1.1` 创建或更新 GitHub Release
- 后续每次打 tag 前先更新 changelog
