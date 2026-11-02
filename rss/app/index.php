<?php
/**
 * 博客订阅 API 接口
 * 使用 WordPress REST API
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Request-Type, X-Article-Id, X-Keyword, X-Category-Id, X-Page, X-Post-Id');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Redis 配置
define('REDIS_HOST', '127.0.0.1');
define('REDIS_PORT', 6379);
define('REDIS_PASSWORD', null);
define('CACHE_EXPIRE', 1800);
define('CACHE_EXPIRE_SHORT', 60);   // 评论缓存60秒
define('CACHE_EXPIRE_LONG', 3600);  // 分类/标签缓存1小时

// Python 服务配置
define('PYTHON_SERVICE_URL', 'http://127.0.0.1:5000');

function getRedisConnection() {
    static $redis = null;
    if ($redis !== null) return $redis;
    try {
        $r = new Redis();
        if (!$r->connect(REDIS_HOST, REDIS_PORT, 2)) return null;
        if (REDIS_PASSWORD) $r->auth(REDIS_PASSWORD);
        $r->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);
        $redis = $r;
        return $redis;
    } catch (Exception $e) {
        error_log('Redis connection failed: ' . $e->getMessage());
        return null;
    }
}

function getCacheKey($type, $param = '') {
    return 'blog_sub_v3:' . md5($type . $param);
}

function requestPythonService($endpoint, $params = [], $method = 'POST') {
    $url = PYTHON_SERVICE_URL . $endpoint;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    if ($method === 'POST' || !empty($params)) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log('Python service request failed: ' . $error);
        return null;
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log('Python service returned HTTP ' . $httpCode . ' for ' . $endpoint);
        // 对于非200也尝试解析返回体（可能包含错误信息）
        if ($response) return json_decode($response, true);
        return null;
    }

    return json_decode($response, true);
}

// 获取请求类型（支持Header、GET参数、POST body）
$requestType = $_SERVER['HTTP_X_REQUEST_TYPE'] ?? ($_GET['type'] ?? '');
if (empty($requestType) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $requestType = $body['type'] ?? '';
}

try {
    $result = ['success' => false, 'message' => '无效的请求类型'];

    switch ($requestType) {

        case 'get_site_and_articles':
            $page = $_SERVER['HTTP_X_PAGE'] ?? ($_GET['page'] ?? 1);
            $redis = getRedisConnection();
            $cacheKey = getCacheKey('site_and_articles', $page);

            if ($redis) {
                $cached = $redis->get($cacheKey);
                if ($cached) { echo $cached; exit(); }
            }

            $data = requestPythonService('/api/site-and-articles', ['page' => (int)$page]);

            if ($data && $data['success']) {
                $result = ['success' => true, 'data' => $data['data']];
                $encoded = json_encode($result, JSON_UNESCAPED_UNICODE);
                if ($redis) $redis->setex($cacheKey, CACHE_EXPIRE, $encoded);
                echo $encoded; exit();
            } else {
                $result = ['success' => false, 'message' => '获取数据失败'];
            }
            break;

        case 'get_article':
            $articleId = $_SERVER['HTTP_X_ARTICLE_ID'] ?? ($_GET['id'] ?? '');
            if (empty($articleId)) {
                $result = ['success' => false, 'message' => '文章ID不能为空'];
                break;
            }

            $redis = getRedisConnection();
            $cacheKey = getCacheKey('article', $articleId);

            if ($redis) {
                $cached = $redis->get($cacheKey);
                if ($cached) { echo $cached; exit(); }
            }

            $data = requestPythonService('/api/article', ['id' => $articleId]);

            if ($data && $data['success']) {
                $result = ['success' => true, 'data' => $data['data']];
                $encoded = json_encode($result, JSON_UNESCAPED_UNICODE);
                if ($redis) $redis->setex($cacheKey, CACHE_EXPIRE, $encoded);
                echo $encoded; exit();
            } else {
                $result = ['success' => false, 'message' => '文章不存在'];
            }
            break;

        case 'get_comments':
            $postId = $_SERVER['HTTP_X_POST_ID'] ?? ($_GET['post_id'] ?? '');
            $page   = $_GET['page'] ?? 1;
            if (empty($postId)) {
                $result = ['success' => false, 'message' => '文章ID不能为空'];
                break;
            }

            $redis = getRedisConnection();
            $cacheKey = getCacheKey('comments', $postId . '_' . $page);

            if ($redis) {
                $cached = $redis->get($cacheKey);
                if ($cached) { echo $cached; exit(); }
            }

            $data = requestPythonService('/api/comments', ['post_id' => $postId, 'page' => (int)$page]);

            if ($data && $data['success']) {
                $result = ['success' => true, 'data' => $data['data']];
                $encoded = json_encode($result, JSON_UNESCAPED_UNICODE);
                if ($redis) $redis->setex($cacheKey, CACHE_EXPIRE_SHORT, $encoded);
                echo $encoded; exit();
            } else {
                $result = ['success' => false, 'message' => '获取评论失败'];
            }
            break;

        case 'post_comment':
            // 发布评论 — 不缓存，直接转发
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            $postId      = $body['post_id'] ?? '';
            $authorName  = trim($body['author_name'] ?? '');
            $authorEmail = trim($body['author_email'] ?? '');
            $content     = trim($body['content'] ?? '');
            $parent      = (int)($body['parent'] ?? 0);
            $authorUrl   = trim($body['author_url'] ?? '');

            if (empty($postId) || empty($authorName) || empty($authorEmail) || empty($content)) {
                http_response_code(400);
                $result = ['success' => false, 'message' => '昵称、邮箱和评论内容不能为空'];
                break;
            }

            $data = requestPythonService('/api/comment', [
                'post_id'      => $postId,
                'author_name'  => $authorName,
                'author_email' => $authorEmail,
                'content'      => $content,
                'parent'       => $parent,
                'author_url'   => $authorUrl
            ]);

            if ($data && $data['success']) {
                // 清除相关缓存
                $redis = getRedisConnection();
                if ($redis) {
                    $redis->del(getCacheKey('comments', $postId . '_1'));
                    $redis->del(getCacheKey('article', $postId));
                }
                $result = ['success' => true, 'data' => $data['data']];
            } else {
                $msg = $data['message'] ?? '评论提交失败';
                http_response_code(400);
                $result = ['success' => false, 'message' => $msg];
            }
            break;

        case 'search':
            $keyword = $_SERVER['HTTP_X_KEYWORD'] ?? ($_GET['keyword'] ?? '');
            $page    = $_GET['page'] ?? 1;
            if (empty($keyword)) {
                $result = ['success' => false, 'message' => '搜索关键词不能为空'];
                break;
            }

            $data = requestPythonService('/api/search', ['keyword' => $keyword, 'page' => (int)$page]);

            if ($data && $data['success']) {
                $result = ['success' => true, 'data' => $data['data']];
            } else {
                $result = ['success' => false, 'message' => '搜索失败'];
            }
            break;

        case 'get_categories':
            $redis = getRedisConnection();
            $cacheKey = getCacheKey('categories');
            if ($redis) {
                $cached = $redis->get($cacheKey);
                if ($cached) { echo $cached; exit(); }
            }

            $data = requestPythonService('/api/categories', [], 'GET');
            if ($data && $data['success']) {
                $result = ['success' => true, 'data' => $data['data']];
                $encoded = json_encode($result, JSON_UNESCAPED_UNICODE);
                if ($redis) $redis->setex($cacheKey, CACHE_EXPIRE_LONG, $encoded);
                echo $encoded; exit();
            } else {
                $result = ['success' => false, 'message' => '获取分类失败'];
            }
            break;

        case 'get_tags':
            $redis = getRedisConnection();
            $cacheKey = getCacheKey('tags');
            if ($redis) {
                $cached = $redis->get($cacheKey);
                if ($cached) { echo $cached; exit(); }
            }

            $data = requestPythonService('/api/tags', [], 'GET');
            if ($data && $data['success']) {
                $result = ['success' => true, 'data' => $data['data']];
                $encoded = json_encode($result, JSON_UNESCAPED_UNICODE);
                if ($redis) $redis->setex($cacheKey, CACHE_EXPIRE_LONG, $encoded);
                echo $encoded; exit();
            } else {
                $result = ['success' => false, 'message' => '获取标签失败'];
            }
            break;

        case 'posts_by_category':
            $categoryId = $_SERVER['HTTP_X_CATEGORY_ID'] ?? ($_GET['category_id'] ?? '');
            $page       = $_GET['page'] ?? 1;
            if (empty($categoryId)) {
                $result = ['success' => false, 'message' => '分类ID不能为空'];
                break;
            }

            $redis = getRedisConnection();
            $cacheKey = getCacheKey('posts_by_cat', $categoryId . '_' . $page);
            if ($redis) {
                $cached = $redis->get($cacheKey);
                if ($cached) { echo $cached; exit(); }
            }

            $data = requestPythonService('/api/posts-by-category', ['category_id' => $categoryId, 'page' => (int)$page]);
            if ($data && $data['success']) {
                $result = ['success' => true, 'data' => $data['data']];
                $encoded = json_encode($result, JSON_UNESCAPED_UNICODE);
                if ($redis) $redis->setex($cacheKey, CACHE_EXPIRE, $encoded);
                echo $encoded; exit();
            } else {
                $result = ['success' => false, 'message' => '获取文章失败'];
            }
            break;

        case 'clear_cache':
            $redis = getRedisConnection();
            if ($redis) {
                $keys = $redis->keys('blog_sub_v3:*');
                if (!empty($keys)) $redis->del($keys);
            }
            // 也清除 Python 内存缓存
            requestPythonService('/api/cache/clear');
            $result = ['success' => true, 'message' => '缓存已清除'];
            break;

        case 'health_check':
            $data = requestPythonService('/health', [], 'GET');
            $result = ['success' => true, 'data' => $data];
            break;

        default:
            http_response_code(400);
            $result = ['success' => false, 'message' => '未知的请求类型: ' . $requestType];
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '服务器内部错误'], JSON_UNESCAPED_UNICODE);
}
