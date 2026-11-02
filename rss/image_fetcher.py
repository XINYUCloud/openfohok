"""
文谶驿站 · 文章封面图获取模块
------------------------------------------------
策略（两阶段异步）：
  1. 对没有封面图的文章，先尝试直接抓取页面 <meta og:image> / <link rel="image_src"> / 首张 <img>。
  2. 若抓取失败，则调用「网页截图系统」提交截图任务，记录 task_id，
     本轮先不等待结果（截图是异步的，通常需要几十秒到几分钟）。
  3. 下一轮采集开始前，先查询所有待处理的截图任务状态；已完成的任务
     用其图片地址回填对应文章的 image_url 字段。

这样可以避免采集流程被截图任务的耗时拖慢。
"""

import re
import json
import os
import uuid
import requests
from pathlib import Path
from urllib.parse import urljoin

try:
    from dotenv import load_dotenv
    load_dotenv(Path(__file__).resolve().parent.parent / ".env")
except ImportError:
    pass

# --------------------------- 网页截图系统配置 ---------------------------
SCREENSHOT_API_BASE = os.environ.get("SCREENSHOT_API_BASE", "https://fohok.xin/webscr/api")
SCREENSHOT_API_KEY = os.environ.get("SCREENSHOT_API_KEY", "")
SCREENSHOT_HEADERS = {
    "Authorization": f"Bearer {SCREENSHOT_API_KEY}",
    "Content-Type": "application/json",
}

PENDING_SHOTS_FILE = "data/pending_screenshots.json"

UA = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36"

OG_IMAGE_PATTERNS = [
    re.compile(r'<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']', re.I),
    re.compile(r'<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']', re.I),
    re.compile(r'<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\']', re.I),
    re.compile(r'<link[^>]+rel=["\']image_src["\'][^>]+href=["\']([^"\']+)["\']', re.I),
]
FIRST_IMG_PATTERN = re.compile(r'<img[^>]+src=["\']([^"\']+)["\']', re.I)


def try_fetch_og_image(article_url, timeout=8):
    """尝试直接从文章页面抓取 og:image 等元信息中的封面图。失败返回 None。"""
    try:
        resp = requests.get(
            article_url,
            headers={"User-Agent": UA},
            timeout=timeout,
            stream=True,
        )
        # 只读取前 200KB，避免大页面拖慢采集
        chunks = []
        total = 0
        for chunk in resp.iter_content(chunk_size=8192, decode_unicode=False):
            chunks.append(chunk)
            total += len(chunk)
            if total > 200 * 1024:
                break
        html = b"".join(chunks).decode(resp.encoding or "utf-8", errors="ignore")

        for pattern in OG_IMAGE_PATTERNS:
            m = pattern.search(html)
            if m:
                img_url = m.group(1).strip()
                return urljoin(article_url, img_url)

        # 兜底：取正文中第一张体积看起来正常的图
        m = FIRST_IMG_PATTERN.search(html)
        if m:
            img_url = m.group(1).strip()
            if not img_url.startswith("data:"):
                return urljoin(article_url, img_url)

    except Exception:
        pass
    return None


def _random_submit_email():
    """
    生成随机前缀的提交邮箱，规避「同一邮箱 10 分钟内最多提交 3 次」的限速。
    邮箱本身不需要真实可达 —— 网页截图系统只用它做限流键和任务归属标记，
    截图结果通过 task_id 查询/取图，不依赖这个邮箱能否收信。
    """
    return f"cover-{uuid.uuid4().hex[:12]}@fohok.xin"


def submit_screenshot_task(article_url, email=None):
    """向网页截图系统提交任务，返回 task_id，失败返回 None。
    默认每次调用生成一个随机邮箱前缀，避免触发同邮箱限速。
    如需固定邮箱（例如调试排查），可显式传入 email 参数覆盖。
    """
    try:
        payload = {
            "url": article_url,
            "email": email or _random_submit_email(),
            "width": 1200,
            "height": 800,
            "full_page": False,
            "export_pdf": False,
        }
        resp = requests.post(
            f"{SCREENSHOT_API_BASE}/submit.php",
            headers=SCREENSHOT_HEADERS,
            json=payload,
            timeout=10,
        )
        data = resp.json()
        if data.get("code") == 200 and data.get("task_id"):
            return data["task_id"]
        else:
            print(f"[Screenshot] 提交失败: {data.get('message')}")
    except Exception as e:
        print(f"[Screenshot] 提交异常: {e}")
    return None


def query_screenshot_task(task_id):
    """查询截图任务状态。返回 dict 或 None。"""
    try:
        resp = requests.get(
            f"{SCREENSHOT_API_BASE}/query.php",
            headers=SCREENSHOT_HEADERS,
            params={"id": task_id},
            timeout=10,
        )
        data = resp.json()
        if data.get("code") == 200:
            return data
    except Exception as e:
        print(f"[Screenshot] 查询异常: {e}")
    return None


def screenshot_image_url(task_id):
    """截图系统的图片直链（无需鉴权即可访问）。"""
    return f"{SCREENSHOT_API_BASE}/image.php?id={task_id}"


def load_pending_shots():
    if os.path.exists(PENDING_SHOTS_FILE):
        try:
            with open(PENDING_SHOTS_FILE, "r", encoding="utf-8") as f:
                return json.load(f)
        except Exception:
            return {}
    return {}


def save_pending_shots(pending):
    os.makedirs("data", exist_ok=True)
    with open(PENDING_SHOTS_FILE, "w", encoding="utf-8") as f:
        json.dump(pending, f, ensure_ascii=False, indent=2)


def resolve_pending_screenshots(articles_by_link):
    """
    查询所有待处理截图任务，把已完成的图片地址回填到对应文章。
    articles_by_link: { link: article_dict, ... } 直接原地修改 article_dict['image_url']
    返回更新后的 pending 字典（未完成的任务会保留，供下一轮继续查询）。
    """
    pending = load_pending_shots()
    if not pending:
        return pending

    still_pending = {}
    resolved_count = 0

    for link, task_id in pending.items():
        result = query_screenshot_task(task_id)
        if not result:
            # 查询失败（可能任务已被清理），保留一次重试机会
            still_pending[link] = task_id
            continue

        condition = result.get("condition", "")
        if condition == "已完成" and result.get("has_image"):
            if link in articles_by_link:
                articles_by_link[link]["image_url"] = screenshot_image_url(task_id)
                articles_by_link[link]["image_source"] = "screenshot"
            resolved_count += 1
        elif condition == "异常":
            # 放弃该任务，不再重试
            resolved_count += 1
        else:
            # 排队中 / 待处理 / 处理中，继续等待
            still_pending[link] = task_id

    if resolved_count:
        print(f"[Screenshot] 本轮回填/结束 {resolved_count} 个截图任务，剩余等待 {len(still_pending)} 个。")

    save_pending_shots(still_pending)
    return still_pending


def ensure_cover_image(article, pending_shots):
    """
    为单篇文章确保有封面图字段：
      1. 已有 image_url 则跳过。
      2. 尝试直接抓取 og:image。
      3. 抓不到则提交截图任务，记录到 pending_shots（不阻塞，等待下一轮回填）。
    该函数会原地修改 article 与 pending_shots。
    """
    if article.get("image_url"):
        return

    link = article.get("link", "")
    if not link:
        return

    # 已经在等截图任务的，跳过重复提交
    if link in pending_shots:
        return

    og_image = try_fetch_og_image(link)
    if og_image:
        article["image_url"] = og_image
        article["image_source"] = "og"
        return

    task_id = submit_screenshot_task(link)
    if task_id:
        pending_shots[link] = task_id
        article["image_source"] = "pending"