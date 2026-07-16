<?php

namespace app\controller;

use support\Request;

class IndexController
{
    public function index(Request $request)
    {
        return $this->view($request);
    }

    public function view(Request $request)
    {
        return response(
            <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AiPay 服务在线</title>
  <style>
    body{margin:0;font-family:"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:#f8fafc;color:#0f172a;display:flex;align-items:center;justify-content:center;min-height:100vh}
    .card{width:min(560px,calc(100% - 32px));background:#fff;border:1px solid #e2e8f0;border-radius:24px;box-shadow:0 18px 48px rgba(15,23,42,.08);padding:28px}
    h1{margin:0 0 12px;font-size:28px}
    p{margin:0;color:#475569;line-height:1.8}
  </style>
</head>
<body>
  <main class="card">
    <h1>AiPay 服务在线</h1>
    <p>当前接口服务已启动，服务状态正常，可继续访问后台、商户端与支付相关接口。</p>
  </main>
</body>
</html>
HTML,
            200,
            ['Content-Type' => 'text/html; charset=utf-8']
        );
    }

    public function json(Request $request)
    {
        return json(['code' => 0, 'msg' => '成功'], JSON_UNESCAPED_UNICODE);
    }

}
