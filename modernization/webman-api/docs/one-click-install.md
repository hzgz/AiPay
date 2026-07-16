# AiPay Linux 一键安装

这套流程面向纯 Linux 服务器，目标是让小白也能完成正式安装。

## 适用场景

- Debian / Ubuntu 服务器
- 已经解压好 AiPay 发布包
- 希望一次完成数据库、管理员账号、Webman、Nginx 和 HTTPS

## 使用方式

```bash
cd /var/www/aipay/backend
sudo bash deploy/linux/install-oneclick.sh
```

脚本会依次处理：

- 检查或安装基础依赖
- 询问域名、数据库信息、管理员账号
- 自动写入 `backend/.env`
- 自动初始化数据库
- 自动创建管理员账号
- 可选创建测试商户
- 自动安装 systemd 服务
- 自动生成 Nginx 配置
- 可选申请 HTTPS
- 自动运行部署验收

## 非交互示例

```bash
sudo bash deploy/linux/install-oneclick.sh \
  --domain=pay.example.com \
  --db-name=aipay_prod \
  --db-user=aipay_prod \
  --db-password='ReplaceMe123!' \
  --admin-user=adminroot \
  --admin-password='ReplaceMe123!' \
  --merchant-user=merchantdemo \
  --merchant-password='ReplaceMe123!' \
  --certbot-no-email \
  --install-deps \
  --non-interactive
```

## 安装完成后

安装脚本会输出：

- 游客首页地址
- 商户登录地址
- 商户注册地址
- 管理员登录地址
- 管理员账号密码
- 测试商户账号密码
- 数据库账号密码
