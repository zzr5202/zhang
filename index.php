<?php
session_start();

// 登录验证逻辑
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $password = trim($_POST['password']);
    $correctPwd = '123';
    if ($password === $correctPwd) {
        $_SESSION['is_login'] = true;
        header("Location: index.php");
        exit;
    } else {
        $loginError = "密码错误";
    }
}

// 登出逻辑
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

// 短链跳转逻辑
$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$shortCode = ltrim($requestPath, '/');
$shortLinks = getShortLinks();

if (!empty($shortCode) && $shortCode !== 'index.php') {
    if (isset($shortLinks[$shortCode])) {
        $shortLinks[$shortCode]['clicks']++;
        saveShortLinks($shortLinks);
        header("Location: " . $shortLinks[$shortCode]['long_url']);
        exit;
    } else {
        ?>
        <!DOCTYPE html>
        <html lang="zh-CN">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>短链接失效</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>.container { max-width: 600px; margin: 100px auto; text-align: center; }</style>
        </head>
        <body>
            <div class="container">
                <h1 class="text-danger mb-4">短链接失效</h1>
                <p class="lead">您访问的短链接不存在或已被删除</p>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// 权限验证
if (!isset($_SESSION['is_login'])) {
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录保护</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .login-card { max-width: 300px; margin: 100px auto; padding: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="login-card card">
        <div class="card-body">
            <h3 class="text-center mb-4">请输入密码</h3>
            <?php if ($loginError): ?>
                <div class="alert alert-danger"><?= $loginError ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">密码</label>
                    <input type="password" name="password" class="form-control" required placeholder="请输入密码">
                </div>
                <button type="submit" name="login" class="btn btn-primary w-100">登录</button>
            </form>
        </div>
    </div>
</body>
</html>
<?php
exit;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>短链接生成器 - yx0.site</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .container { max-width: 800px; margin: 30px auto; padding: 0 15px; }
        .link-card { margin-bottom: 20px; }
        .short-url { color: #0d6efd; font-weight: 500; }
        .table th, .table td { vertical-align: middle; }
        .action-btn { margin-right: 5px; }
        .long-url-cell { max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .pagination { margin-top: 20px; justify-content: center; }
        .qr-code { width: 100px; height: 100px; border: 1px solid #ddd; padding: 5px; background: white; }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="text-center mb-4">短链接生成器 - yx0.site</h2>

        <div class="card link-card">
            <div class="card-body">
                <form method="post" class="row g-3" autocomplete="off">
                    <div class="col-md-9">
                        <label class="form-label">原始长链接 <span class="text-danger">*</span></label>
                        <input type="url" name="long_url" class="form-control" placeholder="https://example.com" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">自定义短码（可选）</label>
                        <input type="text" name="custom_code" class="form-control" placeholder="如：abc123" maxlength="20">
                        <div class="form-text">数字+英文（1-20位）</div>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">备注（可选）</label>
                        <input type="text" name="remark" class="form-control" placeholder="输入短链备注">
                    </div>
                    <div class="col-md-12 text-center">
                        <button type="submit" class="btn btn-primary w-50">生成短链</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="mb-0">短链接记录</h5></div>
            <div class="card-body">
                <?php
                $shortLinks = getShortLinks();
                usort($shortLinks, function($a, $b) {
                    return strtotime($b['created_at']) - strtotime($a['created_at']);
                });

                $pageSize = 5;
                $total = count($shortLinks);
                $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
                $page = max(1, $page);
                $totalPages = ceil($total / $pageSize);
                $page = min($page, $totalPages);
                $offset = ($page - 1) * $pageSize;
                $pagedShortLinks = array_slice($shortLinks, $offset, $pageSize);

                if (isset($_GET['delete']) && !empty($_GET['code'])) {
                    $codeToDelete = $_GET['code'];
                    $updatedLinks = array_filter($shortLinks, function($link) use ($codeToDelete) {
                        return $link['code'] !== $codeToDelete;
                    });
                    saveShortLinks($updatedLinks);
                    echo '<div class="alert alert-info">短链接已删除</div>';
                    header("Location: index.php?page={$page}");
                    exit;
                }

                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['long_url'])) {
                    $longUrl = trim($_POST['long_url']);
                    $customCode = trim($_POST['custom_code'] ?? '');
                    $remark = trim($_POST['remark'] ?? '');
                    
                    if ($customCode && !preg_match('/^[a-zA-Z0-9]{1,20}$/', $customCode)) {
                        echo '<div class="alert alert-danger">自定义短码仅支持数字和英文（1-20位）</div>';
                    } else {
                        $shortCode = $customCode ?: substr(md5(uniqid()), 0, 6);
                        $newLink = [
                            'code' => $shortCode,
                            'long_url' => $longUrl,
                            'clicks' => 0,
                            'created_at' => date('Y-m-d H:i:s'),
                            'remark' => $remark
                        ];
                        
                        $shortLinks[] = $newLink;
                        saveShortLinks($shortLinks);
                        echo '<div class="alert alert-success">短链接生成成功：<a href="https://yx0.site/' . $shortCode . '" target="_blank">https://yx0.site/' . $shortCode . '</a></div>';
                    }
                }

                if ($shortLinks):
                ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th>短链接</th>
                                <th>原始长链接</th>
                                <th>备注</th>
                                <th>访问次数</th>
                                <th>创建时间</th>
                                <th>二维码</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pagedShortLinks as $link): ?>
                            <tr>
                                <td>
                                    <a href="https://yx0.site/<?= $link['code'] ?>" target="_blank" class="short-url"><?= $link['code'] ?></a>
                                </td>
                                <td class="long-url-cell" title="<?= $link['long_url'] ?>">
                                    <?= $link['long_url'] ?>
                                </td>
                                <td><?= $link['remark'] ?: '无备注' ?></td>
                                <td><?= $link['clicks'] ?> 次</td>
                                <td><?= $link['created_at'] ?></td>
                                <td>
                                    <div class="qr-code" id="qr-<?= $link['code'] ?>"></div>
                                </td>
                                <td>
                                    <button class="btn btn-info btn-sm action-btn" data-clipboard-text="https://yx0.site/<?= $link['code'] ?>">复制</button>
                                    <a href="?delete=1&code=<?= $link['code'] ?>&page=<?= $page ?>" class="btn btn-danger btn-sm action-btn">删除</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <nav aria-label="Page navigation">
                    <ul class="pagination">
                        <li class="page-item <?= $page == 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>">&laquo;</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page == $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>">&raquo;</a>
                        </li>
                    </ul>
                </nav>
                <?php else: ?>
                    <div class="text-center py-3 text-muted">暂无短链接记录</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="text-right mt-3">
            <a href="?logout=1" class="btn btn-secondary">退出登录</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/clipboard@2.0.11/dist/clipboard.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <script>
        new ClipboardJS('.action-btn');
        
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('action-btn') && e.target.getAttribute('data-clipboard-text')) {
                const originalText = e.target.innerHTML;
                e.target.innerHTML = '已复制';
                setTimeout(() => { e.target.innerHTML = originalText; }, 1500);
            }
        });

        // 生成二维码
        <?php foreach ($pagedShortLinks as $link): ?>
        QRCode.toCanvas(document.getElementById('qr-<?= $link['code'] ?>'), 
            'https://yx0.site/<?= $link['code'] ?>', 
            { width: 100, margin: 1 });
        <?php endforeach; ?>
    </script>
</body>
</html>

<?php
function getShortLinks() {
    if (!file_exists('data')) {
        mkdir('data', 0755, true);
    }
    $file = 'data/short_links.json';
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }
    return [];
}

function saveShortLinks($data) {
    if (!file_exists('data')) {
        mkdir('data', 0755, true);
    }
    file_put_contents('data/short_links.json', json_encode($data));
}
?>