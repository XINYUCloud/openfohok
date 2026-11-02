#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""
博客订阅 Python 服务
使用 WordPress REST API 获取数据
宝塔进程守护管理器运行此脚本
"""

import requests
from flask import Flask, request, jsonify
from flask_cors import CORS
import re
import hashlib
from datetime import datetime
from html.parser import HTMLParser
from concurrent.futures import ThreadPoolExecutor, as_completed
import threading
import time

app = Flask(__name__)
CORS(app)

# 博客配置
BLOG_URL = "https://blog.fohok.xin"
API_BASE = f"{BLOG_URL}/wp-json/wp/v2"
SITE_NAME = "谶的生活记"
SITE_LOGO_LIGHT = "https://blog.fohok.xin/wp-content/uploads/2026/04/cropped-a91fabf8a49526b0ab7941fb2659297e1755339178171-32x32.jpg"
SITE_LOGO_DARK = "https://blog.fohok.xin/wp-content/uploads/2026/04/cropped-a91fabf8a49526b0ab7941fb2659297e1755339178171-32x32.jpg"

# 内存缓存
_cache = {}
_cache_lock = threading.Lock()
CACHE_TTL = 300  # 5分钟


def cache_get(key):
    with _cache_lock:
        item = _cache.get(key)
        if item and time.time() < item['exp']:
            return item['val']
        return None


def cache_set(key, val, ttl=CACHE_TTL):
    with _cache_lock:
        _cache[key] = {'val': val, 'exp': time.time() + ttl}


def cache_delete(key):
    with _cache_lock:
        _cache.pop(key, None)


class HTMLStripper(HTMLParser):
    """去除HTML标签"""
    def __init__(self):
        super().__init__()
        self.reset()
        self.text = []

    def handle_data(self, data):
        self.text.append(data)

    def get_data(self):
        return ''.join(self.text)


class WordPressAPI:
    """WordPress REST API 封装（带连接池和内存缓存）"""

    def __init__(self):
        adapter = requests.adapters.HTTPAdapter(
            pool_connections=10,
            pool_maxsize=20,
            max_retries=requests.adapters.Retry(total=2, backoff_factor=0.3)
        )
        self.session = requests.Session()
        self.session.mount('http://', adapter)
        self.session.mount('https://', adapter)
        self.session.headers.update({
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Accept': 'application/json'
        })
        self.executor = ThreadPoolExecutor(max_workers=8)

    def strip_html(self, html_content):
        if not html_content:
            return ''
        stripper = HTMLStripper()
        stripper.feed(html_content)
        text = stripper.get_data()
        text = re.sub(r'\s+', ' ', text)
        return text.strip()


    def _get_avatar(self, name='匿名'):
        """使用 UI Avatars 生成头像（国内可访问，无需 Gravatar）"""
        import urllib.parse
        safe = urllib.parse.quote(str(name or '匿名')[:2])
        return f"https://ui-avatars.com/api/?name={safe}&size=48&background=2d6a4f&color=ffffff&bold=true&format=svg"

    def format_date(self, date_str):
        try:
            dt = datetime.fromisoformat(date_str.replace('Z', '+00:00'))
            return dt.strftime('%Y年%m月%d日')
        except:
            return date_str

    def format_datetime(self, date_str):
        try:
            dt = datetime.fromisoformat(date_str.replace('Z', '+00:00'))
            return dt.strftime('%Y年%m月%d日 %H:%M')
        except:
            return date_str

    def extract_cover_image(self, post_data):
        if 'content' in post_data and post_data['content'].get('rendered'):
            img_pattern = r'<img[^>]+src=["\']([^"\']+)["\']'
            match = re.search(img_pattern, post_data['content']['rendered'])
            if match:
                return match.group(1)
        if 'jetpack_featured_media_url' in post_data:
            return post_data['jetpack_featured_media_url']
        return None

    def get_site_info(self):
        cached = cache_get('site_info')
        if cached:
            return cached

        try:
            response = self.session.get(f"{BLOG_URL}/wp-json", timeout=8)
            if response.status_code == 200:
                data = response.json()
                site_name = data.get('name', SITE_NAME)
                description = data.get('description', '个人博客')
            else:
                site_name = SITE_NAME
                description = '个人博客'
        except:
            site_name = SITE_NAME
            description = '个人博客'

        result = {
            'name': site_name,
            'logo': SITE_LOGO_LIGHT,
            'logo_dark': SITE_LOGO_DARK,
            'url': BLOG_URL,
            'description': description
        }
        cache_set('site_info', result, ttl=600)
        return result

    def get_posts(self, page=1, per_page=20):
        cache_key = f'posts_{page}_{per_page}'
        cached = cache_get(cache_key)
        if cached:
            return cached

        try:
            params = {
                'page': page,
                'per_page': per_page,
                '_embed': 'author,wp:featuredmedia,wp:term',
                'status': 'publish',
                'orderby': 'date',
                'order': 'desc'
            }
            response = self.session.get(f"{API_BASE}/posts", params=params, timeout=15)
            if response.status_code != 200:
                return []

            posts = response.json()
            total_pages = int(response.headers.get('X-WP-TotalPages', 1))
            total_posts = int(response.headers.get('X-WP-Total', len(posts)))

            articles = []
            for post in posts:
                try:
                    articles.append(self.parse_post(post))
                except:
                    continue

            result = {
                'articles': articles,
                'total': total_posts,
                'total_pages': total_pages,
                'page': page
            }
            cache_set(cache_key, result)
            return result
        except Exception as e:
            print(f"获取文章列表失败: {e}")
            return {'articles': [], 'total': 0, 'total_pages': 1, 'page': page}

    def parse_post(self, post):
        excerpt = ''
        if post.get('excerpt') and post['excerpt'].get('rendered'):
            excerpt = self.strip_html(post['excerpt']['rendered'])

        if not excerpt and post.get('content') and post['content'].get('rendered'):
            content_text = self.strip_html(post['content']['rendered'])
            excerpt = content_text[:200] + ('...' if len(content_text) > 200 else '')

        cover = None
        if '_embedded' in post:
            fm = post['_embedded'].get('wp:featuredmedia', [{}])
            if fm and 'source_url' in fm[0]:
                cover = fm[0]['source_url']

        if not cover:
            cover = self.extract_cover_image(post)

        # 提取分类和标签
        tags = []
        categories = []
        if '_embedded' in post and 'wp:term' in post['_embedded']:
            for term_group in post['_embedded']['wp:term']:
                for term in term_group:
                    if term.get('taxonomy') == 'post_tag':
                        tags.append(term.get('name', ''))
                    elif term.get('taxonomy') == 'category':
                        categories.append({'id': term.get('id'), 'name': term.get('name', '')})

        author_info = {}
        if '_embedded' in post and 'author' in post['_embedded']:
            author_data = post['_embedded']['author'][0] if post['_embedded']['author'] else {}
            author_info = {
                'name': author_data.get('name', ''),
                'avatar': author_data.get('avatar_urls', {}).get('48', '')
            }

        return {
            'id': str(post['id']),
            'title': post.get('title', {}).get('rendered', '无标题'),
            'link': post.get('link', ''),
            'date': self.format_date(post.get('date', '')),
            'date_raw': post.get('date', ''),
            'modified': self.format_date(post.get('modified', '')),
            'excerpt': excerpt,
            'cover': cover,
            'author': author_info,
            'comment_count': post.get('comment_count', 0),
            'comment_status': post.get('comment_status', 'closed'),
            'tags': tags[:5],
            'categories': categories[:3],
            'reading_time': self._estimate_reading_time(
                self.strip_html(post.get('content', {}).get('rendered', ''))
            )
        }

    def _estimate_reading_time(self, text):
        """估算阅读时间（中文约300字/分钟）"""
        char_count = len(text)
        minutes = max(1, round(char_count / 300))
        return minutes

    def get_post(self, post_id):
        cache_key = f'post_{post_id}'
        cached = cache_get(cache_key)
        if cached:
            return cached

        try:
            params = {'_embed': 'author,wp:featuredmedia,wp:term'}
            response = self.session.get(f"{API_BASE}/posts/{post_id}", params=params, timeout=15)

            if response.status_code != 200:
                return None

            post = response.json()
            article = self.parse_post(post)
            article['content'] = post.get('content', {}).get('rendered', '<p>暂无内容</p>')

            # 并行获取评论
            article['comments'] = self.get_comments(post_id)

            cache_set(cache_key, article, ttl=120)
            return article
        except Exception as e:
            print(f"获取文章详情失败: {e}")
            return None

    def get_comments(self, post_id, page=1, per_page=30):
        cache_key = f'comments_{post_id}_{page}'
        cached = cache_get(cache_key)
        if cached:
            return cached

        try:
            params = {
                'post': post_id,
                'per_page': per_page,
                'page': page,
                'orderby': 'date',
                'order': 'asc',
            }
            response = self.session.get(f"{API_BASE}/comments", params=params, timeout=10)

            if response.status_code != 200:
                return {'comments': [], 'total': 0}

            comments_raw = response.json()
            total = int(response.headers.get('X-WP-Total', len(comments_raw)))

            result_list = []
            for comment in comments_raw:
                cname = comment.get('author_name', '匿名')
                result_list.append({
                    'id': str(comment['id']),
                    'parent': comment.get('parent', 0),
                    'author': cname,
                    'author_url': comment.get('author_url', ''),
                    'content': self.strip_html(comment.get('content', {}).get('rendered', '')),
                    'time': self.format_datetime(comment.get('date', '')),
                    'date_raw': comment.get('date', ''),
                    'avatar': self._get_avatar(cname)
                })

            result = {'comments': result_list, 'total': total}
            cache_set(cache_key, result, ttl=60)
            return result
        except Exception as e:
            print(f"获取评论失败: {e}")
            return {'comments': [], 'total': 0}

    def post_comment(self, post_id, author_name, author_email, content, parent=0, author_url=''):
        """发布评论"""
        try:
            payload = {
                'post': int(post_id),
                'author_name': author_name.strip(),
                'author_email': author_email.strip(),
                'content': content.strip()
            }
            if parent:
                payload['parent'] = int(parent)
            if author_url:
                payload['author_url'] = author_url.strip()

            response = self.session.post(
                f"{API_BASE}/comments",
                json=payload,
                timeout=15
            )

            if response.status_code in (200, 201):
                comment = response.json()
                # 清除该文章评论缓存
                cache_delete(f'comments_{post_id}_1')
                cache_delete(f'post_{post_id}')
                return {
                    'success': True,
                    'comment': {
                        'id': str(comment['id']),
                        'parent': comment.get('parent', 0),
                        'author': comment.get('author_name', author_name),
                        'content': self.strip_html(comment.get('content', {}).get('rendered', content)),
                        'time': self.format_datetime(comment.get('date', '')),
                        'avatar': self._get_avatar(comment.get('author_name', author_name))
                    }
                }
            elif response.status_code == 409:
                return {'success': False, 'message': '您已提交过相同的评论，请勿重复提交'}
            elif response.status_code == 403:
                err = response.json() if response.content else {}
                msg = err.get('message', '')
                if '登录' in msg or 'login' in msg.lower() or 'log in' in msg.lower():
                    msg = '该博客需要登录后才能评论，请联系博主开启匿名评论'
                elif not msg:
                    msg = '评论已关闭或暂不允许匿名评论'
                return {'success': False, 'message': msg}
            else:
                err = response.json() if response.content else {}
                return {'success': False, 'message': err.get('message', f'提交失败 ({response.status_code})，请稍后重试')}
        except Exception as e:
            print(f"发布评论失败: {e}")
            return {'success': False, 'message': '网络错误，请稍后重试'}

    def search_posts(self, keyword, page=1, per_page=20):
        try:
            params = {
                'search': keyword,
                'page': page,
                'per_page': per_page,
                '_embed': 'author,wp:featuredmedia',
                'status': 'publish'
            }
            response = self.session.get(f"{API_BASE}/posts", params=params, timeout=15)

            if response.status_code != 200:
                return {'articles': [], 'total': 0}

            posts = response.json()
            total = int(response.headers.get('X-WP-Total', len(posts)))
            articles = []
            for post in posts:
                try:
                    articles.append(self.parse_post(post))
                except:
                    continue
            return {'articles': articles, 'total': total}
        except Exception as e:
            print(f"搜索失败: {e}")
            return {'articles': [], 'total': 0}

    def get_categories(self):
        cached = cache_get('categories')
        if cached:
            return cached
        try:
            response = self.session.get(
                f"{API_BASE}/categories",
                params={'per_page': 50, 'hide_empty': True},
                timeout=10
            )
            if response.status_code == 200:
                cats = response.json()
                result = [{'id': c['id'], 'name': c['name'], 'count': c['count'], 'slug': c['slug']} for c in cats]
                cache_set('categories', result, ttl=600)
                return result
        except:
            pass
        return []

    def get_posts_by_category(self, category_id, page=1, per_page=20):
        cache_key = f'cat_{category_id}_{page}'
        cached = cache_get(cache_key)
        if cached:
            return cached
        try:
            params = {
                'categories': category_id,
                'page': int(page),
                'per_page': per_page,
                '_embed': 'author,wp:featuredmedia',
                'status': 'publish'
            }
            response = self.session.get(f"{API_BASE}/posts", params=params, timeout=15)
            if response.status_code != 200:
                return {'articles': [], 'total': 0}
            posts = response.json()
            total = int(response.headers.get('X-WP-Total', len(posts)))
            articles = []
            for post in posts:
                try:
                    articles.append(self.parse_post(post))
                except:
                    continue
            result = {'articles': articles, 'total': total}
            cache_set(cache_key, result)
            return result
        except Exception as e:
            return {'articles': [], 'total': 0}

    def get_tags(self):
        cached = cache_get('tags')
        if cached:
            return cached
        try:
            response = self.session.get(
                f"{API_BASE}/tags",
                params={'per_page': 50, 'hide_empty': True, 'orderby': 'count', 'order': 'desc'},
                timeout=10
            )
            if response.status_code == 200:
                tags = response.json()
                result = [{'id': t['id'], 'name': t['name'], 'count': t['count'], 'slug': t['slug']} for t in tags]
                cache_set('tags', result, ttl=600)
                return result
        except:
            pass
        return []


# 初始化 API
wp_api = WordPressAPI()


@app.route('/api/site-and-articles', methods=['GET', 'POST'])
def get_site_and_articles():
    try:
        page = request.args.get('page', 1)
        if request.method == 'POST':
            data = request.get_json() or {}
            page = data.get('page', page)

        # 并行获取站点信息和文章
        with ThreadPoolExecutor(max_workers=2) as ex:
            f_site = ex.submit(wp_api.get_site_info)
            f_posts = ex.submit(wp_api.get_posts, page=int(page), per_page=20)
            site_info = f_site.result()
            posts_result = f_posts.result()

        return jsonify({
            'success': True,
            'data': {
                'site': site_info,
                'articles': posts_result.get('articles', []),
                'page': int(page),
                'total': posts_result.get('total', 0),
                'total_pages': posts_result.get('total_pages', 1)
            }
        })
    except Exception as e:
        return jsonify({'success': False, 'message': str(e)}), 500


@app.route('/api/article', methods=['GET', 'POST'])
def get_article():
    try:
        if request.method == 'POST':
            data = request.get_json() or {}
            article_id = data.get('id')
        else:
            article_id = request.args.get('id')

        if not article_id:
            return jsonify({'success': False, 'message': '文章ID不能为空'}), 400

        article = wp_api.get_post(article_id)
        if article:
            return jsonify({'success': True, 'data': article})
        else:
            return jsonify({'success': False, 'message': '文章不存在'}), 404
    except Exception as e:
        return jsonify({'success': False, 'message': str(e)}), 500


@app.route('/api/comments', methods=['GET', 'POST'])
def get_comments():
    """获取文章评论"""
    try:
        if request.method == 'POST':
            data = request.get_json() or {}
            post_id = data.get('post_id')
            page = data.get('page', 1)
        else:
            post_id = request.args.get('post_id')
            page = request.args.get('page', 1)

        if not post_id:
            return jsonify({'success': False, 'message': '文章ID不能为空'}), 400

        result = wp_api.get_comments(post_id, page=int(page))
        return jsonify({'success': True, 'data': result})
    except Exception as e:
        return jsonify({'success': False, 'message': str(e)}), 500


@app.route('/api/comment', methods=['POST'])
def post_comment():
    """发布评论"""
    try:
        data = request.get_json() or {}
        post_id = data.get('post_id')
        author_name = data.get('author_name', '').strip()
        author_email = data.get('author_email', '').strip()
        content = data.get('content', '').strip()
        parent = data.get('parent', 0)
        author_url = data.get('author_url', '')

        # 基础验证
        if not post_id:
            return jsonify({'success': False, 'message': '文章ID不能为空'}), 400
        if not author_name:
            return jsonify({'success': False, 'message': '请填写昵称'}), 400
        if not author_email or not re.match(r'^[^@]+@[^@]+\.[^@]+$', author_email):
            return jsonify({'success': False, 'message': '请填写有效的邮箱地址'}), 400
        if not content or len(content) < 2:
            return jsonify({'success': False, 'message': '评论内容不能为空'}), 400
        if len(content) > 2000:
            return jsonify({'success': False, 'message': '评论内容不能超过2000字'}), 400

        result = wp_api.post_comment(post_id, author_name, author_email, content, parent, author_url)

        if result['success']:
            return jsonify({'success': True, 'data': result['comment']})
        else:
            return jsonify({'success': False, 'message': result['message']}), 400
    except Exception as e:
        return jsonify({'success': False, 'message': str(e)}), 500


@app.route('/api/search', methods=['GET', 'POST'])
def search_articles():
    try:
        if request.method == 'POST':
            data = request.get_json() or {}
            keyword = data.get('keyword', '')
            page = data.get('page', 1)
        else:
            keyword = request.args.get('keyword', '')
            page = request.args.get('page', 1)

        if not keyword:
            return jsonify({'success': False, 'message': '搜索关键词不能为空'}), 400

        result = wp_api.search_posts(keyword, page=int(page))
        return jsonify({'success': True, 'data': {'articles': result['articles'], 'keyword': keyword, 'total': result['total']}})
    except Exception as e:
        return jsonify({'success': False, 'message': str(e)}), 500


@app.route('/api/categories', methods=['GET'])
def get_categories():
    try:
        categories = wp_api.get_categories()
        return jsonify({'success': True, 'data': categories})
    except Exception as e:
        return jsonify({'success': False, 'message': str(e)}), 500


@app.route('/api/tags', methods=['GET'])
def get_tags():
    try:
        tags = wp_api.get_tags()
        return jsonify({'success': True, 'data': tags})
    except Exception as e:
        return jsonify({'success': False, 'message': str(e)}), 500


@app.route('/api/posts-by-category', methods=['GET', 'POST'])
def get_posts_by_category():
    try:
        if request.method == 'POST':
            data = request.get_json() or {}
            category_id = data.get('category_id')
            page = data.get('page', 1)
        else:
            category_id = request.args.get('category_id')
            page = request.args.get('page', 1)

        if not category_id:
            return jsonify({'success': False, 'message': '分类ID不能为空'}), 400

        result = wp_api.get_posts_by_category(category_id, page=int(page))
        return jsonify({'success': True, 'data': result})
    except Exception as e:
        return jsonify({'success': False, 'message': str(e)}), 500


@app.route('/api/cache/clear', methods=['POST'])
def clear_cache():
    """清除内存缓存"""
    with _cache_lock:
        _cache.clear()
    return jsonify({'success': True, 'message': '缓存已清除'})


@app.route('/health', methods=['GET'])
def health_check():
    return jsonify({
        'status': 'ok',
        'timestamp': datetime.now().isoformat(),
        'api_base': API_BASE,
        'cache_size': len(_cache)
    })


if __name__ == '__main__':
    app.run(host='127.0.0.1', port=5000, debug=False, threaded=True)
