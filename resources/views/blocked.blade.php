<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>访问被限制 - 叶宇文件快递</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 15px;
        }
        p {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        .timer {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .timer-label {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .countdown {
            color: #667eea;
            font-size: 32px;
            font-weight: bold;
        }
        .info {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #856404;
        }
        .contact {
            color: #999;
            font-size: 14px;
        }
        .contact a {
            color: #667eea;
            text-decoration: none;
        }
        .contact a:hover {
            text-decoration: underline;
        }
        .ip-info {
            background: #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #4a5568;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🚫</div>
        <h1>访问被限制</h1>
        <p>您的IP地址已被暂时封禁，无法访问本站。</p>
        
        <div class="info">
            <strong>封禁原因：</strong>{{ $reason ?? '上传了包含病毒的文件' }}
        </div>
        
        <div class="ip-info">
            <strong>被封IP：</strong>{{ $ip ?? '未知' }}
        </div>
        
        <div class="timer">
            <div class="timer-label">预计解封时间</div>
            <div class="countdown" id="countdown">计算中...</div>
        </div>
        
        <div class="contact">
            如有疑问，请联系管理员
        </div>
    </div>
    
    <script>
        // 从后端传递的过期时间
        const expiresAt = "{{ $expires_at ? $expires_at->format('Y-m-d H:i:s') : '' }}";
        
        function updateCountdown() {
            if (!expiresAt) {
                document.getElementById('countdown').textContent = '24小时后';
                return;
            }
            
            const unbanTime = new Date(expiresAt);
            const now = new Date();
            const diff = unbanTime - now;
            
            if (diff <= 0) {
                document.getElementById('countdown').textContent = '即将解封';
                // 尝试刷新页面
                setTimeout(() => {
                    location.reload();
                }, 3000);
                return;
            }
            
            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((diff % (1000 * 60)) / 1000);
            
            let timeString = '';
            if (days > 0) {
                timeString += days + '天 ';
            }
            timeString += hours + '小时 ' + minutes + '分钟 ' + seconds + '秒';
            
            document.getElementById('countdown').textContent = timeString;
        }
        
        updateCountdown();
        setInterval(updateCountdown, 1000);
        
        // 每分钟尝试刷新页面，检查是否解封
        setInterval(() => {
            location.reload();
        }, 60000);
    </script>
</body>
</html>
