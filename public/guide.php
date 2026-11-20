<?php
require_once __DIR__ . '/../src/App.php';
\mediablue\App::registerAutoloader();
\mediablue\Config::init();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mediablue - Linux A2DP Sink / HFP 導入解説ガイド</title>
    
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0c0f14">
    <link rel="icon" type="image/svg+xml" href="/assets/icon.svg">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/icon-192.png">

    <link rel="stylesheet" href="/css/style.css?v=2.1.0">
</head>
<body class="dark-theme guide-standalone-body">
    <div class="car-container" style="overflow-y: auto;">
        <!-- Top Status Bar -->
        <header class="status-bar">
            <div class="status-left">
                <a href="/" class="btn-action btn-sm" style="background: rgba(255, 255, 255, 0.08); color: var(--text-primary); text-decoration: none; display: flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: var(--radius-full); border: 1px solid var(--border-color);">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="19" y1="12" x2="5" y2="12"></line>
                        <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>
                    <span>← アプリに戻る (Media)</span>
                </a>
                <span class="brand-logo" style="margin-left: 12px;">
                    <span data-i18n="brand_name">Mediablue</span>
                    <span class="badge badge-profile" style="margin-left: 8px;">Documentation</span>
                </span>
            </div>
            
            <div class="status-right">
                <a href="#guide-sec-faq" class="btn-action btn-sm btn-secondary" style="text-decoration: none;">
                    FAQ / トラブルシューティング
                </a>
            </div>
        </header>

        <!-- Main Guide Content -->
        <main class="guide-standalone-viewport">
            <?php include __DIR__ . '/../src/Views/guide_content.php'; ?>
        </main>

        <div id="toast" class="toast hidden"></div>
    </div>

    <!-- Scripts for Code Copy & Interaction -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toast = (msg) => {
                const el = document.getElementById('toast');
                if (!el) return;
                el.textContent = msg;
                el.classList.remove('hidden');
                setTimeout(() => el.classList.add('hidden'), 2500);
            };

            // コピーボタンの処理
            document.querySelectorAll('.btn-copy-code').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const code = btn.getAttribute('data-code');
                    if (code) {
                        try {
                            await navigator.clipboard.writeText(code);
                            const orig = btn.textContent;
                            btn.textContent = '✅ コピー完了!';
                            toast('クリップボードにコピーしました');
                            setTimeout(() => btn.textContent = orig, 2000);
                        } catch (e) {
                            toast('コピーに失敗しました');
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>
