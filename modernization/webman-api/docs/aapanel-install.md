# AiPay aaPanel 安装说明

这套流程专门给 aaPanel 使用，重点是：

- 前台、商户端、管理员端共用一个前端壳
- Webman 后端只监听 `127.0.0.1:8787`
- aaPanel 的 Nginx 负责公网入口与 HTTPS

## 推荐目录结构

```text
/www/wwwroot/aipay/
  backend/
  console/
```

建议公网目录指向：

```text
/www/wwwroot/aipay/console
```

## 一键安装

```bash
cd /www/wwwroot/aipay/backend
sudo bash deploy/linux/install-aapanel.sh
```

脚本会处理：

- 写入 `backend/.env`
- 创建数据库和数据库用户
- 导入基础库表
- 创建管理员账号
- 可选创建测试商户
- 同步 `console/` 到 aaPanel 公网目录
- 安装 Webman systemd 服务
- 生成 aaPanel 专用 Nginx 配置
- 可选自动覆盖 aaPanel 站点配置
- 可选自动申请 HTTPS

## 非交互示例

```bash
sudo bash deploy/linux/install-aapanel.sh \
  --domain=pay.example.com \
  --public-root=/www/wwwroot/pay.example.com \
  --nginx-conf=/www/server/panel/vhost/nginx/pay.example.com.conf \
  --db-name=pay \
  --db-user=pay \
  --db-password='ReplaceMe123!' \
  --admin-user=adminroot \
  --admin-password='ReplaceMe123!' \
  --certbot-no-email \
  --install-deps \
  --non-interactive
```

## 使用建议

- 站点类型建议使用静态站点
- 站点根目录指向 `console/`
- 不要再让 aaPanel 的 PHP 规则接管 `/submit.php`、`/mapi.php` 这些入口
- 这些入口应该统一反代到 `127.0.0.1:8787`
