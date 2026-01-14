<?php
http_response_code(404);
?><!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Không tìm thấy nội dung</title>
  <link rel="stylesheet" href="style.css" />
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; margin:0; }
    .wrap { max-width: 720px; margin: 10vh auto; padding: 24px; text-align: center; }
    h1 { margin: 0 0 12px; font-size: 28px; }
    p { color: #666; margin: 0 0 20px; }
    a.btn { display:inline-block; padding:10px 16px; background:#0a66c2; color:#fff; border-radius:8px; text-decoration:none; }
  </style>
</head>
<body>
  <div class="wrap">
    <h1>Không tìm thấy nội dung</h1>
    <p>Trang bạn yêu cầu không tồn tại hoặc đã bị xóa.</p>
    <a class="btn" href="/">Về trang chủ</a>
  </div>
</body>
</html>
