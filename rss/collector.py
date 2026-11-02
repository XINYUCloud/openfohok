import requests
import feedparser
import json
import time
import os
import redis
import pytz
import subprocess
from datetime import datetime, timedelta
from dateutil import parser as date_parser

import image_fetcher

# --- 基础配置 ---
API_URL = os.environ.get("RSS_LINKS_API_URL", "https://blog.fohok.xin/wp-json/linkinterface/v1/get-links")
HEADERS = {"X-Comm-Key": os.environ.get("X_COMM_KEY", "")}
DATA_FILE = "data/essay.json"
LAST_TIME_FILE = "data/last_time.txt"
REDIS_CONF = {"host": "127.0.0.1", "port": 6379, "db": 0, "key": "rss_essay_cache"}
MAX_DAYS = 60
BEIJING_TZ = pytz.timezone('Asia/Shanghai')

def fetch_links():
    """从 WordPress 接口获取订阅源列表"""
    try:
        resp = requests.post(API_URL, headers=HEADERS, timeout=15)
        data = resp.json()
        if data.get("success"):
            return [l for l in data["links"] if l.get("rss_url")]
    except Exception as e:
        print(f"[Error] 无法获取订阅列表: {e}")
    return []

def parse_rss(site_url, rss_url):
    """解析单个 RSS 源并过滤近 60 天的文章"""
    articles = []
    try:
        feed = feedparser.parse(rss_url)
        now_beijing = datetime.now(BEIJING_TZ)
        limit_date = now_beijing - timedelta(days=MAX_DAYS)
        
        # 优先使用 Feed 标题，若无则使用 URL
        site_name = feed.feed.get('title', site_url)

        for entry in feed.entries:
            pub_date_str = entry.get('published', entry.get('updated', None))
            if not pub_date_str: continue
            
            try:
                raw_date = date_parser.parse(pub_date_str)
                # 统一转为北京时间
                if raw_date.tzinfo is None:
                    raw_date = pytz.utc.localize(raw_date)
                pub_date_beijing = raw_date.astimezone(BEIJING_TZ)

                if pub_date_beijing > limit_date:
                    # 简单清洗 HTML 标签，防止摘要乱码
                    summary_text = entry.get('summary', entry.get('description', ''))
                    clean_summary = "".join(summary_text.splitlines())[:200]
                    
                    articles.append({
                        "site_name": site_name,
                        "site_link": site_url,
                        "title": entry.get('title', '无标题'),
                        "author": entry.get('author', '佚名'),
                        "summary": clean_summary,
                        "pub_time": pub_date_beijing.strftime('%Y-%m-%d %H:%M:%S'),
                        "link": entry.get('link', ''),
                        "timestamp": int(pub_date_beijing.timestamp())
                    })
            except Exception:
                continue
    except Exception:
        pass 
    return articles

def interleaved_sort(articles):
    """时间倒序基础上，防止同一站点连续刷屏"""
    articles.sort(key=lambda x: x['timestamp'], reverse=True)
    result = []
    temp_pool = articles.copy()
    
    while temp_pool:
        found = False
        for i in range(len(temp_pool)):
            item = temp_pool[i]
            # 如果最后两篇来自同一站点，则跳过尝试寻找其他站点
            if len(result) >= 2 and result[-1]['site_name'] == item['site_name'] and result[-2]['site_name'] == item['site_name']:
                continue 
            else:
                result.append(temp_pool.pop(i))
                found = True
                break
        if not found:
            result.append(temp_pool.pop(0))
    return result

def get_last_timestamp():
    """获取上次推送的最晚时间戳"""
    if os.path.exists(LAST_TIME_FILE):
        try:
            with open(LAST_TIME_FILE, 'r') as f:
                return int(f.read().strip())
        except:
            return 0
    return 0

def save_last_timestamp(ts):
    """保存本次抓取的最晚时间戳"""
    with open(LAST_TIME_FILE, 'w') as f:
        f.write(str(ts))

def clear_redis():
    """刷新 Redis 缓存，强制前端获取新 JSON"""
    try:
        r = redis.Redis(host=REDIS_CONF['host'], port=REDIS_CONF['port'], db=REDIS_CONF['db'])
        r.delete(REDIS_CONF['key'])
        print("[Redis] 缓存已清除")
    except Exception as e:
        print(f"[Redis] 无法连接: {e}")

def load_existing_articles():
    """读取上一轮已保存的文章数据，用于合并封面图等增量信息。"""
    if os.path.exists(DATA_FILE):
        try:
            with open(DATA_FILE, 'r', encoding='utf-8') as f:
                data = json.load(f)
            if isinstance(data, list):
                return {a['link']: a for a in data if a.get('link')}
        except Exception:
            pass
    return {}


def apply_cover_images(all_articles):
    """
    两阶段封面图处理：
      1. 先查询上一轮提交的截图任务，完成的直接回填。
      2. 对仍缺封面的文章尝试 og:image，抓不到则提交新的截图任务。
    """
    existing = load_existing_articles()
    articles_by_link = {a['link']: a for a in all_articles if a.get('link')}

    # 继承旧数据中已有的封面图（避免每轮重复请求）
    for link, article in articles_by_link.items():
        old = existing.get(link)
        if old and old.get('image_url') and not article.get('image_url'):
            article['image_url'] = old['image_url']
            article['image_source'] = old.get('image_source', 'og')

    # 回填已完成的截图任务
    pending = image_fetcher.resolve_pending_screenshots(articles_by_link)

    # 对仍无封面图的文章尝试抓取 / 提交截图任务
    missing = [a for a in all_articles if not a.get('image_url')]
    print(f"[Cover] 待补全封面图文章数：{len(missing)}")
    fetched, submitted = 0, 0
    for article in missing:
        before_pending = len(pending)
        image_fetcher.ensure_cover_image(article, pending)
        if article.get('image_url'):
            fetched += 1
        elif len(pending) > before_pending:
            submitted += 1
    print(f"[Cover] 本轮直接抓取成功 {fetched} 篇，新提交截图任务 {submitted} 个。")

    image_fetcher.save_pending_shots(pending)


def run_task():
    print(f"\n--- 任务启动: {datetime.now(BEIJING_TZ).strftime('%Y-%m-%d %H:%M:%S')} ---")
    
    links = fetch_links()
    if not links:
        print("订阅源为空，跳过本次执行。")
        return

    all_articles = []
    for link in links:
        all_articles.extend(parse_rss(link['link_url'], link['rss_url']))
    
    if not all_articles:
        print("未抓取到任何有效文章。")
        return

    # 1. 整理并保存全量数据
    sorted_data = interleaved_sort(all_articles)

    # 1.5 补全文章封面图（异步两阶段，不阻塞主流程太久）
    try:
        apply_cover_images(sorted_data)
    except Exception as e:
        print(f"[Cover] 封面图处理异常，跳过本轮: {e}")

    os.makedirs("data", exist_ok=True)
    with open(DATA_FILE, 'w', encoding='utf-8') as f:
        json.dump(sorted_data, f, ensure_ascii=False, indent=2)
    print(f"[Success] 已更新 {DATA_FILE}，共 {len(sorted_data)} 篇文章。")

    # 2. 增量检测与推送
    last_ts = get_last_timestamp()
    # 筛选出比上次记录更晚的所有新文章
    new_articles = [a for a in all_articles if a['timestamp'] > last_ts]
    
    if new_articles:
        # 按时间从旧到新排序
        new_articles.sort(key=lambda x: x['timestamp'])
        print(f"[Push] 发现 {len(new_articles)} 篇新文章！")
        
        # 准备推送内容（限最新的 5 篇，避免单次邮件过大）
        push_payload = json.dumps(new_articles[-5:], ensure_ascii=False)
        
        try:
            # 调用 PHP 推送脚本
            php_script = os.path.join(os.getcwd(), 'send_emails.php')
            subprocess.run(['php', php_script, push_payload], check=True)
            
            # 更新时间戳为这批新文章中最晚的一篇
            save_last_timestamp(new_articles[-1]['timestamp'])
            print("[Push] 邮件推送任务已提交。")
        except Exception as e:
            print(f"[Push] 邮件推送调用失败: {e}")
    else:
        print("[Push] 暂无新文章，无需发送邮件。")

    # 3. 清理缓存
    clear_redis()

if __name__ == "__main__":
    # 首次启动：若无记录，则标记当前时间为起始点，防止历史文章群发
    if not os.path.exists(LAST_TIME_FILE):
        os.makedirs("data", exist_ok=True)
        save_last_timestamp(int(time.time()))
        print("[System] 初始化时间戳记录文件。")
        
    while True:
        try:
            run_task()
        except Exception as e:
            print(f"[Critical] 运行异常: {e}")
        
        print(f"--- 进入 1 小时休眠周期 ---")
        time.sleep(3600)
