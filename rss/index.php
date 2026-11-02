<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$redisConf = ['host' => '127.0.0.1', 'port' => 6379, 'key' => 'rss_essay_cache'];
$jsonFile  = __DIR__ . '/data/essay.json';

function getData() {
    global $redisConf, $jsonFile;
    
    try {
        $redis = new Redis();
        $redis->connect($redisConf['host'], $redisConf['port']);
        
        $cache = $redis->get($redisConf['key']);
        if ($cache) {
            return $cache;
        }
        
        if (!file_exists($jsonFile)) {
            throw new Exception("Data source not found");
        }
        
        $data = file_get_contents($jsonFile);
        $ttl = rand(240, 300);
        $redis->setex($redisConf['key'], $ttl, $data);
        
        return $data;
    } catch (Exception $e) {
        if (file_exists($jsonFile)) {
            return file_get_contents($jsonFile);
        }
        return json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function outputJson() {
    header('Content-Type: application/json; charset=utf-8');
    echo getData();
    exit;
}

function renderPage() {
    $jsonData = getData();
    $articles = json_decode($jsonData, true);
    
    if (!is_array($articles)) {
        $articles = [];
    }
    
    $links = [];
    foreach ($articles as $article) {
        if (!empty($article['link'])) {
            $links[] = $article['link'];
        }
    }
    $linksJson = json_encode($links);
    
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>文谶驿站 · 墨引思潮</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@300;400;500;600;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Noto Serif SC', serif;
            background-color: #fcfcfc;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: background-color 0.5s ease;
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        body.dark-mode {
            background-color: #0f172a;
        }
        
        .container {
            text-align: center;
            z-index: 10;
            padding: 2rem;
            transition: opacity 0.4s ease, transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .container.hidden {
            opacity: 0;
            transform: scale(0.9);
            pointer-events: none;
        }
        
        .title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2d6a4f;
            margin-bottom: 0.5rem;
            letter-spacing: 0.05em;
            transition: color 0.5s ease;
        }
        
        .dark-mode .title {
            color: #81d8a1;
        }
        
        .subtitle {
            font-size: 1rem;
            color: #6b7280;
            margin-bottom: 2.5rem;
            letter-spacing: 0.1em;
            transition: color 0.5s ease;
        }
        
        .dark-mode .subtitle {
            color: #94a3b8;
        }
        
        .portal-btn {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 1rem 2.5rem;
            font-size: 1.1rem;
            font-family: 'Noto Serif SC', serif;
            font-weight: 600;
            color: #ffffff;
            background: linear-gradient(135deg, #2d6a4f 0%, #40916c 50%, #2d6a4f 100%);
            background-size: 200% 200%;
            border: none;
            border-radius: 3rem;
            cursor: pointer;
            letter-spacing: 0.08em;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 30px rgba(45, 106, 79, 0.3);
            animation: gradientShift 3s ease infinite;
            outline: none;
            -webkit-tap-highlight-color: transparent;
        }
        
        .portal-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(45, 106, 79, 0.45);
            animation-play-state: paused;
        }
        
        .portal-btn:active {
            transform: translateY(0);
            box-shadow: 0 4px 15px rgba(45, 106, 79, 0.3);
        }
        
        .portal-btn.hidden {
            opacity: 0;
            pointer-events: none;
            transform: scale(0.8);
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .btn-icon {
            display: inline-block;
            width: 1.2rem;
            height: 1.2rem;
            transition: transform 0.3s ease;
            flex-shrink: 0;
        }
        
        .portal-btn:hover .btn-icon {
            transform: rotate(15deg);
        }
        
        .bike-container {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0);
            z-index: 20;
            opacity: 0;
            transition: transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.4s ease;
            pointer-events: none;
        }
        
        .bike-container.active {
            transform: translate(-50%, -50%) scale(1);
            opacity: 1;
        }
        
        .bike-container.removing {
            transform: translate(-50%, -50%) scale(0);
            opacity: 0;
            transition: transform 0.4s cubic-bezier(0.6, 0.04, 0.98, 0.34), opacity 0.3s ease;
        }
        
        .bike-svg {
            width: 180px;
            height: auto;
            display: block;
        }
        
        .dark-mode .bike-svg g[fill="none"] {
            stroke: #81d8a1;
        }
        
        .counter-text {
            position: fixed;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.85rem;
            color: #9ca3af;
            letter-spacing: 0.05em;
            transition: opacity 0.4s ease, color 0.5s ease;
            z-index: 5;
            white-space: nowrap;
            opacity: 0;
        }
        
        .counter-text.visible {
            opacity: 1;
        }
        
        .dark-mode .counter-text {
            color: #64748b;
        }

        .beian-info {
            position: fixed;
            bottom: 0.6rem;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.75rem;
            color: #9ca3af;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: center;
            z-index: 5;
            transition: color 0.5s ease;
        }

        .beian-info a {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-decoration: none;
            color: inherit;
        }

        .dark-mode .beian-info {
            color: #64748b;
        }
        
        @media (max-width: 640px) {
            .title {
                font-size: 1.8rem;
            }
            .subtitle {
                font-size: 0.85rem;
            }
            .portal-btn {
                padding: 0.8rem 2rem;
                font-size: 1rem;
            }
            .bike-svg {
                width: 140px;
            }
            .beian-info {
                font-size: 0.65rem;
                bottom: 0.4rem;
            }
        }
    </style>
</head>
<body>
    <div class="container" id="mainContainer">
        <h1 class="title">文谶驿站</h1>
        <p class="subtitle">墨引思潮 · 随机穿越</p>
        <button class="portal-btn" id="portalBtn" onclick="initiatePortal()">
            <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
            穿越文海
        </button>
    </div>
    
    <div class="bike-container" id="bikeContainer">
        <svg class="bike-svg" viewBox="0 0 48 30" xmlns="http://www.w3.org/2000/svg">
            <g fill="none" stroke="#2d6a4f" stroke-linecap="round" stroke-linejoin="round" stroke-width="1">
                <g transform="translate(9.5,19)">
                    <circle class="loader_tire" r="9" stroke-dasharray="56.549 56.549">
                        <animate attributeName="stroke-dashoffset" from="56.549" to="-56.549" dur="3s" repeatCount="indefinite" />
                    </circle>
                    <g stroke-dasharray="31.416 31.416" stroke-dashoffset="-23.562">
                        <animateTransform attributeName="transform" type="rotate" from="0" to="1080" dur="3s" repeatCount="indefinite" />
                        <circle class="loader_spokes" r="5"></circle>
                        <circle class="loader_spokes" r="5" transform="rotate(180,0,0)"></circle>
                    </g>
                </g>
                <g transform="translate(24,19)">
                    <g stroke-dasharray="25.133 25.133" stroke-dashoffset="-21.991">
                        <animateTransform attributeName="transform" type="rotate" from="67.5" to="1147.5" dur="3s" repeatCount="indefinite" />
                        <circle class="loader_pedals" r="4"></circle>
                        <circle class="loader_pedals" r="4" transform="rotate(180,0,0)"></circle>
                    </g>
                </g>
                <g transform="translate(38.5,19)">
                    <circle class="loader_tire" r="9" stroke-dasharray="56.549 56.549">
                        <animate attributeName="stroke-dashoffset" from="56.549" to="-56.549" dur="3s" repeatCount="indefinite" />
                    </circle>
                    <g stroke-dasharray="31.416 31.416" stroke-dashoffset="-23.562">
                        <animateTransform attributeName="transform" type="rotate" from="0" to="1080" dur="3s" repeatCount="indefinite" />
                        <circle class="loader_spokes" r="5"></circle>
                        <circle class="loader_spokes" r="5" transform="rotate(180,0,0)"></circle>
                    </g>
                </g>
                <polyline class="loader_seat" points="14 3,18 3" stroke-dasharray="5 5">
                    <animate attributeName="stroke-dashoffset" from="5" to="-5" dur="3s" repeatCount="indefinite" />
                </polyline>
                <polyline class="loader_body" points="16 3,24 19,9.5 19,18 8,34 7,24 19" stroke-dasharray="79 79">
                    <animate attributeName="stroke-dashoffset" from="79" to="-79" dur="3s" repeatCount="indefinite" />
                </polyline>
                <path class="loader_handlebars" d="m30,2h6s1,0,1,1-1,1-1,1" stroke-dasharray="10 10">
                    <animate attributeName="stroke-dashoffset" from="10" to="-10" dur="3s" repeatCount="indefinite" />
                </path>
                <polyline class="loader_front" points="32.5 2,38.5 19" stroke-dasharray="19 19">
                    <animate attributeName="stroke-dashoffset" from="19" to="-19" dur="3s" repeatCount="indefinite" />
                </polyline>
            </g>
        </svg>
    </div>
    
    <p class="counter-text" id="counterText"></p>

    <div class="beian-info">
        <a href="https://beian.miit.gov.cn" target="_blank">
            <img src="https://fohok.xin/img/icp.png" alt="ICP备案图标" style="width:20px;height:auto;">
            闽ICP备2026024151号
        </a>
        <span>|</span>
        <a href="https://beian.mps.gov.cn/#/query/webSearch?code=35058302351237" target="_blank">
            <img src="https://fohok.xin/img/mps.png" alt="公安备案图标" style="width:20px;height:auto;">
            闽公网安备35058302351237号
        </a>
    </div>

    <script>
        var articleLinks = <?php echo $linksJson; ?>;
        
        function getRandomLink() {
            if (articleLinks.length === 0) return null;
            var randomIndex = Math.floor(Math.random() * articleLinks.length);
            return articleLinks[randomIndex];
        }
        
        function updateCounter(seconds) {
            var counter = document.getElementById('counterText');
            if (seconds > 0) {
                counter.textContent = '即将穿越 · ' + seconds.toFixed(1) + 's';
                counter.classList.add('visible');
            } else {
                counter.classList.remove('visible');
                counter.textContent = '';
            }
        }
        
        function initiatePortal() {
            if (articleLinks.length === 0) {
                alert('暂无可用文章链接');
                return;
            }
            
            var mainContainer = document.getElementById('mainContainer');
            var bikeContainer = document.getElementById('bikeContainer');
            
            mainContainer.classList.add('hidden');
            
            setTimeout(function() {
                bikeContainer.classList.add('active');
            }, 200);
            
            document.body.classList.add('dark-mode');
            
            var startTime = Date.now();
            var totalDuration = 3500;
            
            var countdownInterval = setInterval(function() {
                var elapsed = Date.now() - startTime;
                var remaining = (totalDuration - elapsed) / 1000;
                updateCounter(Math.max(0, remaining));
            }, 100);
            
            setTimeout(function() {
                clearInterval(countdownInterval);
                updateCounter(0);
                
                var randomLink = getRandomLink();
                if (randomLink) {
                    window.open(randomLink, '_blank');
                }
                
                bikeContainer.classList.add('removing');
                
                setTimeout(function() {
                    bikeContainer.classList.remove('active', 'removing');
                    mainContainer.classList.remove('hidden');
                    document.body.classList.remove('dark-mode');
                    document.getElementById('counterText').textContent = '';
                    document.getElementById('counterText').classList.remove('visible');
                }, 400);
                
            }, totalDuration);
        }
    </script>
</body>
</html>
    <?php
}

$isWebRequest = false;

if (isset($_GET['type']) && $_GET['type'] === 'web') {
    $isWebRequest = true;
}

if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false && strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false) {
    outputJson();
}

if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    outputJson();
}

if (isset($_GET['format']) && $_GET['format'] === 'json') {
    outputJson();
}

if ($isWebRequest) {
    renderPage();
} else {
    outputJson();
}
?>