"""
文谶驿站 · 手动补全封面图脚本
------------------------------------------------
用途：不想等下一轮采集自动处理，手动立即为文章提交截图任务 / 回填结果。

用法：
  # 1. 查看当前有多少文章缺封面图
  python3 manual_cover.py --list

  # 2. 为所有缺封面图的文章提交截图任务（不等待完成，异步）
  python3 manual_cover.py --submit

  # 3. 查询之前提交的任务进度，完成的自动回填进 essay.json
  python3 manual_cover.py --resolve

  # 4. 只针对某一个 link 处理（提交+等待+回填，会阻塞等待，适合单篇测试）
  python3 manual_cover.py --url "https://example.com/some-article" --wait
"""

import argparse
import json
import time
import sys

import image_fetcher

DATA_FILE = "data/essay.json"


def load_articles():
    with open(DATA_FILE, "r", encoding="utf-8") as f:
        return json.load(f)


def save_articles(articles):
    with open(DATA_FILE, "w", encoding="utf-8") as f:
        json.dump(articles, f, ensure_ascii=False, indent=2)


def cmd_list():
    articles = load_articles()
    missing = [a for a in articles if not a.get("image_url")]
    print(f"共 {len(articles)} 篇文章，其中 {len(missing)} 篇缺封面图：")
    for a in missing:
        print(f"  - [{a.get('site_name')}] {a.get('title')} -> {a.get('link')}")


def cmd_submit():
    articles = load_articles()
    articles_by_link = {a["link"]: a for a in articles if a.get("link")}
    pending = image_fetcher.load_pending_shots()

    missing = [a for a in articles if not a.get("image_url")]
    print(f"待处理文章数：{len(missing)}")

    fetched, submitted, skipped = 0, 0, 0
    for article in missing:
        link = article.get("link")
        if link in pending:
            print(f"  [跳过] 已有进行中任务: {article.get('title')}")
            skipped += 1
            continue
        before = len(pending)
        image_fetcher.ensure_cover_image(article, pending)
        if article.get("image_url"):
            print(f"  [直连成功] {article.get('title')} -> {article['image_url']}")
            fetched += 1
        elif len(pending) > before:
            print(f"  [已提交截图任务] {article.get('title')} -> task_id={pending[link]}")
            submitted += 1
        else:
            print(f"  [失败] 提交截图任务失败: {article.get('title')}")

    image_fetcher.save_pending_shots(pending)
    save_articles(articles)
    print(f"\n完成：直连抓取成功 {fetched}，新提交截图任务 {submitted}，跳过 {skipped}。")
    print("截图任务是异步的，等待几十秒到几分钟后运行 --resolve 查看结果。")


def cmd_resolve():
    articles = load_articles()
    articles_by_link = {a["link"]: a for a in articles if a.get("link")}

    pending = image_fetcher.resolve_pending_screenshots(articles_by_link)
    save_articles(articles)

    remaining = len(pending)
    print(f"本次回填完成，仍有 {remaining} 个任务在排队/处理中。")
    if remaining:
        print("提示：稍后再次运行 --resolve 继续查询。")


def cmd_single_url(url, wait=False):
    articles = load_articles()
    target = next((a for a in articles if a.get("link") == url), None)
    if not target:
        print(f"未在 essay.json 中找到 link = {url} 的文章。")
        sys.exit(1)

    if target.get("image_url"):
        print(f"该文章已有封面图: {target['image_url']}")
        return

    print(f"尝试直连抓取 og:image ...")
    og = image_fetcher.try_fetch_og_image(url)
    if og:
        target["image_url"] = og
        target["image_source"] = "og"
        save_articles(articles)
        print(f"[成功] 直连抓取到封面图: {og}")
        return

    print("直连抓取失败，提交截图任务 ...")
    task_id = image_fetcher.submit_screenshot_task(url)
    if not task_id:
        print("[失败] 截图任务提交失败，请检查网络 / API Key。")
        sys.exit(1)

    print(f"截图任务已提交，task_id = {task_id}")

    if not wait:
        pending = image_fetcher.load_pending_shots()
        pending[url] = task_id
        image_fetcher.save_pending_shots(pending)
        print("已记录到待处理队列，稍后运行 --resolve 查看结果。")
        return

    print("等待截图完成（最多轮询 12 次，每次间隔 10 秒）...")
    for i in range(12):
        time.sleep(10)
        result = image_fetcher.query_screenshot_task(task_id)
        if not result:
            print(f"  第 {i+1} 次查询失败，继续等待...")
            continue
        condition = result.get("condition", "")
        print(f"  第 {i+1} 次查询：状态 = {condition}")
        if condition == "已完成" and result.get("has_image"):
            target["image_url"] = image_fetcher.screenshot_image_url(task_id)
            target["image_source"] = "screenshot"
            save_articles(articles)
            print(f"[成功] 封面图已回填: {target['image_url']}")
            return
        elif condition == "异常":
            print("[失败] 截图任务状态异常，放弃。")
            return

    print("[超时] 截图任务仍未完成，已记录到待处理队列，稍后可运行 --resolve 查看。")
    pending = image_fetcher.load_pending_shots()
    pending[url] = task_id
    image_fetcher.save_pending_shots(pending)


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="手动补全文章封面图")
    parser.add_argument("--list", action="store_true", help="列出缺封面图的文章")
    parser.add_argument("--submit", action="store_true", help="为所有缺封面图文章提交任务")
    parser.add_argument("--resolve", action="store_true", help="查询并回填已完成的截图任务")
    parser.add_argument("--url", type=str, help="只处理指定 link 的文章")
    parser.add_argument("--wait", action="store_true", help="配合 --url 使用，阻塞等待截图完成")

    args = parser.parse_args()

    if args.url:
        cmd_single_url(args.url, wait=args.wait)
    elif args.list:
        cmd_list()
    elif args.submit:
        cmd_submit()
    elif args.resolve:
        cmd_resolve()
    else:
        parser.print_help()