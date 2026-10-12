<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/* 主题用于存计数器的自定义字段名（都存在 Typecho 原生 typecho_fields 表里）。
   命名都限制在 ^[_a-z][_a-z0-9]*$，符合 Typecho 后台字段名校验规则。 */
if (!defined('WEUI_FIELD_VIEWS')) {
    define('WEUI_FIELD_VIEWS', 'views');
}
if (!defined('WEUI_FIELD_LIKES')) {
    define('WEUI_FIELD_LIKES', 'likes');
}
if (!defined('WEUI_FIELD_COMMENT_LIKES')) {
    define('WEUI_FIELD_COMMENT_LIKES', 'comment_likes');
}
/* 「按访客去重」时，Cookie 里最多记多少篇已读文章（约每篇 5 字节，远低于 Cookie 4KB 上限） */
if (!defined('WEUI_VIEWED_MAX')) {
    define('WEUI_VIEWED_MAX', 200);
}

/**
 * 主题设置项读取（带默认值，避免老配置里没有新字段时报 null）。
 */
function weuiOpt($name, $default = '')
{
    try {
        $v = \Widget\Options::alloc()->{$name};
        return ($v === null || $v === '') ? $default : $v;
    } catch (\Throwable $e) {
        return $default;
    }
}

/**
 * 主题设置
 */
function themeConfig($form)
{
    // ---------- 头部与外观 ----------
    $stickyHeader = new \Typecho\Widget\Helper\Form\Element\Radio(
        'stickyHeader',
        [
            '0' => _t('跟随页面滚动'),
            '1' => _t('固定在页面顶部')
        ],
        '0',
        _t('网站头部位置'),
        _t('选择网站头部在滚动时是固定在页面顶部，还是跟随页面一起滚动')
    );
    $form->addInput($stickyHeader);

    $showDescription = new \Typecho\Widget\Helper\Form\Element\Radio(
        'showDescription',
        [
            '1' => _t('显示'),
            '0' => _t('隐藏')
        ],
        '1',
        _t('首页显示副标题'),
        _t('是否在首页头部的主标题后面显示站点副标题（描述）')
    );
    $form->addInput($showDescription);

    $searchEnable = new \Typecho\Widget\Helper\Form\Element\Radio(
        'searchEnable',
        [
            '1' => _t('开启'),
            '0' => _t('关闭')
        ],
        '1',
        _t('头部搜索'),
        _t('开启后导航栏右侧出现搜索按钮，点击在头部展开 WeUI 搜索框，再点收起')
    );
    $form->addInput($searchEnable);

    // ---------- 配色 ----------
    $themeSwitch = new \Typecho\Widget\Helper\Form\Element\Radio(
        'themeSwitch',
        [
            '1' => _t('开启'),
            '0' => _t('关闭')
        ],
        '1',
        _t('显示明暗模式切换按钮'),
        _t('关闭后前台的「外观设置」悬浮按钮不再显示，访客将按「默认配色」显示')
    );
    $form->addInput($themeSwitch);

    $themeDefault = new \Typecho\Widget\Helper\Form\Element\Select(
        'themeDefault',
        [
            'system' => _t('跟随系统'),
            'time'   => _t('按时间自动'),
            'light'  => _t('亮色'),
            'dark'   => _t('暗色')
        ],
        'system',
        _t('默认配色'),
        _t('首次访问（未手动选择过）时使用的配色方案')
    );
    $form->addInput($themeDefault);

    $themeLightStart = new \Typecho\Widget\Helper\Form\Element\Text(
        'themeLightStart',
        null,
        '7',
        _t('亮色起始小时'),
        _t('「按时间自动」时，从几点开始用亮色（0-23 的整数）')
    );
    $form->addInput($themeLightStart);

    $themeLightEnd = new \Typecho\Widget\Helper\Form\Element\Text(
        'themeLightEnd',
        null,
        '19',
        _t('亮色结束小时'),
        _t('「按时间自动」时，到几点结束亮色、开始用暗色（0-23 的整数）')
    );
    $form->addInput($themeLightEnd);

    // ---------- 头像 ----------
    $gravatarCdn = new \Typecho\Widget\Helper\Form\Element\Text(
        'gravatarCdn',
        null,
        '',
        _t('Gravatar 头像加速地址'),
        _t('留空使用官方 gravatar.com。可填镜像地址，例如 https://cn.cravatar.com 或 https://cdn.v2ex.com/gravatar，'
            . '主题会自动在末尾拼接 /avatar/<hash>（已带 /avatar 的写法也支持，不会重复拼接）')
    );
    $form->addInput($gravatarCdn);

    // ---------- 底部 ----------
    $footerExtra = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'footerExtra',
        null,
        '',
        _t('页脚自定义内容（支持 HTML）'),
        _t('显示在版权信息上方，可放备案号、统计代码、友链等。支持 HTML 标签。')
    );
    $form->addInput($footerExtra);

    $analyticsCode = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'analyticsCode',
        null,
        '',
        _t('统计代码'),
        _t('直接粘贴第三方统计代码（支持 HTML / script，如百度统计、Google Analytics 等）。'
            . '代码只在首次加载时执行一次；配合无刷新时，主题会调用各统计平台的标准接口补发浏览，'
            . '因此不会因为无刷新而漏统计或重复统计。')
    );
    $form->addInput($analyticsCode);

    // ---------- 列表 / 评论 ----------
    $pageMode = new \Typecho\Widget\Helper\Form\Element\Radio(
        'pageMode',
        [
            'paged'    => _t('分页'),
            'button'   => _t('按钮加载更多'),
            'loadmore' => _t('瀑布流加载更多')
        ],
        'paged',
        _t('列表翻页方式'),
        _t('文章列表页（首页 / 分类 / 归档 / 搜索）的翻页方式：<br>'
            . '<b>分页</b>：底部显示「上一页 / 页码 / 下一页」；<br>'
            . '<b>按钮加载更多</b>：底部一个按钮，点击后追加下一页；<br>'
            . '<b>瀑布流加载更多</b>：滚动到底部自动追加下一页。')
    );
    $form->addInput($pageMode);

    $commentsMode = new \Typecho\Widget\Helper\Form\Element\Radio(
        'commentsMode',
        [
            'paged'    => _t('分页'),
            'button'   => _t('按钮加载更多'),
            'loadmore' => _t('瀑布流加载更多')
        ],
        'paged',
        _t('评论翻页方式'),
        _t('评论区的翻页方式：<br>'
            . '<b>分页</b>：显示页码条；<br>'
            . '<b>按钮加载更多</b>：点按钮追加下一页评论；<br>'
            . '<b>瀑布流加载更多</b>：滚动到底部自动追加下一页评论。<br>'
            . '需要先在 Typecho「评论设置」里开启评论分页（每页评论数）。')
    );
    $form->addInput($commentsMode);

    $commentFormPos = new \Typecho\Widget\Helper\Form\Element\Radio(
        'commentFormPos',
        [
            'top'    => _t('评论列表上方（文章下）'),
            'bottom' => _t('评论列表下方（评论下）')
        ],
        'top',
        _t('评论框位置'),
        _t('评论输入框放在哪里：<br>'
            . '<b>评论列表上方</b>：紧跟文章正文之后、评论列表之前（默认，先写评论再看评论）；<br>'
            . '<b>评论列表下方</b>：放在全部评论列表的最下面。')
    );
    $form->addInput($commentFormPos);

    $votePost = new \Typecho\Widget\Helper\Form\Element\Radio(
        'votePost',
        ['1' => _t('开启'), '0' => _t('关闭')],
        '1',
        _t('文章点赞'),
        _t('是否在文章页显示点赞按钮')
    );
    $form->addInput($votePost);

    $sharePost = new \Typecho\Widget\Helper\Form\Element\Radio(
        'sharePost',
        ['1' => _t('开启'), '0' => _t('关闭')],
        '1',
        _t('文章分享'),
        _t('是否在文章页显示分享按钮（与点赞、打赏同一行）。点击弹出分享面板：'
            . '微博 / QQ 空间 / Telegram / 微信扫码 / 复制链接 / 生成海报')
    );
    $form->addInput($sharePost);

    $voteComment = new \Typecho\Widget\Helper\Form\Element\Radio(
        'voteComment',
        ['1' => _t('开启'), '0' => _t('关闭')],
        '1',
        _t('评论点赞'),
        _t('是否在每条评论上显示点赞按钮')
    );
    $form->addInput($voteComment);

    $viewsUnique = new \Typecho\Widget\Helper\Form\Element\Radio(
        'viewsUnique',
        ['1' => _t('按访客去重'), '0' => _t('每次访问都计数')],
        '1',
        _t('阅读计数方式'),
        _t('按访客去重：用 Cookie 记录已读，同一访客反复打开同一篇文章只计一次；'
            . '每次访问都计数：刷新、重复打开都会 +1。')
    );
    $form->addInput($viewsUnique);

    // ---------- 打赏 ----------
    $rewardWechat = new \Typecho\Widget\Helper\Form\Element\Text(
        'rewardWechat',
        null,
        '',
        _t('微信打赏二维码'),
        _t('收款码图片地址。留空则不显示微信打赏；与支付宝都留空时整块打赏按钮都不显示')
    );
    $form->addInput($rewardWechat);

    $rewardAlipay = new \Typecho\Widget\Helper\Form\Element\Text(
        'rewardAlipay',
        null,
        '',
        _t('支付宝打赏二维码'),
        _t('收款码图片地址。两个都填时会在打赏弹窗里并排显示')
    );
    $form->addInput($rewardAlipay);

    $rewardText = new \Typecho\Widget\Helper\Form\Element\Text(
        'rewardText',
        null,
        '',
        _t('打赏说明文字'),
        _t('显示在打赏弹窗二维码上方，可留空')
    );
    $form->addInput($rewardText);

    // ---------- 页脚 ----------
    $siteStartYear = new \Typecho\Widget\Helper\Form\Element\Text(
        'siteStartYear',
        null,
        '',
        _t('建站年份'),
        _t('页脚版权显示为「Copyright © 建站年份-当前年份」。填 4 位年份，如 2020；留空则只显示当前年份')
    );
    $form->addInput($siteStartYear);

    // ---------- 音乐播放器 ----------
    $musicEnable = new \Typecho\Widget\Helper\Form\Element\Radio(
        'musicEnable',
        ['1' => _t('开启'), '0' => _t('关闭')],
        '0',
        _t('音乐播放器'),
        _t('开启后，侧边栏出现音乐按钮，点一下展开播放器；歌曲取下方填写的歌单')
    );
    $form->addInput($musicEnable);

    $musicServer = new \Typecho\Widget\Helper\Form\Element\Select(
        'musicServer',
        [
            'netease' => _t('网易云音乐'),
            'tencent' => _t('QQ 音乐'),
            'kugou'   => _t('酷狗音乐'),
            'baidu'   => _t('百度音乐'),
        ],
        'netease',
        _t('音乐平台'),
        _t('歌单来自哪个平台，需与歌单 ID 对应')
    );
    $form->addInput($musicServer);

    $musicList = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'musicList',
        null,
        '',
        _t('歌单 ID / 链接'),
        _t('一行一个。可直接填歌单或单曲 ID，也可粘贴分享链接（会自动解析出 ID）。例：883542351')
    );
    $form->addInput($musicList);

    $musicCookie = new \Typecho\Widget\Helper\Form\Element\Text(
        'musicCookie',
        null,
        '',
        _t('网易云 Cookie'),
        _t('留空则用内置兜底 Cookie。内置的会失效；失效时请从浏览器复制自己的 music.163.com Cookie（含 MUSIC_U）填这里')
    );
    $form->addInput($musicCookie);

    $musicQqCookie = new \Typecho\Widget\Helper\Form\Element\Text(
        'musicQqCookie',
        null,
        '',
        _t('QQ 音乐 Cookie'),
        _t('留空则用内置兜底 Cookie；失效时请填入自己的 y.qq.com Cookie')
    );
    $form->addInput($musicQqCookie);

    $musicSalt = new \Typecho\Widget\Helper\Form\Element\Text(
        'musicSalt',
        null,
        '',
        _t('音乐接口密钥'),
        _t('用于给音乐代理地址签名，防止接口被随意盗刷。建议随便填一串随机字符；留空则用默认值')
    );
    $form->addInput($musicSalt);

    $musicAutoplay = new \Typecho\Widget\Helper\Form\Element\Radio(
        'musicAutoplay',
        ['1' => _t('开启'), '0' => _t('关闭')],
        '0',
        _t('自动播放'),
        _t('打开页面自动播放（多数浏览器会拦截，用户首次交互后才会响）')
    );
    $form->addInput($musicAutoplay);

    $musicLyric = new \Typecho\Widget\Helper\Form\Element\Radio(
        'musicLyric',
        ['1' => _t('显示'), '0' => _t('不显示')],
        '1',
        _t('默认显示歌词'),
        _t('前台底部歌词条的初始状态；访客可在播放器里自行开关，选择会被记住')
    );
    $form->addInput($musicLyric);

    $musicMode = new \Typecho\Widget\Helper\Form\Element\Select(
        'musicMode',
        [
            'list'   => _t('列表循环'),
            'random' => _t('随机播放'),
            'single' => _t('单曲循环'),
        ],
        'list',
        _t('默认播放模式'),
        _t('前台可切换，选择会被记住')
    );
    $form->addInput($musicMode);
}

/**
 * 相对时间：几秒/几分钟/几小时/几天/几月/几年前。
 * 超过一年直接给完整日期，避免「1 年前」这种没信息量的相对值。
 */
function weuiRelativeTime($timestamp)
{
    $timestamp = (int) $timestamp;
    if ($timestamp <= 0) {
        return '';
    }

    $diff = time() - $timestamp;
    if ($diff < 0) {
        // 未来时间（定时发布等）直接显示完整日期
        return date('Y-m-d H:i:s', $timestamp);
    }

    if ($diff < 60) {
        return '刚刚';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . '分钟前';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . '小时前';
    }
    if ($diff < 2592000) { // 30 天
        return floor($diff / 86400) . '天前';
    }
    if ($diff < 31536000) { // 365 天
        return floor($diff / 2592000) . '个月前';
    }

    return date('Y-m-d H:i:s', $timestamp);
}

/**
 * 输出一个时间元素。
 *
 * @param int    $timestamp
 * @param string $class
 * @param bool   $interactive 是否允许「点击切换完整时间」。默认 true
 *                             （列表卡片与正文都用默认值，时间均可点击）。
 */
function weuiTime($timestamp, $class = '', $interactive = true)
{
    $timestamp = (int) $timestamp;
    if ($timestamp <= 0) {
        return;
    }
    $rel = weuiRelativeTime($timestamp);
    $full = date('Y-m-d H:i:s', $timestamp);
    if ($rel === '') {
        $rel = $full;
    }
    $cls = 'wf-time' . ($class !== '' ? ' ' . $class : '');
    ?>
    <?php if ($interactive): ?>
    <time class="<?php echo $cls; ?>"
          datetime="<?php echo date('c', $timestamp); ?>"
          title="<?php echo htmlspecialchars($full, ENT_QUOTES, 'UTF-8'); ?>"
          data-full="<?php echo htmlspecialchars($full, ENT_QUOTES, 'UTF-8'); ?>"
          data-rel="<?php echo htmlspecialchars($rel, ENT_QUOTES, 'UTF-8'); ?>"
          data-reltime="1"><?php echo htmlspecialchars($rel, ENT_QUOTES, 'UTF-8'); ?></time>
    <?php else: ?>
    <time class="<?php echo $cls; ?>"
          datetime="<?php echo date('c', $timestamp); ?>"><?php echo htmlspecialchars($rel, ENT_QUOTES, 'UTF-8'); ?></time>
    <?php endif; ?>
    <?php
}

/**
 * weuiTime() 的字符串版本（供拼接用）。
 */
function weuiTimeHtml($timestamp, $class = '', $interactive = true)
{
    ob_start();
    weuiTime($timestamp, $class, $interactive);
    return trim(ob_get_clean());
}

/**
 * 元信息用的小图标（内联 SVG，跟随 currentColor 自动适配明暗）。
 */
function weuiIcon($name)
{
    static $icons = [
        'author'   => '<path fill="currentColor" d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm0 2c-3.87 0-7 2.24-7 5v1h14v-1c0-2.76-3.13-5-7-5z"/>',
        'time'     => '<path fill="currentColor" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm0 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16zm.5-13H11v6l4.25 2.52.75-1.23-3.5-2.08V7z"/>',
        'category' => '<path fill="currentColor" d="M10 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-8l-2-2z"/>',
        'views'    => '<path fill="currentColor" d="M12 5c-5 0-9 4.5-9 7s4 7 9 7 9-4.5 9-7-4-7-9-7zm0 12a5 5 0 1 1 0-10 5 5 0 0 1 0 10zm0-8a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/>',
    ];
    if (!isset($icons[$name])) {
        return '';
    }
    return '<svg class="wf-icon" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true">' . $icons[$name] . '</svg>';
}

/**
 * 组装列表卡片与正文共用的四个元信息字段（各带图标）：
 *   作者 · 相对时间 · 分类 · 阅读次数
 * 作者与分类都是链接（点击进入对应归档），但颜色与其它字段一致——不用链接色。
 * 返回字段 HTML 数组，调用方自行套容器与分隔符。
 *
 * @param \Widget\Archive $archive
 * @return string[]
 */
function weuiMetaFields($archive)
{
    $fields = [];

    // 作者（可点击）
    $authorName = '';
    $authorUrl = '';
    try {
        $authorObj = $archive->author;
        if ($authorObj) {
            $authorName = trim((string) $authorObj->screenName);
            $authorUrl = trim((string) $authorObj->permalink);
        }
    } catch (\Throwable $e) {
        // 忽略：取不到就当没有作者
    }
    if ($authorName !== '') {
        $inner = ($authorUrl !== '' && $authorUrl !== '#')
            ? '<a class="wf-meta-link" href="' . htmlspecialchars($authorUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($authorName, ENT_QUOTES, 'UTF-8') . '</a>'
            : htmlspecialchars($authorName, ENT_QUOTES, 'UTF-8');
        $fields[] = weuiIcon('author') . $inner;
    }

    // 相对时间（可点击切换完整时间）
    $fields[] = weuiIcon('time') . weuiTimeHtml((int) $archive->created);

    // 分类（可点击）。相册文章不显示分类——列表与相册详情页都不显示。
    if (!weuiIsAlbum($archive)) {
        $cats = [];
        foreach ((array) $archive->categories as $c) {
            if (empty($c['name'])) {
                continue;
            }
            $name = htmlspecialchars((string) $c['name'], ENT_QUOTES, 'UTF-8');
            $cats[] = !empty($c['permalink'])
                ? '<a class="wf-meta-link" href="' . htmlspecialchars((string) $c['permalink'], ENT_QUOTES, 'UTF-8') . '">' . $name . '</a>'
                : $name;
        }
        if ($cats) {
            $fields[] = weuiIcon('category') . implode('/', $cats);
        }
    }

    // 阅读次数
    $fields[] = weuiIcon('views') . (int) weuiViewsGet((int) $archive->cid) . '次阅读';

    return $fields;
}

/**
 * 输出列表卡片的元信息行（`.weui-media-box__info`）。
 *
 * @param \Widget\Archive $archive
 */
function weuiListInfo($archive)
{
    $html = '';
    foreach (weuiMetaFields($archive) as $f) {
        $html .= '<span class="weui-media-box__info__meta">' . $f . '</span>';
    }
    echo '<div class="weui-media-box__info">' . $html . '</div>';
}

/**
 * 输出正文（文章页 / 相册页）的元信息行：
 *   作者 | xxx前 | 分类名 | xxx次阅读
 * 作者与分类可点击，颜色与其它字段一致。
 *
 * @param \Widget\Archive $archive
 */
function weuiArticleMeta($archive)
{
    echo '<p class="article-meta">'
        . implode('<span class="article-meta__sep">|</span>', weuiMetaFields($archive))
        . '</p>';
}

/**
 * 输出归档时间线（按年月分组，WeUI 风格：左日期 + 竖线圆点 + 右标题）。
 * 参考 https://www.ihewro.com/archives.html 的分组方式，但用主题自有样式实现，
 * 不引入任何额外组件库。相册文章（album=1）不出现在归档里。
 *
 * 只能用于「独立页面」场景：内部用 Recent::alloc() 重查，会重置归档组件的查询状态，
 * 所以 archives.php 必须先判断 is('page')。
 */
function weuiArchiveTimeline()
{
    $posts = \Widget\Contents\Post\Recent::alloc('pageSize=1000');
    $month = null;

    echo '<ol class="wf-timeline">';
    while ($posts->next()) {
        if (weuiIsAlbum($posts)) {
            continue;
        }
        $ts = (int) $posts->created;
        $key = date('Y-m', $ts);
        if ($key !== $month) {
            $month = $key;
            echo '<li class="wf-timeline__group"><span class="wf-timeline__month">'
                . date('Y', $ts) . '年' . (int) date('n', $ts) . '月'
                . '</span></li>';
        }
        ?>
        <li class="wf-timeline__item">
            <span class="wf-timeline__date"><?php echo (int) date('j', $ts); ?>日</span>
            <span class="wf-timeline__dot" aria-hidden="true"></span>
            <div class="wf-timeline__body">
                <a class="wf-timeline__title" href="<?php $posts->permalink(); ?>"><?php $posts->title(); ?></a>
            </div>
        </li>
        <?php
    }
    echo '</ol>';
}

/**
 * 输出列表分页。
 * - paged   ：上一页 / 当前页-总页数 / 下一页（按钮式，参考 ngx-weui 分页）
 * - button  ：一个「加载更多」按钮，点击抓取下一页并追加
 * - loadmore：瀑布流，滚动到底部自动抓取下一页并追加（配合 PJAX）
 *
 * @param \Widget\Archive $archive
 */
function weuiPagination($archive)
{
    // 只给「列表类」归档渲染分页。
    // 独立页面（page）也会被走到 index.php（最终兜底模板），那里调用本函数没有分页参数，
    // 早期用 $archive->parameter->pageSize 会读到 null 而报错，这里先挡掉。
    try {
        // 列表类页面：首页 / 归档（分类、标签、作者、日期）/ 搜索
        if (!$archive->is('index') && !$archive->is('archive') && !$archive->is('search')) {
            return;
        }
    } catch (\Throwable $e) {
        return;
    }

    if (!$archive->have()) {
        return;
    }

    $total = (int) $archive->getTotal();
    $pageSize = (int) $archive->parameter->pageSize;
    $totalPages = $pageSize > 0 ? (int) ceil($total / $pageSize) : 1;
    if ($totalPages <= 1) {
        return;
    }

    $current = (int) $archive->getCurrentPage();
    // options 是组件的 protected 属性：模板里 $this->options 能用，是因为模板运行在类作用域内；
    // 但本函数是全局函数，$archive->options 会触发 __get 并返回 null，故改用全局单例。
    $mode = 'paged';
    if (class_exists('\Widget\Options')) {
        $mode = (string) \Widget\Options::alloc()->pageMode;
    }

    if ($mode === 'button' || $mode === 'loadmore') {
        // 抓取「下一页」链接地址（无更多页时为空）
        ob_start();
        $archive->pageLink('', 'next');
        $nextHtml = trim(ob_get_clean());
        $nextUrl = '';
        if ($nextHtml !== '' && preg_match('/href="([^"]+)"/', $nextHtml, $m)) {
            $nextUrl = $m[1];
        }
        $nextAttr = htmlspecialchars($nextUrl, ENT_QUOTES, 'UTF-8');
        $fallback = $nextUrl !== '' ? $nextAttr : 'javascript:;';

        if ($mode === 'button') {
            // 按钮加载更多：点击才抓取下一页，不自动触发。
            ?>
            <div class="wf-loadmore wf-loadmore--button"
                 data-pagination="button"
                 data-next="<?php echo $nextAttr; ?>">
                <div class="weui-loadmore wf-loadmore__loading">
                    <i class="weui-primary-loading"></i>
                    <span class="weui-loadmore__tips"><?php _e('正在加载'); ?></span>
                </div>
                <a class="weui-btn weui-btn_default weui-btn_medium wf-loadmore__btn"
                   data-no-pjax
                   href="<?php echo $fallback; ?>"><?php _e('加载更多'); ?></a>
                <div class="weui-loadmore weui-loadmore_line wf-loadmore__end">
                    <span class="weui-loadmore__tips"><?php _e('已无更多内容'); ?></span>
                </div>
            </div>
            <?php
            return;
        }

        ?>
        <?php /* 瀑布流无限加载：滚到底部由 IntersectionObserver 自动抓取下一页并追加。
                 这里的 <a> 只是「无 JS / 观察器不可用」时的兜底，故标 data-no-pjax，
                 避免被 PJAX 拦成普通整页跳转。加载态与结束态沿用 WeUI 官方 loadmore 结构，
                 显隐由容器上的 is-loading / is-end 类控制（见 style.css）。 */ ?>
        <div class="wf-loadmore"
             data-pagination="loadmore"
             data-next="<?php echo $nextAttr; ?>">
            <div class="weui-loadmore wf-loadmore__loading">
                <i class="weui-primary-loading"></i>
                <span class="weui-loadmore__tips"><?php _e('正在加载'); ?></span>
            </div>
            <div class="weui-loadmore weui-loadmore_line wf-loadmore__end">
                <span class="weui-loadmore__tips"><?php _e('已无更多内容'); ?></span>
            </div>
            <a class="wf-loadmore__fallback"
               data-no-pjax
               href="<?php echo $fallback; ?>"><?php _e('加载更多'); ?></a>
        </div>
        <?php
        return;
    }

    // 分页模式
    ?>
    <div class="weui-pagination">
        <div class="weui-pagination__item weui-pagination__prev">
            <?php $archive->pageLink(_t('上一页'), 'prev'); ?>
        </div>
        <div class="weui-pagination__item weui-pagination__num"><?php echo $current; ?>/<?php echo $totalPages; ?></div>
        <div class="weui-pagination__item weui-pagination__next">
            <?php $archive->pageLink(_t('下一页'), 'next'); ?>
        </div>
    </div>
    <?php
}

/**
 * 文章编辑页的「自定义字段」里预置的字段。
 * 预置后编辑文章时会直接出现这些字段，无需手动添加。
 *
 * 说明：Typecho 会把字段的 description 渲染在输入框旁边（在 field-value 里），
 * 长说明会换行、把布局挤歪。所以这里**不写 description**，改为给输入框设 placeholder，
 * 说明直接显示在框内，既整洁又不会错位。
 */
function themeFields($layout)
{
    // 相册：用下拉选择，默认「否」。值为 '1' 时视为相册文章（与历史数据兼容）。
    $album = new \Typecho\Widget\Helper\Form\Element\Select(
        'album',
        [
            '0' => _t('否'),
            '1' => _t('是'),
        ],
        '0',
        _t('相册'),
        null
    );
    $layout->addItem($album);

    $thumb = new \Typecho\Widget\Helper\Form\Element\Text(
        'thumb',
        null,
        null,
        _t('缩略图'),
        null
    );
    $thumb->setInputsAttribute('placeholder', _t('留空自动取正文首图'));
    $layout->addItem($thumb);

    /*
     * 计数器字段（阅读次数 / 点赞 / 评论点赞）。
     *
     * 它们由前台自动维护，但必须出现在后台表单里：Typecho 保存文章时会删除
     * 「数据库里有、本次表单没提交」的自定义字段（EditTrait::applyFields），
     * 不注册的话，编辑一次文章就会把计数清零。
     *
     * 用 Hidden 再配空 label，让这三项在「自定义字段」里不占视觉位置：
     * 值仍会随表单提交回写，计数因此不会丢。
     */
    foreach ([WEUI_FIELD_VIEWS, WEUI_FIELD_LIKES, WEUI_FIELD_COMMENT_LIKES] as $wfCounter) {
        $hidden = new \Typecho\Widget\Helper\Form\Element\Hidden(
            $wfCounter,
            null,
            null,
            ''
        );
        $layout->addItem($hidden);
    }
}

/**
 * 相册列表用的文章组件：在 Recent 的基础上补上正文，
 * 以便列表卡片能取到摘要与正文首图（Recent 默认不取 text）。
 */
class WeuiAlbumRows extends \Widget\Contents\Post\Recent
{
    public function execute()
    {
        $this->parameter->setDefault(['pageSize' => 200]);
        $this->db->fetchAll(
            $this->select(
                'table.contents.cid',
                'table.contents.title',
                'table.contents.slug',
                'table.contents.created',
                'table.contents.modified',
                'table.contents.type',
                'table.contents.status',
                'table.contents.commentsNum',
                'table.contents.allowComment',
                'table.contents.authorId',
                'table.contents.parent',
                'table.contents.text'
            )
                ->where('table.contents.status = ?', 'publish')
                ->where('table.contents.created < ?', $this->options->time)
                ->where('table.contents.type = ?', 'post')
                ->order('table.contents.created', \Typecho\Db::SORT_DESC)
                ->limit($this->parameter->pageSize),
            [$this, 'push']
        );
    }
}

/**
 * 是否为相册文章（自定义字段 album = 1）。
 */
function weuiIsAlbum($archive)
{
    return trim((string) $archive->fields->album) === '1';
}

/**
 * 输出「上一篇 / 下一篇」导航（排除相册文章）。
 *
 * Typecho 自带的 thePrev/theNext 不经过 Archive::query 钩子（single 查询被跳过），
 * 因此会把相册文章也算进来。这里自己查相邻的、已发布的、非相册的普通文章。
 *
 * @param \Widget\Archive $archive 当前文章
 * @param string $label '_prev' 或 '_next'
 * @return string 相邻文章的 <a>，没有则返回空串
 */
function weuiNearPost($archive, $which)
{
    try {
        $db = \Typecho\Db::get();
        $cid = (int) $archive->cid;
        $created = (int) $archive->created;
        // 当前文章的状态/时区等信息：只用「已发布 + 同类型(post)」即可
        $exclude = weuiAlbumCids();

        $select = $db->select('table.contents.cid', 'table.contents.title', 'table.contents.slug',
                'table.contents.created', 'table.contents.type', 'table.contents.status')
            ->from('table.contents')
            ->where('table.contents.type = ?', 'post')
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.created <= ?', time() + 31536000); // 允许定时发布已到的

        if ($exclude) {
            $select->where('table.contents.cid NOT IN ?', $exclude);
        }

        if ($which === '_prev') {
            // 上一篇：更早的（created 更小，或同 created 时 cid 更小）
            $select->where('(table.contents.created < ? OR (table.contents.created = ? AND table.contents.cid < ?))',
                    $created, $created, $cid)
                ->order('table.contents.created', \Typecho\Db::SORT_DESC)
                ->order('table.contents.cid', \Typecho\Db::SORT_DESC);
        } else {
            $select->where('(table.contents.created > ? OR (table.contents.created = ? AND table.contents.cid > ?))',
                    $created, $created, $cid)
                ->order('table.contents.created', \Typecho\Db::SORT_ASC)
                ->order('table.contents.cid', \Typecho\Db::SORT_ASC);
        }
        $select->limit(1);

        $row = $db->fetchRow($select);
        if (!$row) {
            return '';
        }
        // 用 Typecho 路由生成 permalink（自动适配后台的永久链接格式）。
        // 站点根地址从 Options 组件取，避免有的 widget 实例上 ->options 为 null。
        $opts = \Typecho\Widget::widget('\Widget\Options');
        $url = \Typecho\Router::url('post', $row, $opts ? $opts->index : null);
        return '<a href="' . htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars((string) $row['title'], ENT_QUOTES, 'UTF-8') . '</a>';
    } catch (\Throwable $e) {
        return '';
    }
}

/**
 * 取文章缩略图：优先使用自定义字段 thumb，其次取正文中的第一张图片，
 * 再退到第一个视频的封面（poster）——这样只有视频的相册也能在列表里露出缩略图。
 */
function weuiGetThumb($archive)
{
    $thumb = trim((string) $archive->fields->thumb);
    if ($thumb !== '') {
        return $thumb;
    }
    $html = (string) $archive->content;
    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/<video\b[^>]*\bposter\s*=\s*["\']([^"\']+)["\']/i', $html, $m)) {
        return $m[1];
    }
    return '';
}

/**
 * 输出一条图文列表项的主体内容（标题 / 摘要）。
 * 供 weui-media-box_appmsg（有图）和 weui-media-box_text（无图）共用。
 *
 * 标题单独是链接（只有点标题才进入文章），这样元信息里的时间可以点击切换完整时间。
 * 元信息不在这里输出——由 weuiPostCard() 放在图片之外，独占整行。
 */
function weuiMediaBody($archive)
{
    ?>
    <a class="weui-media-box__title wf-card-title" href="<?php $archive->permalink(); ?>"><?php $archive->title(); ?></a>
    <?php
    ob_start();
    $archive->excerpt(80, '...');
    $desc = trim(ob_get_clean());
    if ($desc !== ''):
        ?>
        <p class="weui-media-box__desc"><?php echo $desc; ?></p>
    <?php endif;
}

/**
 * 输出一条文章列表卡片（缩略图 + 标题 / 摘要 / 元信息）。
 * 首页、分类、搜索结果共用，相册文章（album=1）由调用方跳过。
 *
 * 整条不是链接：图片与文字在一行（.wf-card__main），元信息独占下面一整行，
 * 这样图文模式下元信息不会挤在图片右侧、也不会换行错位。只有标题可点击进入。
 */
function weuiPostCard($archive)
{
    $thumb = weuiGetThumb($archive);
    ?>
    <div class="weui-media-box wf-card <?php echo $thumb ? 'weui-media-box_appmsg' : 'weui-media-box_text'; ?>">
        <div class="wf-card__main">
            <?php if ($thumb): ?>
                <div class="weui-media-box__hd">
                    <?php echo weuiLazyImg($thumb, 'weui-media-box__thumb'); ?>
                </div><?php /* 缩略图用自身尺寸类，不加 .wf-img，避免被 width:100% 撑开 */ ?>
            <?php endif; ?>
            <div class="weui-media-box__bd">
                <?php weuiMediaBody($archive); ?>
            </div>
        </div>
        <?php weuiListInfo($archive); ?>
    </div>
    <?php
}

/**
 * 读取某个标签上的属性值（支持双引号 / 单引号 / 无引号）。
 */
function weuiTagAttr($tag, $name)
{
    $n = preg_quote($name, '/');
    if (preg_match('/\b' . $n . '\s*=\s*"([^"]*)"/i', $tag, $m)) {
        return trim($m[1]);
    }
    if (preg_match('/\b' . $n . '\s*=\s*\'([^\']*)\'/i', $tag, $m)) {
        return trim($m[1]);
    }
    if (preg_match('/\b' . $n . '\s*=\s*([^\s"\'>=]+)/i', $tag, $m)) {
        return trim($m[1]);
    }
    return '';
}

/**
 * 取出文章正文里的媒体（图片 / 视频），按在正文中出现的顺序返回。
 * 每项：['type' => 'image'|'video', 'src' => 地址, 'poster' => 封面, 'caption' => 说明]
 *
 * 视频写法：直接在正文里插入 raw HTML，Typecho 的 Markdown 会原样保留：
 *   <video src="https://…/a.mp4" poster="https://…/cover.jpg" controls></video>
 * 也可以用 <video controls><source src="…"></video>；
 * 说明文字依次取 title → alt → data-caption。
 */
function weuiGetMedia($archive)
{
    $html = (string) $archive->content;
    $items = [];

    // 图片匹配 <img …>；视频匹配完整的 <video …>…</video>（没有闭合标签时只匹配开标签）
    if (!preg_match_all('/<img\b[^>]*>|<video\b[^>]*>(?:.*?<\/video>)?/is', $html, $m, PREG_OFFSET_CAPTURE)) {
        return $items;
    }

    foreach ($m[0] as $one) {
        $tag = $one[0];
        $pos = (int) $one[1];

        if (stripos($tag, '<img') === 0) {
            $src = weuiTagAttr($tag, 'src');
            if ($src === '') {
                continue;
            }
            $items[$pos] = [
                'type'    => 'image',
                'src'     => $src,
                'poster'  => '',
                'caption' => weuiTagAttr($tag, 'alt'),
            ];
            continue;
        }

        $src = weuiTagAttr($tag, 'src');
        if ($src === '' && preg_match('/<source\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']/i', $tag, $sm)) {
            $src = trim($sm[1]);
        }
        if ($src === '') {
            continue;
        }
        $caption = weuiTagAttr($tag, 'title');
        if ($caption === '') {
            $caption = weuiTagAttr($tag, 'alt');
        }
        if ($caption === '') {
            $caption = weuiTagAttr($tag, 'data-caption');
        }
        $items[$pos] = [
            'type'    => 'video',
            'src'     => $src,
            'poster'  => weuiTagAttr($tag, 'poster'),
            'caption' => $caption,
        ];
    }

    ksort($items);
    return array_values($items);
}

/**
 * 懒加载占位图（一个内联的「透明」SVG，无需额外请求）。
 *
 * 用透明而不是固定灰：图片元素的可见底色交给 CSS 的 background-color，
 * 这样能随亮/暗主题自动变化，避免暗色模式下闪一块白。
 */
function weuiPlaceholderSrc()
{
    return 'data:image/svg+xml;charset=utf-8,'
        . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 300"></svg>');
}

/**
 * 输出一张「自研懒加载」图片：src 先给灰色占位图，真实地址写入 data-wf-src，
 * 进入视口后由 theme.js 的 IntersectionObserver 换回真图。
 *
 * 注意：只有「需要整宽显示」的正文/相册图片才传 $fullWidth=true（会带 .wf-img，
 * 该类的 CSS 是 width:100%）。表情/头像等小图必须保持自己的尺寸类，否则会被
 * .wf-img 撑满整行。
 *
 * @param string $src        真实图片地址
 * @param string $class      图片自身的 class（原样保留，不追加 wf-img）
 * @param string $alt        alt 文本
 * @param string $extra      额外属性串（如 width="28" height="28"）
 * @param bool   $fullWidth  是否按整宽懒加载图处理（加 .wf-img）
 */
function weuiLazyImg($src, $class = '', $alt = '', $extra = '', $fullWidth = false)
{
    if ($src === '' || $src === null) {
        return '';
    }
    $cls = trim($class . ($fullWidth ? ' wf-img' : ''));
    $out = '<img' . ($cls !== '' ? ' class="' . htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') . '"' : '')
        . ' src="' . weuiPlaceholderSrc() . '"'
        . ' data-wf-src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"';
    $out .= ' alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '"';
    if ($extra !== '') {
        $out .= ' ' . $extra;
    }
    $out .= ' loading="lazy" decoding="async">';
    return $out;
}

/**
 * 视频缩略图的占位（深色）。没有封面图时用它，白色播放按钮才看得清。
 */
function weuiVideoPlaceholderSrc()
{
    return 'data:image/svg+xml;charset=utf-8,'
        . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 300"><rect width="300" height="300" fill="#2b2b2b"/></svg>');
}

/**
 * 文章目录：扫描正文里的 <h2>/<h3>/<h4>，给没有 id 的标题补上 id，并生成目录树。
 *
 * 返回 ['html' => 处理后的正文, 'items' => [ ['id','text','level'], … ] ]。
 * items 会存到全局 $GLOBALS['weui_toc'] 供 footer.php 渲染目录按钮/面板（同一次请求内，
 * post.php 先于 footer.php 执行，所以能取到）。
 *
 * 只认 h2~h4：h1 是文章标题，h5/h6 太少用，纳入反而让目录过长。
 */
function weuiBuildToc($html)
{
    $html = (string) $html;
    $items = [];
    $used = [];

    // 匹配 <h2..h4 …>文本</h2..h4>
    $html = preg_replace_callback(
        '/<h([234])\b([^>]*)>(.*?)<\/h\1>/is',
        function ($m) use (&$items, &$used) {
            $level = (int) $m[1];
            $attrs = $m[2];
            $inner = $m[3];

            // 纯文本标题：去掉内联标签（如 <strong>）再取文字，用于生成 id 与显示
            $text = trim(preg_replace('/\s+/u', ' ', strip_tags($inner)));
            if ($text === '') {
                return $m[0];
            }

            // 已有 id 就沿用，否则用「标题文字」生成一个可读且唯一的 id
            $id = '';
            if (preg_match('/\bid\s*=\s*["\']([^"\']+)["\']/i', $attrs, $im)) {
                $id = trim($im[1]);
            }
            if ($id === '') {
                $id = weuiTocSlug($text);
                // 去重：重名标题加 -2、-3 …
                $base = $id;
                $n = 1;
                while (isset($used[$id])) {
                    $n++;
                    $id = $base . '-' . $n;
                }
                // 写回 id 属性
                if (preg_match('/\bid\s*=\s*["\']/i', $attrs)) {
                    $attrs = preg_replace('/\bid\s*=\s*["\'][^"\']*["\']/i', 'id="' . $id . '"', $attrs, 1);
                } else {
                    $attrs .= ' id="' . $id . '"';
                }
            }
            $used[$id] = true;

            $items[] = ['id' => $id, 'text' => $text, 'level' => $level];
            return '<h' . $level . $attrs . '>' . $inner . '</h' . $level . '>';
        },
        $html
    );

    return ['html' => $html, 'items' => $items];
}

/**
 * 由标题文字生成锚点 id：保留中日韩文字 / 字母 / 数字，其余折成短横线。
 * 纯符号标题退化为 t-<hash>，保证始终有可用 id。
 */
function weuiTocSlug($text)
{
    $s = trim((string) $text);
    // 去掉 HTML 实体、空白
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = preg_replace('/\s+/u', '-', $s);
    // 只保留：汉字、字母、数字、连字符
    $s = preg_replace('/[^\x{4e00}-\x{9fa5}\x{3040}-\x{30ff}a-zA-Z0-9\-]+/u', '', $s);
    $s = trim($s, '-');
    if ($s === '') {
        $s = 't-' . substr(md5($text), 0, 8);
    }
    return $s;
}

/**
 * 处理正文内容：给图片加懒加载，并把正文里的图片 / 视频接入 Fancybox 灯箱。
 *
 * 图片：
 *   - 真实地址写入 data-wf-src，src 换成灰色占位图（进入视口后再由 JS 换回并淡入）
 *   - 加上 wf-img 类，供懒加载动画使用
 * 灯箱：正文里的每个 <img> / <video> 都包成一个 <a data-fancybox="wf-content"> 触发器，
 *   让 Fancybox 接管点击。图片触发图片灯箱，视频触发 html5video 灯箱。
 *   已经是 <a> 包裹的图片（例如指向原图的链接）不再重复包裹，只在原 <a> 上补属性。
 */
function weuiPrepareContent($html)
{
    $placeholder = weuiPlaceholderSrc();

    // 1) 先把图片/视频包进 Fancybox 触发器（在替换 src 之前做，便于识别原始结构）
    $html = weuiWrapContentMedia((string) $html);

    // 2) 再给 <img> 换占位 + 懒加载属性
    return preg_replace_callback('/<img\b([^>]*)>/i', function ($m) use ($placeholder) {
        $attrs = $m[1];
        if (!preg_match('/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $attrs, $s)) {
            return $m[0];
        }
        $src = $s[1];
        $attrs = preg_replace('/\ssrc\s*=\s*["\'][^"\']*["\']/i', '', $attrs);
        $attrs = preg_replace('/\sdata-wf-src\s*=\s*["\'][^"\']*["\']/i', '', $attrs);
        // 已是占位图（data:）的跳过，避免重复处理
        if (strpos($src, 'data:image/svg+xml') === 0) {
            return $m[0];
        }
        $attrs = preg_replace('/\sclass\s*=\s*["\']([^"\']*)["\']/i', ' class="$1 wf-img"', $attrs, 1, $count);
        if (!$count) {
            $attrs .= ' class="wf-img"';
        }
        $attrs .= ' data-wf-src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"';
        $attrs .= ' src="' . $placeholder . '"';
        $attrs .= ' loading="lazy" decoding="async"';
        return '<img' . $attrs . '>';
    }, $html);
}

/**
 * 把正文里的 <img> / <video> 包成 Fancybox 触发器。
 *
 * - 顶层 <img>：包成 <a data-fancybox="wf-content" href="原图">…</a>
 * - 已被 <a> 包着的 <img>：在 <a> 上补 data-fancybox / href 指向图片，避免嵌套 <a>
 * - <video>：包成 <a data-fancybox data-type="html5video" href="视频" data-poster="封面">
 *
 * 说明：说明文字取 alt（图片）/ title→alt（视频），写进 data-caption。
 * 这里只做一次正则级别的包裹，已带 data-fancybox 的元素跳过（防重复处理）。
 */
function weuiWrapContentMedia($html)
{
    if ($html === '') {
        return $html;
    }

    // 1) 先处理 <video …>…</video>（含无闭合的情况）
    $html = preg_replace_callback('/<video\b[^>]*>(?:.*?<\/video>)?/is', function ($m) {
        $tag = $m[0];
        if (stripos($tag, 'data-fancybox') !== false) {
            return $tag;
        }
        $src = weuiTagAttr($tag, 'src');
        if ($src === '' && preg_match('/<source\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']/i', $tag, $sm)) {
            $src = trim($sm[1]);
        }
        if ($src === '') {
            return $tag;
        }
        $poster = weuiTagAttr($tag, 'poster');
        $caption = weuiTagAttr($tag, 'title');
        if ($caption === '') {
            $caption = weuiTagAttr($tag, 'alt');
        }
        if ($caption === '') {
            $caption = weuiTagAttr($tag, 'data-caption');
        }
        $a = '<a href="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-fancybox="wf-content" data-type="html5video" data-no-pjax';
        if ($poster !== '') {
            $a .= ' data-poster="' . htmlspecialchars($poster, ENT_QUOTES, 'UTF-8') . '"';
        }
        if ($caption !== '') {
            $a .= ' data-caption="' . htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') . '"';
        }
        $a .= ' class="wf-media-link">';
        return $a . $tag . '</a>';
    }, $html);

    // 2) 再处理被 <a> 包着的 <img>（在外层 <a> 上补 Fancybox 属性）
    //    简单匹配 <a …>…<img …>…</a>
    $html = preg_replace_callback(
        '/<a\b([^>]*)>(\s*)(<img\b[^>]*>)(\s*)<\/a>/is',
        function ($m) {
            $aAttrs = $m[1];
            $img = $m[3];
            if (stripos($aAttrs, 'data-fancybox') !== false) {
                return $m[0];
            }
            $src = weuiTagAttr($img, 'src');
            if ($src === '') {
                return $m[0];
            }
            $caption = weuiTagAttr($img, 'alt');
            // 保留作者原本的 href（常见写法是指向大图）；只有 href 缺失或为
            // javascript:/# 时才退回图片自身地址。
            $href = weuiTagAttr($m[0], 'href');
            if ($href === '' || preg_match('/^(javascript:|#)/i', $href)) {
                $aAttrs = preg_replace('/\shref\s*=\s*["\'][^"\']*["\']/i', '', $aAttrs);
                $aAttrs .= ' href="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"';
            }
            $aAttrs .= ' data-fancybox="wf-content" data-type="image" data-no-pjax';
            if ($caption !== '') {
                $aAttrs .= ' data-caption="' . htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') . '"';
            }
            return '<a' . $aAttrs . '>' . $m[2] . $img . $m[4] . '</a>';
        },
        $html
    );

    // 3) 最后处理剩下的裸 <img>（没有被 <a> 包着的）
    //    先把已处理过的 <a…>…</a> 整段挖掉，避免把里面包过的 <img> 再包一次
    $stash = [];
    $html = preg_replace_callback('/<a\b[^>]*>.*?<\/a>/is', function ($m) use (&$stash) {
        $key = "\x00WFSTASH" . count($stash) . "\x00";
        $stash[$key] = $m[0];
        return $key;
    }, $html);

    $html = preg_replace_callback('/<img\b[^>]*>/i', function ($m) {
        $img = $m[0];
        $src = weuiTagAttr($img, 'src');
        if ($src === '') {
            return $img;
        }
        $caption = weuiTagAttr($img, 'alt');
        $a = '<a href="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-fancybox="wf-content" data-type="image" data-no-pjax class="wf-media-link"';
        if ($caption !== '') {
            $a .= ' data-caption="' . htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') . '"';
        }
        $a .= '>';
        return $a . $img . '</a>';
    }, $html);

    if ($stash) {
        $html = strtr($html, $stash);
    }

    return $html;
}


/**
 * 自定义评论输出（覆盖 Typecho 默认的 threadedComments 主题函数）。
 * 头像 + 昵称 + 时间 + 操作区（点赞、回复）同一行，操作区靠右（与默认主题的「回复」同位置）；
 * 内容另起一行。
 *
 * 关键：所有评论**平级输出在同一个 <ol class="comment-list"> 里**，不再层层嵌套。
 * Typecho 默认会按树递归包出 <div class="comment-children">，DOM 实际有 N 层深，
 * 只靠 CSS 把深层 margin 归零来「假装只有一层」，结果就是各层 padding 不同、
 * 上下评论的间距对不上。这里改成：当前 <li> 先闭合，再递归输出子评论作为兄弟节点，
 * 缩进完全交给 CSS 的 .comment-child 一层 margin 表达。
 *
 * 注意：Typecho 点击「回复」时会把表单移动到这个 <li> 内，位置取决于回复按钮所在的
 * 「直接子元素」——它会把表单插到该子元素之后。这里回复按钮在 .comment-head 里，
 * 但主题在 assets/theme.js 里拦截了点击、自己把表单插到正文之后，所以回复框会紧跟在
 * 正文下面，而不会跑到这条评论的子回复下方。
 */
function threadedComments(\Widget\Comments\Archive $comments, $options)
{
    $level = (int) $comments->levels;
    $parentCoid = $level > 0 ? (int) $comments->parent : 0;
    $parentAuthor = $parentCoid > 0 ? weuiCommentParentAuthor($parentCoid) : '';

    /* 「回复」链接里也带页码（/文章/comment-page-N?replyTo=X#respond-…），
       核心那套算法会算偏，这里按分页的真实口径改对 ——
       否则无 JS 时点「回复」会跳到不含这条评论的页上。详见文件末尾的说明。 */
    $comments->commentPage = weuiCommentRealPage($comments);
    ?>
    <li id="<?php $comments->theId(); ?>" data-depth="<?php echo $level; ?>" data-parent="<?php echo $parentCoid; ?>" class="comment-body<?php echo $level > 0 ? ' comment-child' : ' comment-parent'; ?><?php $comments->alt(' comment-odd', ' comment-even'); ?>">
        <div class="comment-head">
            <span class="comment-avatar"><?php weuiCommentAvatar($comments, 28); ?></span>
            <cite class="comment-name"><?php $comments->author(); ?></cite>
            <?php
            /* 还没通过审核的评论：Typecho 只会把它渲染给「提交者本人」（凭 cookie），
               别人页面上压根没有这条。这里补一个标记说明「别人暂时看不到」，
               审核一通过这行就会自动消失（状态由服务端判定，不依赖浏览器缓存）。 */
            $weuiCmtStatus = (string) $comments->status;
            ?>
            <?php if ($weuiCmtStatus !== '' && strtolower($weuiCmtStatus) !== 'approved'): ?>
                <span class="comment-pending-tag" title="<?php _e('评论已提交，通过审核后所有人可见'); ?>"><?php _e('待审核'); ?></span>
            <?php endif; ?>
            <?php weuiTime((int) $comments->created, 'comment-date'); ?>
            <?php /* 操作区放在头部行右侧（与默认主题「回复」同位置）：点赞在前、回复在后。
                     这样回复框插到正文之后也不会把操作区挤到下方。 */ ?>
            <span class="comment-actions">
                <?php if (weuiOpt('voteComment', '1') === '1'): ?>
                    <?php weuiVoteHtml('comment', (int) $comments->coid); ?>
                <?php endif; ?>
                <?php weuiCommentReplyLink($comments, $options); ?>
            </span>
        </div>
        <div class="comment-content"><?php weuiCommentBody($comments, $parentAuthor, $parentCoid); ?></div>
    </li>
    <?php
    /* 递归输出子评论：不包容器，直接作为兄弟节点接在同一个 <ol> 里。
       listComments 的 before/after 已置空，所以这里不会再产出嵌套的 <ol>。 */
    $comments->threadedComments();
}

/**
 * 输出「回复」链接。
 *
 * 不用 Typecho 自带的 $comments->reply()：它内部有 isTopLevel 判断
 * （levels > commentsMaxNestingLevels - 2 就不给回复按钮），
 * 于是**嵌套最深的那一层评论会没有回复按钮**——这就是线上「有几条不能回复」的原因。
 * 这里改成自己输出，只要允许评论就始终显示回复按钮。
 * 链接地址与 Typecho 原版一致（?replyTo=coid#respondId），无 JS 时也能用。
 */
function weuiCommentReplyLink(\Widget\Comments\Archive $comments, $options)
{
    try {
        $optionsObj = \Widget\Options::alloc();
        if (empty($optionsObj->commentsThreaded)) {
            return;
        }
        $allow = true;
        try {
            $allow = (bool) $comments->parameter->allowComment;
        } catch (\Throwable $e) {
            $allow = true;
        }
        if (!$allow) {
            return;
        }

        $coid = (int) $comments->coid;
        $respondId = '';
        try {
            $respondId = (string) $comments->parameter->respondId;
        } catch (\Throwable $e) {
            $respondId = '';
        }
        // 去掉 permalink 末尾的 #theId，再拼上 replyTo 参数
        $base = explode('#', (string) $comments->permalink);
        $href = $base[0] . '?replyTo=' . $coid . ($respondId !== '' ? '#' . $respondId : '');
        $word = (string) $options->replyWord;
        ?>
        <a href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>" rel="nofollow"
           data-coid="<?php echo $coid; ?>"><?php echo htmlspecialchars($word, ENT_QUOTES, 'UTF-8'); ?></a>
        <?php
    } catch (\Throwable $e) {
        // 出错就不输出，保证页面可用
    }
}

/**
 * 表情 / 贴纸名单（assets/emoticons/manifest.json，由 tools/fetch-emoticons.py 生成）。
 * 只用于校验短代码里的名字，避免拼出任意路径。
 */
function weuiEmoticonManifest()
{
    static $manifest = null;
    if ($manifest !== null) {
        return $manifest;
    }

    $manifest = ['stickers' => [], 'emoji' => []];
    $file = __DIR__ . '/assets/emoticons/manifest.json';
    if (is_file($file)) {
        $data = json_decode((string) file_get_contents($file), true);
        if (is_array($data)) {
            $manifest = array_merge($manifest, $data);
        }
    }

    return $manifest;
}

/**
 * 表情短代码 -> 图片地址；名字不在名单里则返回空串。
 *
 * 注意：这里不能用 $options->themeUrl('...')——它是「输出」方法，会直接 echo 并返回 null
 * （Typecho 里只有在第二个参数传入主题名时才返回字符串）。用 $options->themeUrl 属性
 * 拿到主题目录地址，再交给 Common::url() 拼接。
 */
function weuiEmoticonUrl($kind, $name)
{
    $manifest = weuiEmoticonManifest();
    $name = (string) $name;

    if ($kind === 'sticker' && in_array($name, $manifest['stickers'], true)) {
        $path = 'assets/emoticons/stickers/' . rawurlencode($name) . '.png';
    } elseif ($kind === 'emoji' && in_array($name, $manifest['emoji'], true)) {
        $path = 'assets/emoticons/emoji/' . rawurlencode($name) . '.svg';
    } else {
        return '';
    }

    try {
        $base = (string) \Widget\Options::alloc()->themeUrl;
    } catch (\Throwable $e) {
        $base = '';
    }

    return $base !== '' ? \Typecho\Common::url($path, $base) : $path;
}

/**
 * 反垃圾令牌（提交时表单里的隐藏域 `_`）。
 *
 * Typecho 默认是前端 JS 在 scroll / mousemove / keyup / touchstart 之后才把 `_`
 * 隐藏域插进表单；一旦走 PJAX 换页或评论无刷新替换了评论区，这个隐藏域就会丢失，
 * 提交时 protect() 校验不过，会静默 goBack() 跳回原页——表现就是「无刷新发不出评论」。
 * 这里改成在服务端直接渲染进表单，并保证 PJAX / AJAX 换新表单时也带着它。
 * 令牌算法与 Typecho 自带 JS 完全一致：md5(secret[&authCode&uid] . '&' . 当前地址)。
 */
function weuiAntiSpamToken()
{
    try {
        if (empty(\Widget\Options::alloc()->commentsAntiSpam)) {
            return '';
        }
        return \Widget\Security::alloc()->getToken(\Typecho\Request::getInstance()->getRequestUrl());
    } catch (\Throwable $e) {
        return '';
    }
}

/**
 * 把评论正文里的 [sticker:名字] / [emoji:编号] 短代码换成表情图片。
 *
 * 数据库里存的是短代码文本（纯文本，天然安全），只在输出时映射成
 * 主题内置的固定图片，名字必须命中名单，因此不存在注入风险。
 */
function weuiEmoticonsInHtml($html)
{
    return preg_replace_callback('/\[(sticker|emoji):([^\]\s]{1,32})\]/u', function ($m) {
        $kind = $m[1];
        $name = $m[2];
        $url = weuiEmoticonUrl($kind, $name);
        if ($url === '') {
            return $m[0];
        }
        $shortcode = htmlspecialchars($m[0], ENT_QUOTES, 'UTF-8');
        $class = $kind === 'sticker' ? 'wf-sticker' : 'wf-biaoqing';
        return weuiLazyImg($url, $class, $m[0], 'data-shortcode="' . $shortcode . '"');
    }, (string) $html);
}

/**
 * 输出评论正文；若是回复，把 `@某某` 插到正文第一段内部开头，
 * 保证 @ 和正文在同一行，而不是各占一行。
 */
function weuiCommentBody(\Widget\Comments\Archive $comments, $parentAuthor, $parentCoid = 0)
{
    ob_start();
    $comments->content();
    $html = weuiEmoticonsInHtml(ob_get_clean());

    if ($parentAuthor !== '') {
        // @某人：链接指向被回复评论的锚点，点一下跳到那条评论。
        // 锚点 id 与评论 <li> 的 id 一致（comment-<coid>，由 $comments->theId() 输出）。
        $href = $parentCoid > 0 ? '#comment-' . (int) $parentCoid : 'javascript:;';
        $mention = '<a class="comment-mention" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-mention-coid="' . (int) $parentCoid . '">@'
            . htmlspecialchars($parentAuthor, ENT_QUOTES, 'UTF-8') . '</a> ';
        if (preg_match('/^\s*<(p|div|li|blockquote|h[1-6])\b[^>]*>/i', $html, $m)) {
            $pos = strpos($html, $m[0]) + strlen($m[0]);
            $html = substr($html, 0, $pos) . $mention . substr($html, $pos);
        } else {
            $html = $mention . $html;
        }
    }

    echo $html;
}


/**
 * 相册文章（自定义字段 album = 1）的 cid 列表（按请求缓存）。
 */
function weuiAlbumCids()
{
    static $cids = null;
    if ($cids !== null) {
        return $cids;
    }

    $cids = [];
    try {
        $db = \Typecho\Db::get();
        $rows = $db->fetchAll(
            $db->select('cid')->from('table.fields')
                ->where('name = ?', 'album')
                ->where('str_value = ?', '1')
        );
        foreach ($rows as $row) {
            $cids[] = (int) $row['cid'];
        }
    } catch (\Throwable $e) {
        $cids = [];
    }

    return $cids;
}

/**
 * 在 SQL 查询层排除相册文章。
 *
 * 只在输出阶段过滤是不够的：那样数据库查询和分页仍带着相册文章，
 * 会导致列表每页条数变少、总页数偏多。这里挂到 Typecho 的 Archive::query 钩子，
 * 在真正查询前加上 `cid NOT IN (相册 cid)`，并同步替换 countSql，
 * 让「每页条数」和「总页数」都不把相册算进去。
 *
 * 说明：
 * - 跳过 single 查询，否则相册文章自身会被排除而 404；
 * - 出错时退回「不排除」，保证页面可用；
 * - 主题内注册，无需插件。
 */
function weuiExcludeAlbumsFromQuery()
{
    if (!class_exists('\Typecho\Plugin') || !method_exists('\Typecho\Db', 'get')) {
        return;
    }

    try {
        \Typecho\Plugin::factory('Widget\Archive')->query = function ($archive, $select) {
            $db = \Typecho\Db::get();

            try {
                $isSingle = method_exists($archive, 'is') ? $archive->is('single') : false;
                if (!$isSingle) {
                    $cids = weuiAlbumCids();
                    if ($cids) {
                        $select->where('table.contents.cid NOT IN ?', $cids);

                        // 同步修正计数用的 SQL（去掉分页/排序，避免影响 COUNT）
                        $count = clone $select;
                        $count->cleanAttribute('limit')
                            ->cleanAttribute('offset')
                            ->cleanAttribute('order');
                        $archive->setCountSql($count);
                    }
                }
            } catch (\Throwable $e) {
                // 排除失败则维持原查询，不影响页面
            }

            $db->fetchAll($select, [$archive, 'push']);
        };
    } catch (\Throwable $e) {
        // 注册失败不影响主题其它功能
    }
}

// functions.php 在 Archive 执行查询之前被加载，这里注册的钩子对本请求立即生效
weuiExcludeAlbumsFromQuery();


/**
 * 前台评论渲染需要的内存级设置（只改本次请求，不动数据库、不影响后台设置界面）：
 *
 * 1. 开启 Markdown：评论支持标题 / 列表 / 引用 / 代码 / 表格等语法。
 * 2. 放宽允许的 HTML 标签：Typecho 默认只保留 <p><br>，
 *    会把 Markdown 生成的表格、列表、代码块等全部剥掉——这是「评论不支持表格」的根因。
 *    这里与站点原有白名单合并（原有白名单放后面，保证 a[href] 等已有属性不被覆盖）。
 * 3. 提高最大嵌套层数：Typecho 达到上限后会把深层回复重新挂到上层评论，
 *    导致「回复的那条在它下面」失效。设为上限 7，让回复始终挂在被回复评论之下。
 */
function weuiCommentRenderTweaks()
{
    if (!class_exists('\Widget\Options')) {
        return;
    }

    try {
        $options = \Widget\Options::alloc();

        // 1. 评论使用 Markdown
        $options->commentsMarkdown = 1;

        // 2. 合并允许的标签（Markdown 常见输出；不含 a，避免放开 href 的安全风险，
        //    站点若已自行允许 a 会因放在后面而被保留）
        $extraTags = '<strong><b><em><i><del><ins><code><pre><blockquote><ul><ol><li><hr>'
            . '<h1><h2><h3><h4><h5><h6><table><thead><tbody><tfoot><tr><th><td><sup><sub>';
        $existing = (string) $options->commentsHTMLTagAllowed;
        $options->commentsHTMLTagAllowed = $extraTags . $existing;

        // 3. 真正嵌套（减少深层回复被重新挂到上层）
        $options->commentsMaxNestingLevels = 7;
    } catch (\Throwable $e) {
        // 任何异常都不影响页面
    }
}

if (!defined('__TYPECHO_ADMIN__')) {
    weuiCommentRenderTweaks();
}


/**
 * 取某条评论的作者名（用于回复时显示 @某某）。
 * 结果按 coid 缓存，避免同一次请求里重复查询。
 */
function weuiCommentParentAuthor($coid)
{
    static $cache = [];
    $coid = (int) $coid;
    if ($coid <= 0) {
        return '';
    }
    if (array_key_exists($coid, $cache)) {
        return $cache[$coid];
    }

    $cache[$coid] = '';
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow(
            $db->select('author')->from('table.comments')->where('coid = ?', $coid)->limit(1)
        );
        if ($row && isset($row['author'])) {
            $cache[$coid] = (string) $row['author'];
        }
    } catch (\Throwable $e) {
        // 查询失败则不加 @
    }

    return $cache[$coid];
}

/**
 * 评论区翻页：分页 / 「加载更多」/ 瀑布流无限加载。
 * 需要先在 Typecho「评论设置」里开启评论分页（commentsPageBreak）。
 *
 * @param \Widget\Comments\Archive $comments
 */
function weuiCommentsNav($comments)
{
    // Typecho 的 pageNav() 输出结构：
    //   <ol class="page-navigator"> 或自定义 wrapTag / wrapClass
    //   里面是 <li class="prev|current|next"> 与纯数字页 <li>
    // 这里统一用 ol/li 的语义化结构（早期用 div 包 li 是非法 HTML，
    // 浏览器会把它拆开，导致 CSS 选择器全部落空、样式错乱）。
    ob_start();
    $comments->pageNav('上一页', '下一页', 3, '...', [
        'wrapTag'   => 'ol',
        'wrapClass' => 'page-navigator',
        'itemTag'   => 'li',
    ]);
    $html = trim(ob_get_clean());
    if ($html === '') {
        return;
    }

    $mode = 'paged';
    if (class_exists('\Widget\Options')) {
        $mode = (string) \Widget\Options::alloc()->commentsMode;
    }

    // 「下一页」链接：三种模式都要用（按钮 / 瀑布流都靠它推进）
    $nextUrl = '';
    if (preg_match('/<li[^>]*class="[^"]*\bnext\b[^"]*"[^>]*>\s*<a[^>]+href="([^"]+)"/i', $html, $m)) {
        $nextUrl = $m[1];
    } elseif (preg_match('/class="[^"]*\bnext\b[^"]*"[^>]*>\s*<a[^>]+href="([^"]+)"/i', $html, $m)) {
        $nextUrl = $m[1];
    }
    $nextAttr = htmlspecialchars($nextUrl, ENT_QUOTES, 'UTF-8');

    // 「上一页」链接同理
    $prevUrl = '';
    if (preg_match('/<li[^>]*class="[^"]*\bprev\b[^"]*"[^>]*>\s*<a[^>]+href="([^"]+)"/i', $html, $m)) {
        $prevUrl = $m[1];
    } elseif (preg_match('/class="[^"]*\bprev\b[^"]*"[^>]*>\s*<a[^>]+href="([^"]+)"/i', $html, $m)) {
        $prevUrl = $m[1];
    }
    $prevAttr = htmlspecialchars($prevUrl, ENT_QUOTES, 'UTF-8');

    if ($mode === 'button') {
        // 按钮加载更多：结构与文章列表侧保持一致（.wf-loadmore + is-loading / is-end）。
        // 那个 <a> 是「无 JS」时的兜底，标记 data-no-pjax 避免被 PJAX 拦成整页跳转。
        ?>
        <div class="wf-loadmore wf-loadmore--button"
             data-comment-pagination="button"
             data-next="<?php echo $nextAttr; ?>">
            <div class="weui-loadmore wf-loadmore__loading">
                <i class="weui-primary-loading"></i>
                <span class="weui-loadmore__tips"><?php _e('正在加载'); ?></span>
            </div>
            <a class="weui-btn weui-btn_default weui-btn_medium wf-loadmore__btn"
               data-no-pjax
               href="<?php echo $nextUrl !== '' ? $nextAttr : 'javascript:;'; ?>"><?php _e('加载更多评论'); ?></a>
            <div class="weui-loadmore weui-loadmore_line wf-loadmore__end">
                <span class="weui-loadmore__tips"><?php _e('已无更多评论'); ?></span>
            </div>
        </div>
        <?php
        return;
    }

    if ($mode === 'loadmore') {
        // 瀑布流：滚到底部自动加载。结构与文章列表侧保持一致（.wf-loadmore + is-loading/is-end），
        // 那个 <a> 只是无 JS 时的兜底。
        ?>
        <div class="wf-loadmore"
             data-comment-pagination="loadmore"
             data-next="<?php echo $nextAttr; ?>">
            <div class="weui-loadmore wf-loadmore__loading">
                <i class="weui-primary-loading"></i>
                <span class="weui-loadmore__tips"><?php _e('正在加载'); ?></span>
            </div>
            <div class="weui-loadmore weui-loadmore_line wf-loadmore__end">
                <span class="weui-loadmore__tips"><?php _e('已无更多评论'); ?></span>
            </div>
            <a class="wf-loadmore__fallback"
               data-no-pjax
               href="<?php echo $nextUrl !== '' ? $nextAttr : 'javascript:;'; ?>"><?php _e('加载更多评论'); ?></a>
        </div>
        <?php
        return;
    }

    // 分页模式：与文章列表用同一套 .weui-pagination 样式（上一页 / 当前·总数 / 下一页）。
    // 链接统一带 #comments 锚点：无刷新换页后停在评论区（而不是回到页面顶部），
    // 整页跳转时浏览器也会自动定位到评论区。
    // 注意：评论组件的 getCurrentPage()/getTotal() 在这里给的是**文章**维度而非评论分页维度，
    // 直接取会得到 1/1。改为从 pageNav 的 HTML 里解析：当前页在 <li class="current">，
    // 总页数是最后一个纯数字页链接。
    $curPage = 1;
    $totalPages = 1;
    if (preg_match('/<li[^>]*class="[^"]*\bcurrent\b[^"]*"[^>]*>\s*<a[^>]*>\s*(\d+)/i', $html, $m)) {
        $curPage = max(1, (int) $m[1]);
    }
    if (preg_match_all('/<a[^>]*>\s*(\d+)\s*<\/a>/i', $html, $ms)) {
        $nums = array_map('intval', $ms[1]);
        if (!empty($nums)) {
            $totalPages = max($nums);
        }
    }
    // 兜底：若只有数字 1（单页不该走到这里），仍保证 current ≤ total
    if ($totalPages < $curPage) $totalPages = $curPage;
    // 去掉原链接自带的 #comments 尾巴再统一补上，避免出现 ##comments
    $prevHref = $prevUrl !== '' ? rtrim(preg_replace('/#.*$/', '', $prevUrl), '#') . '#comments' : '';
    $nextHref = $nextUrl !== '' ? rtrim(preg_replace('/#.*$/', '', $nextUrl), '#') . '#comments' : '';
    ?>
    <div class="weui-pagination">
        <div class="weui-pagination__item weui-pagination__prev">
            <?php if ($prevHref !== ''): ?><a href="<?php echo htmlspecialchars($prevHref, ENT_QUOTES, 'UTF-8'); ?>"><?php _e('上一页'); ?></a><?php endif; ?>
        </div>
        <div class="weui-pagination__item weui-pagination__num"><?php echo $curPage; ?>/<?php echo $totalPages; ?></div>
        <div class="weui-pagination__item weui-pagination__next">
            <?php if ($nextHref !== ''): ?><a href="<?php echo htmlspecialchars($nextHref, ENT_QUOTES, 'UTF-8'); ?>"><?php _e('下一页'); ?></a><?php endif; ?>
        </div>
    </div>
    <?php
}


/* ============================================================
 * 阅读次数 / 点赞数
 * 全部存入 Typecho 原生的自定义字段表 typecho_fields，不新增任何数据表：
 *   - 阅读次数：字段名 views，值以十进制字符串保存
 *   - 文章点赞：字段名 likes，同上
 *   - 评论点赞：字段名 comment_likes，值为 JSON（{"<coid>":赞数}），存在所属文章上
 * 用 str 而不是 int 存数字，是为了与后台编辑页的字段表单行为保持一致：
 * 后台提交的自定义字段一律按 str 处理，若用 int 存，保存文章时会被改写成 str。
 * ============================================================ */

/** 请求内缓存的字段值（读多写少，写后主动失效） */
function &weuiFieldCache()
{
    static $cache = [];
    return $cache;
}

/** 读取某 cid 的字段原始字符串值（不存在返回 null） */
function weuiFieldRawGet($cid, $name)
{
    $cid = (int) $cid;
    if ($cid <= 0) {
        return null;
    }
    $key = $cid . ':' . $name;
    $cache = &weuiFieldCache();
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $val = null;
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow(
            $db->select('type', 'str_value', 'int_value')->from('table.fields')
                ->where('cid = ?', $cid)->where('name = ?', $name)->limit(1)
        );
        if ($row) {
            // 兼容后台手工把类型改成「整数」的情况
            $val = $row['type'] === 'int' ? (string) $row['int_value'] : (string) $row['str_value'];
        }
    } catch (\Throwable $e) {
        $val = null;
    }
    return $cache[$key] = $val;
}

/** 写入字段（存在则更新，不存在则插入），始终写成 str 类型 */
function weuiFieldRawSet($cid, $name, $value)
{
    $cid = (int) $cid;
    if ($cid <= 0 || !preg_match('/^[_a-z][_a-z0-9]*$/', (string) $name)) {
        return false;
    }
    try {
        $db = \Typecho\Db::get();
        $exists = $db->fetchRow(
            $db->select('cid')->from('table.fields')
                ->where('cid = ?', $cid)->where('name = ?', $name)->limit(1)
        );
        $rows = [
            'type'        => 'str',
            'str_value'   => (string) $value,
            'int_value'   => 0,
            'float_value' => 0,
        ];
        if ($exists) {
            $db->query(
                $db->update('table.fields')->rows($rows)
                    ->where('cid = ?', $cid)->where('name = ?', $name),
                \Typecho\Db::WRITE
            );
        } else {
            $rows['cid'] = $cid;
            $rows['name'] = $name;
            $db->query($db->insert('table.fields')->rows($rows), \Typecho\Db::WRITE);
        }
        $cache = &weuiFieldCache();
        $cache[$cid . ':' . $name] = (string) $value;
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

/** 读取整数字段 */
function weuiFieldIntGet($cid, $name)
{
    return (int) weuiFieldRawGet($cid, $name);
}

/** 写入整数字段（负数归零） */
function weuiFieldIntSet($cid, $name, $val)
{
    return weuiFieldRawSet($cid, $name, (string) max(0, (int) $val));
}

/** 读取阅读次数 */
function weuiViewsGet($cid)
{
    return weuiFieldIntGet($cid, WEUI_FIELD_VIEWS);
}

/** 阅读次数 +1（写失败静默） */
function weuiViewsAdd($cid)
{
    $cid = (int) $cid;
    if ($cid <= 0) {
        return;
    }
    // 同一请求内对同一篇只处理一次（避免一次访问里多处调用重复计数）
    static $done = [];
    if (isset($done[$cid])) {
        return;
    }
    $done[$cid] = true;

    // 「按访客去重」：Cookie 里记过的访客不再计数，只有这种模式才需要写 Cookie
    $unique = (weuiOpt('viewsUnique', '1') === '1');
    if ($unique && weuiViewSeen($cid)) {
        return;
    }

    weuiFieldIntSet($cid, WEUI_FIELD_VIEWS, weuiFieldIntGet($cid, WEUI_FIELD_VIEWS) + 1);
    if ($unique) {
        weuiViewMark($cid);
    }
}

/** 存放「已读文章」的 Cookie 名 */
function weuiViewedCookieName()
{
    return 'weui_viewed';
}

/**
 * 读取 Cookie 里记录的已读 cid 列表（按最近访问排序）。
 * 用单个 Cookie 存一串 cid，而不是每篇文章一个 Cookie——
 * 后者文章一多就会顶到浏览器「每域名 Cookie 数」上限，还可能互相挤掉。
 */
function weuiViewSeenList()
{
    if (empty($_COOKIE[weuiViewedCookieName()])) {
        return [];
    }
    $list = [];
    foreach (explode(',', (string) $_COOKIE[weuiViewedCookieName()]) as $v) {
        $v = (int) trim($v);
        if ($v > 0) {
            $list[] = $v;
        }
    }
    return $list;
}

/** 该访客是否已经读过这篇 */
function weuiViewSeen($cid)
{
    return in_array((int) $cid, weuiViewSeenList(), true);
}

/**
 * 记下「这篇读过了」。
 * 列表只保留最近 WEUI_VIEWED_MAX 条，避免 Cookie 无限增长（单 Cookie 约 4KB 上限）。
 */
function weuiViewMark($cid)
{
    if (headers_sent()) {
        return;
    }
    $cid = (int) $cid;
    $list = weuiViewSeenList();
    if (in_array($cid, $list, true)) {
        return;
    }
    array_unshift($list, $cid);
    if (count($list) > WEUI_VIEWED_MAX) {
        $list = array_slice($list, 0, WEUI_VIEWED_MAX);
    }
    setcookie(weuiViewedCookieName(), implode(',', $list), [
        'expires'  => time() + 31536000, // 一年
        'path'     => '/',
        'samesite' => 'Lax',
    ]);
}

/** 输出「xxx 次阅读」 */
function weuiViewsText($cid)
{
    echo (int) weuiViewsGet($cid);
    _e('次阅读');
}


/* ============================================================
 * 点赞
 * 计数存在 Typecho 原生自定义字段里（不新增数据表）：
 *   - 文章点赞：所属文章的 likes 字段（十进制字符串）
 *   - 评论点赞：所属文章的 comment_likes 字段（JSON：{"<coid>": 赞数}）
 * 前端向当前页面 POST（weui_vote），服务端返回 JSON。
 * ============================================================ */

/** 读取某文章的评论点赞映射表：['<coid>' => int] */
function weuiCommentLikesMap($cid)
{
    $raw = weuiFieldRawGet($cid, WEUI_FIELD_COMMENT_LIKES);
    if ($raw === null || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    $out = [];
    foreach ($decoded as $k => $v) {
        $out[(int) $k] = max(0, (int) $v);
    }
    return $out;
}

/** 写回评论点赞映射（内容为空时删除该字段，保持库干净） */
function weuiCommentLikesMapSet($cid, array $map)
{
    $map = array_filter($map, function ($v) {
        return (int) $v > 0;
    });
    if (!$map) {
        try {
            $db = \Typecho\Db::get();
            $db->query(
                $db->delete('table.fields')
                    ->where('cid = ?', (int) $cid)->where('name = ?', WEUI_FIELD_COMMENT_LIKES),
                \Typecho\Db::WRITE
            );
            $cache = &weuiFieldCache();
            unset($cache[(int) $cid . ':' . WEUI_FIELD_COMMENT_LIKES]);
        } catch (\Throwable $e) {
            // 忽略
        }
        return;
    }
    ksort($map);
    weuiFieldRawSet($cid, WEUI_FIELD_COMMENT_LIKES, json_encode($map, JSON_UNESCAPED_UNICODE));
}

/** 取某条评论所属的文章 cid（失败返回 0） */
function weuiCommentCid($coid)
{
    $coid = (int) $coid;
    if ($coid <= 0) {
        return 0;
    }
    static $cache = [];
    if (array_key_exists($coid, $cache)) {
        return $cache[$coid];
    }
    $cid = 0;
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow(
            $db->select('cid')->from('table.comments')->where('coid = ?', $coid)->limit(1)
        );
        if ($row) {
            $cid = (int) $row['cid'];
        }
    } catch (\Throwable $e) {
        // 忽略
    }
    return $cache[$coid] = $cid;
}

/**
 * 读取赞数。
 * @return array ['likes' => int]
 */
function weuiVoteGet($type, $id)
{
    $out = ['likes' => 0];
    $type = $type === 'comment' ? 'comment' : 'post';
    $id = (int) $id;
    if ($id <= 0) {
        return $out;
    }
    if ($type === 'comment') {
        $cid = weuiCommentCid($id);
        if ($cid > 0) {
            $map = weuiCommentLikesMap($cid);
            $out['likes'] = isset($map[$id]) ? (int) $map[$id] : 0;
        }
    } else {
        $out['likes'] = weuiFieldIntGet($id, WEUI_FIELD_LIKES);
    }
    return $out;
}

/**
 * 写入赞数。
 * @return bool
 */
function weuiVoteSet($type, $id, $likes)
{
    $type = $type === 'comment' ? 'comment' : 'post';
    $id = (int) $id;
    $likes = max(0, (int) $likes);
    if ($id <= 0) {
        return false;
    }
    if ($type === 'comment') {
        $cid = weuiCommentCid($id);
        if ($cid <= 0) {
            return false;
        }
        $map = weuiCommentLikesMap($cid);
        if ($likes > 0) {
            $map[$id] = $likes;
        } else {
            unset($map[$id]);
        }
        weuiCommentLikesMapSet($cid, $map);
        return true;
    }
    return weuiFieldIntSet($id, WEUI_FIELD_LIKES, $likes);
}

/** 访客投票记录的 cookie 名（避免刷新重复计数） */
function weuiVotedCookieName($type, $id)
{
    return 'weui_voted_' . ($type === 'comment' ? 'c' : 'p') . (int) $id;
}

/**
 * Gravatar 前缀：后台「头像加速地址」原样归一化（只去掉结尾多余的斜杠）。
 *
 * 不再强行追加 /gravatar——很多镜像（如 https://cn.cravatar.com）本身就是根域名，
 * 追加后会变成 .../gravatar/avatar/... 而 404。是否补 /avatar 由 weuiGravatarDir() 判断。
 * 供前端 JS 实时拼地址用，必须与 weuiGravatarUrl() 的规则一致。
 */
function weuiGravatarBase()
{
    $prefix = trim((string) weuiOpt('gravatarCdn', ''));
    if ($prefix === '') {
        return 'https://www.gravatar.com';
    }
    return rtrim($prefix, '/');
}

/**
 * 把前缀补成「后面直接接 hash 的目录」，兼容后台的多种写法：
 *   https://cn.cravatar.com        -> https://cn.cravatar.com/avatar
 *   https://cdn.v2ex.com/gravatar  -> https://cdn.v2ex.com/gravatar/avatar
 *   https://cn.cravatar.com/avatar -> 原样（已带 /avatar）
 */
function weuiGravatarDir()
{
    $base = weuiGravatarBase();
    return preg_match('~/avatar$~', $base) ? $base : $base . '/avatar';
}

/**
 * 生成 Gravatar 头像地址。
 * 后台可填镜像地址（如 https://cn.cravatar.com 或 https://cdn.v2ex.com/gravatar），留空用官方。
 */
function weuiGravatarUrl($mail, $size = 48, $default = 'mp')
{
    $size = (int) $size;
    $hash = md5(strtolower(trim((string) $mail)));
    return weuiGravatarDir() . '/' . $hash . '?s=' . $size . '&d=' . rawurlencode($default);
}

/**
 * 输出评论头像 <img>。
 *
 * 不使用 Typecho 自带的 $comments->gravatar()——它固定走 Common::gravatarUrl()，
 * 会绕过主题后台的「Gravatar 头像加速地址」设置。这里统一走 weuiGravatarUrl()，
 * 全站头像（评论列表 / 游客身份行 / 前端实时更新）因此都严格使用后台配置的 CDN，
 * 后台留空时回落到官方 gravatar.com。
 *
 * @param int $size 显示尺寸（px）；实际按 2 倍图请求，便于高分屏
 */
function weuiCommentAvatar(\Widget\Comments\Archive $comments, $size = 28)
{
    $size = max(1, (int) $size);
    $src = weuiGravatarUrl((string) $comments->mail, $size * 2);
    $alt = (string) $comments->author;
    echo weuiLazyImg(
        $src,
        '',
        $alt,
        'width="' . $size . '" height="' . $size . '" referrerpolicy="no-referrer"'
    );
}

/**
 * 让 Typecho 核心的 Gravatar 也走后台配置的加速地址。
 *
 * 核心（Common::gravatarUrl）在被调用时会优先使用常量 __TYPECHO_GRAVATAR_PREFIX__，
 * 且把它当作**完整前缀**（后面直接接 md5 值），所以必须以 /avatar/ 结尾。
 * 这样前台里任何仍走核心的 Gravatar（例如插件、侧栏最近评论等）也会跟着用 CDN。
 *
 * 注意：主题的 functions.php **只在前后台中的「前台」**被加载，Typecho 「后台」
 * 的头像由核心直接渲染、不经过这里。后台也想走 CDN，需在 config.inc.php 里加一行
 * 同样的常量（见 README「头像」一节）。
 */
if (!defined('__TYPECHO_GRAVATAR_PREFIX__')) {
    define('__TYPECHO_GRAVATAR_PREFIX__', weuiGravatarDir() . '/');
}

/**
 * 处理投票请求：POST weui_vote(post|comment) / weui_id / weui_dir(like|dislike)。
 * 由本文件末尾的顶层代码触发：命中就输出 JSON 并结束请求。
 */
function weuiVoteHandle()
{
    @header('Content-Type: application/json; charset=UTF-8');

    $out = function ($arr) {
        echo json_encode($arr, JSON_UNESCAPED_UNICODE);
        exit;
    };

    try {
        $type = (isset($_POST['weui_vote']) && $_POST['weui_vote'] === 'comment') ? 'comment' : 'post';
        $id = isset($_POST['weui_id']) ? (int) $_POST['weui_id'] : 0;

        if ($id <= 0) {
            $out(['ok' => false, 'msg' => '参数错误']);
        }

        $cookieName = weuiVotedCookieName($type, $id);
        $current = isset($_COOKIE[$cookieName]) ? (string) $_COOKIE[$cookieName] : '';

        // 只保留点赞：已赞则再点 = 取消
        $now = weuiVoteGet($type, $id);
        if ($current === 'like') {
            $likes = max(0, $now['likes'] - 1);
            $state = '';
        } else {
            $likes = $now['likes'] + 1;
            $state = 'like';
        }

        if (!weuiVoteSet($type, $id, $likes)) {
            $out(['ok' => false, 'msg' => '保存失败']);
        }

        // 记下当前状态（空 / like），供下次判断是否为「取消」
        if ($state === '') {
            setcookie($cookieName, '', time() - 3600, '/');
        } else {
            setcookie($cookieName, $state, time() + 31536000, '/');
        }

        $out(['ok' => true, 'likes' => $likes, 'state' => $state]);
    } catch (\Throwable $e) {
        echo json_encode(['ok' => false, 'msg' => '服务端错误'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

/**
 * 输出点赞按钮（只保留点赞，点踩已移除）。
 *
 * @param string $type   post|comment
 * @param int    $id     cid 或 coid
 * @param string $middle 追加在点赞后面的内容（文章页用来放打赏按钮）
 */
function weuiVoteHtml($type, $id, $middle = '')
{
    $type = $type === 'comment' ? 'comment' : 'post';
    $id = (int) $id;
    if ($id <= 0) {
        return;
    }

    $counts = weuiVoteGet($type, $id);
    $cookieName = weuiVotedCookieName($type, $id);
    $voted = isset($_COOKIE[$cookieName]) ? (string) $_COOKIE[$cookieName] : '';
    $liked = ($voted === 'like');
    ?>
    <span class="wf-vote<?php echo $middle !== '' ? ' wf-vote--with-middle' : ''; ?>" data-vote="<?php echo $type; ?>" data-id="<?php echo $id; ?>" data-state="<?php echo $liked ? 'like' : ''; ?>">
        <button type="button" class="wf-vote__btn wf-vote__btn--like<?php echo $liked ? ' is-voted' : ''; ?>"
                data-dir="like" aria-label="<?php _e('点赞'); ?>" aria-pressed="<?php echo $liked ? 'true' : 'false'; ?>">
            <svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true"><path fill="currentColor" d="M1 21h4V9H1v12zm22-11c0-1.1-.9-2-2-2h-6.31l.95-4.57.03-.32c0-.41-.17-.79-.44-1.06L14.17 1 7.59 7.59C7.22 7.95 7 8.45 7 9v10c0 1.1.9 2 2 2h9c.83 0 1.54-.5 1.84-1.22l3.02-7.05c.09-.23.14-.47.14-.73v-1z"/></svg>
            <span class="wf-vote__count" data-count><?php echo (int) $counts['likes']; ?></span>
        </button>
        <?php echo $middle; // 已由调用方转义/拼好 ?>
    </span>
    <?php
}

/**
 * 打赏：微信 / 支付宝二维码。
 * 两个都留空时整块不显示（连同打赏按钮）；只配一个就只显示一个（居中）；
 * 两个都配则在同一行并排显示。二维码放在一个弹窗里，点「打赏」按钮打开。
 */
function weuiRewardHtml()
{
    $wechat = trim((string) weuiOpt('rewardWechat', ''));
    $alipay = trim((string) weuiOpt('rewardAlipay', ''));
    if ($wechat === '' && $alipay === '') {
        return;
    }

    $text = trim((string) weuiOpt('rewardText', ''));
    $both = ($wechat !== '' && $alipay !== '');
    ?>
    <button type="button" class="wf-reward__btn" data-reward-open aria-label="<?php _e('打赏'); ?>">
        <svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true"><path fill="currentColor" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
        <span><?php _e('打赏'); ?></span>
    </button>

    <div class="weui-mask" id="wf-reward-mask" data-reward-close></div>
    <div class="weui-dialog" id="wf-reward-dialog" role="dialog" aria-modal="true" aria-label="<?php _e('打赏'); ?>">
        <div class="weui-dialog__hd"><strong class="weui-dialog__title"><?php _e('打赏'); ?></strong></div>
        <div class="weui-dialog__bd">
            <?php if ($text !== ''): ?>
                <p class="wf-reward__text"><?php echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <div class="wf-reward__codes<?php echo $both ? ' is-both' : ''; ?>">
                <?php if ($wechat !== ''): ?>
                    <figure class="wf-reward__item">
                        <img class="wf-reward__img" src="<?php echo weuiPlaceholderSrc(); ?>" data-wf-src="<?php echo htmlspecialchars($wechat, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php _e('微信打赏'); ?>" loading="lazy" decoding="async">
                        <figcaption><?php _e('微信'); ?></figcaption>
                    </figure>
                <?php endif; ?>
                <?php if ($alipay !== ''): ?>
                    <figure class="wf-reward__item">
                        <img class="wf-reward__img" src="<?php echo weuiPlaceholderSrc(); ?>" data-wf-src="<?php echo htmlspecialchars($alipay, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php _e('支付宝打赏'); ?>" loading="lazy" decoding="async">
                        <figcaption><?php _e('支付宝'); ?></figcaption>
                    </figure>
                <?php endif; ?>
            </div>
        </div>
        <div class="weui-dialog__ft">
            <a href="javascript:;" class="weui-dialog__btn weui-dialog__btn_primary" data-reward-close><?php _e('关闭'); ?></a>
        </div>
    </div>
    <?php
}

/**
 * 把 Typecho 的 excerpt 输出清洗成海报/纯文本可用的摘要：
 *  1) 丢掉 <pre>/<code> 代码块（正文开头若是代码，海报上会是一堆 &lt;?php，没意义）；
 *  2) 去掉所有 HTML 标签，块级标签换成空格；
 *  3) 解码 HTML 实体。
 *
 * 注意实体要解**两轮**：Typecho 的 excerpt() 已是 HTML 上下文（文章正文里的
 * `<?php` 在编辑时就被存成 `&lt;?php`），再经 htmlspecialchars() 写进 data-* 会变成
 * `&amp;lt;?php`，浏览器解析属性时只解一层，于是海报上显示成字面的 `&lt;?php`。
 * 这里在服务端先解回可读文本，海报自然正常。
 */
function weuiPlainExcerpt($html)
{
    $s = (string) $html;
    // 代码块整体丢弃（含内容），避免把源码当摘要
    $s = preg_replace('#<(pre|code)\b[^>]*>.*?</\1>#is', ' ', $s);
    // 注释（含 Typecho 的 <!--more-->）一并去掉
    $s = preg_replace('/<!--.*?-->/s', ' ', $s);
    // 块级元素边界换成空格，防止相邻文字粘连
    $s = preg_replace('#</?(p|div|br|li|ul|ol|h[1-6]|blockquote|section|article|tr|td)\b[^>]*>#i', ' ', $s);
    // 其余标签一律剥掉
    $s = strip_tags($s);
    // 解两轮实体，兼容 &amp;lt; 这种二次转义
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = trim(preg_replace('/\s+/u', ' ', $s));
    $s = trim($s, " \t\n\r\0\x0B·-—");
    // 代码块被丢弃后可能只剩空白 —— 调用方会据此决定不画摘要卡片
    if ($s === '' || preg_match('/^[.…\s]+$/u', $s)) {
        return '';
    }
    return $s;
}

/**
 * 分享：分享按钮 + 分享面板 + 海报弹窗。
 *
 * 面板参考 kx.gy 的分享（mofang 主题），提供 6 项：
 *   微博 / QQ 空间 / Telegram / 微信扫码 / 复制链接 / 生成海报。
 * 点分享**一律弹自带面板**，不调用 navigator.share：桌面 Chrome/Edge 也暴露该 API，
 * 但 Windows 的系统分享面板常常没有可用目标，一点就报「无法为你显示所有共享方法」，
 * 各平台表现也不一致，因此统一走自己的面板，行为可预期。
 *
 * 文章地址、标题与海报素材（封面 / 摘要 / 站点名 / 日期）都写在按钮的 data-* 上（由服务端渲染），
 * JS 一律用事件委托读取，因此 PJAX 换页后重新生成的按钮不需要重新绑定。
 *
 * @param \Widget\Archive $archive 当前文章
 */
function weuiShareHtml($archive)
{
    if (weuiOpt('sharePost', '1') !== '1') {
        return;
    }

    $url = (string) $archive->permalink;
    $title = (string) $archive->title;
    if ($url === '') {
        return;
    }

    // 海报素材：封面（自定义字段 thumb / 正文首图）与摘要。
    // 摘要直接从**正文 HTML** 清洗而来，而不是用 $archive->excerpt()：后者会先
    // strip_tags，把 <pre><code> 的包裹一起抹掉，导致代码正文（如 <?php …）被当成
    // 普通文字留在摘要里。用 content 才能识别并丢弃代码块。
    $cover = weuiGetThumb($archive);
    $excerpt = weuiPlainExcerpt((string) $archive->content);
    if (function_exists('mb_substr')) {
        $excerpt = mb_substr($excerpt, 0, 100, 'UTF-8');
    } else {
        $excerpt = substr($excerpt, 0, 300);
    }
    $excerpt = trim($excerpt);

    // 站点名与日期，海报页脚用
    $siteTitle = '';
    try {
        $siteTitle = trim((string) \Widget\Options::alloc()->title);
    } catch (\Throwable $e) {
        $siteTitle = '';
    }
    $date = date('Y-m-d', (int) $archive->created);
    ?>
    <button type="button" class="wf-share__btn" data-share-open
            data-share-url="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"
            data-share-title="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>"
            data-share-cover="<?php echo htmlspecialchars($cover, ENT_QUOTES, 'UTF-8'); ?>"
            data-share-excerpt="<?php echo htmlspecialchars($excerpt, ENT_QUOTES, 'UTF-8'); ?>"
            data-share-site="<?php echo htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8'); ?>"
            data-share-date="<?php echo htmlspecialchars($date, ENT_QUOTES, 'UTF-8'); ?>"
            aria-label="<?php _e('分享'); ?>">
        <svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true"><path fill="currentColor" d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92z"/></svg>
        <span><?php _e('分享'); ?></span>
    </button>

    <div class="weui-mask" id="wf-share-mask" data-share-close></div>
    <div class="weui-dialog" id="wf-share-dialog" role="dialog" aria-modal="true" aria-label="<?php _e('分享'); ?>">
        <div class="weui-dialog__hd"><strong class="weui-dialog__title"><?php _e('分享这篇文章'); ?></strong></div>
        <div class="weui-dialog__bd">
            <div class="wf-share__grid">
                <a class="wf-share__item" data-share="weibo" href="javascript:;" target="_blank" rel="noopener">
                    <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm6.93 6h-2.95a15.65 15.65 0 0 0-1.38-3.56A8.03 8.03 0 0 1 18.93 8zM12 4.04c.83 1.2 1.48 2.53 1.91 3.96h-3.82c.43-1.43 1.08-2.76 1.91-3.96zM4.26 14A7.82 7.82 0 0 1 4 12c0-.69.1-1.36.26-2h3.38a16.6 16.6 0 0 0 0 4H4.26zm.82 2h2.95c.32 1.25.78 2.45 1.38 3.56A7.99 7.99 0 0 1 5.08 16zm2.95-8H5.08a7.99 7.99 0 0 1 4.33-3.56A15.65 15.65 0 0 0 8.03 8zM12 19.96c-.83-1.2-1.48-2.53-1.91-3.96h3.82c-.43 1.43-1.08 2.76-1.91 3.96zM14.34 14H9.66a14.6 14.6 0 0 1 0-4h4.68a14.6 14.6 0 0 1 0 4zm.25 5.56c.6-1.11 1.06-2.31 1.38-3.56h2.95a7.99 7.99 0 0 1-4.33 3.56zM16.36 14a16.6 16.6 0 0 0 0-4h3.38c.16.64.26 1.31.26 2s-.1 1.36-.26 2h-3.38z"/></svg>
                    <span><?php _e('微博'); ?></span>
                </a>
                <a class="wf-share__item" data-share="qzone" href="javascript:;" target="_blank" rel="noopener">
                    <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                    <span><?php _e('QQ 空间'); ?></span>
                </a>
                <a class="wf-share__item" data-share="telegram" href="javascript:;" target="_blank" rel="noopener">
                    <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M2.01 21 23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                    <span><?php _e('Telegram'); ?></span>
                </a>
                <button type="button" class="wf-share__item" data-share="wechat">
                    <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M3 11h8V3H3v8zm2-6h4v4H5V5zM3 21h8v-8H3v8zm2-6h4v4H5v-4zM13 3v8h8V3h-8zm6 6h-4V5h4v4zM13 13h2v2h-2zM15 15h2v2h-2zM13 17h2v2h-2zM17 17h2v2h-2zM19 15h2v2h-2zM19 19h2v2h-2z"/></svg>
                    <span><?php _e('微信扫码'); ?></span>
                </button>
                <button type="button" class="wf-share__item" data-share="copy">
                    <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>
                    <span><?php _e('复制链接'); ?></span>
                </button>
                <button type="button" class="wf-share__item" data-share="poster">
                    <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="currentColor" d="M21 3H3c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H3V5h18v14zM5 15h5v3H5v-3zm0-4h5v3H5v-3zm6 0h8v7h-8v-7zm6-4h2v3h-2V7zm-4 0h2v3h-2V7z"/></svg>
                    <span><?php _e('生成海报'); ?></span>
                </button>
            </div>
            <?php /* 微信扫码：点「微信扫码」后在这里生成二维码 */ ?>
            <div class="wf-share__qr" id="wf-share-qr" hidden></div>
        </div>
        <div class="weui-dialog__ft">
            <a href="javascript:;" class="weui-dialog__btn weui-dialog__btn_primary" data-share-close><?php _e('关闭'); ?></a>
        </div>
    </div>

    <?php /* 海报弹窗：纯前端 canvas 绘制（标题 / 封面 / 摘要 / 二维码），无服务端参与。
       canvas 由 theme.js 在打开时生成，这里的 stage 只是容器。 */ ?>
    <div class="weui-mask" id="wf-poster-mask" data-poster-close></div>
    <div class="weui-dialog" id="wf-poster-dialog" role="dialog" aria-modal="true" aria-label="<?php _e('生成海报'); ?>">
        <div class="weui-dialog__hd"><strong class="weui-dialog__title"><?php _e('生成海报'); ?></strong></div>
        <div class="weui-dialog__bd">
            <div class="wf-poster__stage" id="wf-poster-stage">
                <div class="wf-poster__loading"><?php _e('正在生成…'); ?></div>
            </div>
            <p class="wf-poster__tip"><?php _e('长按图片可保存到相册，或点下方按钮下载'); ?></p>
        </div>
        <div class="weui-dialog__ft">
            <a href="javascript:;" class="weui-dialog__btn" data-poster-close><?php _e('关闭'); ?></a>
            <a href="javascript:;" class="weui-dialog__btn weui-dialog__btn_primary" data-poster-save hidden><?php _e('保存图片'); ?></a>
        </div>
    </div>
    <?php
}

/* ============================================================
 * 音乐播放器
 * - 前台：footer.php 的 #wf-fab 内输出一个音乐按钮 + 播放器面板（自研，无第三方库）；
 *   theme.js 的 initMusic() 驱动（DOM 在 .page 之外，PJAX 换页不重建 → 不断歌）。
 * - 后端：复用 libs/Meting.php（Meting 1.5.7）做同源代理，取歌单/播放地址/歌词。
 *   挂载方式与投票一致：文件末尾按「?weui_music=」识别，无需注册路由或插件。
 * ============================================================ */

/** 加载 Meting 库（只加载一次）。 */
function weuiMetingLoad()
{
    if (class_exists('\\Metowolf\\Meting')) {
        return true;
    }
    $file = __DIR__ . '/libs/Meting.php';
    if (!is_file($file)) {
        return false;
    }
    require_once $file;
    return class_exists('\\Metowolf\\Meting');
}

/** 合法的音乐平台白名单。 */
function weuiMusicServers()
{
    return ['netease', 'tencent', 'kugou', 'baidu'];
}

/** 归一化平台名（非法值回落 netease）。 */
function weuiMusicServer($v)
{
    $v = strtolower(trim((string) $v));
    return in_array($v, weuiMusicServers(), true) ? $v : 'netease';
}

/**
 * 解析后台「歌单 ID / 链接」文本，返回 [[server,type,id], …]。
 * 规则（仿 handsome）：每行一个；json 里的 {server,type,id} 优先；
 * 否则从分享链接里按平台特征提取 id；纯数字/纯字母数字则当 ID，类型默认 playlist。
 */
function weuiMusicItems()
{
    $raw = (string) weuiOpt('musicList', '');
    if (trim($raw) === '') {
        return [];
    }
    $defaultServer = weuiMusicServer(weuiOpt('musicServer', 'netease'));
    $items = [];
    foreach (preg_split('/[\r\n]+/', $raw) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        // 1) JSON 形式 {"server":"netease","type":"playlist","id":"123"}
        if ($line[0] === '{') {
            $j = json_decode($line, true);
            if (is_array($j) && isset($j['id'])) {
                $items[] = [
                    'server' => weuiMusicServer(isset($j['server']) ? $j['server'] : $defaultServer),
                    'type'   => in_array(isset($j['type']) ? $j['type'] : 'playlist', ['playlist', 'song', 'album', 'artist'], true)
                        ? $j['type'] : 'playlist',
                    'id'     => (string) $j['id'],
                ];
            }
            continue;
        }
        // 2) 分享链接：按平台特征提取 id
        $server = $defaultServer;
        $type = 'playlist';
        $id = '';
        if (preg_match('~music\.163\.com.*[/#](?:playlist|song|album|artist)[^/]*[/=](\d+)~i', $line, $m)
            || preg_match('~music\.163\.com.*[?&]id=(\d+)~i', $line, $m)) {
            $server = 'netease';
            if (stripos($line, '/song') !== false) $type = 'song';
            elseif (stripos($line, '/album') !== false) $type = 'album';
            elseif (stripos($line, '/artist') !== false) $type = 'artist';
            $id = $m[1];
        } elseif (preg_match('~y\.qq\.com.*(?:playlist|songDetail|album|singer)[^/]*[/=](\w+)~i', $line, $m)) {
            $server = 'tencent';
            if (stripos($line, 'songDetail') !== false) $type = 'song';
            elseif (stripos($line, 'album') !== false) $type = 'album';
            elseif (stripos($line, 'singer') !== false) $type = 'artist';
            $id = $m[1];
        } elseif (preg_match('~kugou\.com.*[/=](\w+)~i', $line, $m)) {
            $server = 'kugou';
            $id = $m[1];
        } elseif (preg_match('~(?:ting|music)\.baidu\.com.*[/=](\d+)~i', $line, $m)) {
            $server = 'baidu';
            $id = $m[1];
        } elseif (preg_match('/^[A-Za-z0-9_]+$/', $line)) {
            // 3) 纯 ID
            $id = $line;
        }
        if ($id !== '') {
            $items[] = ['server' => $server, 'type' => $type, 'id' => $id];
        }
    }
    return $items;
}

/**
 * 音乐代理的 auth 令牌。仿 handsome：md5(salt . server.type.id . salt)。
 * salt 来自主题设置 musicSalt（留空则用固定默认串），避免被随意盗刷接口。
 */
function weuiMusicSalt()
{
    $s = trim((string) weuiOpt('musicSalt', ''));
    return $s !== '' ? $s : 'weui-music';
}

function weuiMusicAuth($server, $type, $id)
{
    $salt = weuiMusicSalt();
    return md5($salt . $server . $type . $id . $salt);
}

/** 本站音乐代理的基址（不带查询串）。 */
function weuiMusicActionUrl()
{
    return rtrim(\Widget\Options::alloc()->siteUrl, '/') . '/?weui_music=';
}

/** 构造一个「指向本站代理」的地址：?weui_music=<type>&server=&id=&auth= */
function weuiMusicProxyUrl($type, $server, $id)
{
    return weuiMusicActionUrl() . urlencode($type)
        . '&server=' . urlencode($server)
        . '&id=' . urlencode((string) $id)
        . '&auth=' . weuiMusicAuth($server, $type, (string) $id);
}

/**
 * 音乐代理的简易文件缓存（主题目录下 cache/ 子目录，默认 2 小时）。
 * 缓存不可写时静默降级为直连（不影响功能）。
 */
function weuiMusicCacheDir()
{
    $dir = __DIR__ . '/cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function weuiMusicCacheGet($key, $ttl = 7200)
{
    $f = weuiMusicCacheDir() . '/' . md5($key) . '.dat';
    if (!is_file($f)) {
        return null;
    }
    if (time() - (int) @filemtime($f) > $ttl) {
        @unlink($f);
        return null;
    }
    $s = @file_get_contents($f);
    return ($s === false || $s === '') ? null : $s;
}

function weuiMusicCacheSet($key, $value)
{
    $f = weuiMusicCacheDir() . '/' . md5($key) . '.dat';
    @file_put_contents($f, $value, LOCK_EX);
}

/**
 * 载入并配置好一个 Meting 实例（含 Cookie，仿 handsome）。
 * 关键：网易云 / QQ 必须带 Cookie 才拿得到播放地址，否则 url() 返回空。
 * Cookie 优先用后台配置的 musicCookie / musicQqCookie，留空则用内置兜底串。
 */
function weuiMusicInstance($server)
{
    $m = new \Metowolf\Meting($server);
    $m->format(true);

    $cookie = '';
    if ($server === 'netease') {
        $cookie = trim((string) weuiOpt('musicCookie', ''));
        if ($cookie === '') {
            $cookie = weuiMusicDefaultCookie('netease');
        }
    } elseif ($server === 'tencent') {
        $cookie = trim((string) weuiOpt('musicQqCookie', ''));
        if ($cookie === '') {
            $cookie = weuiMusicDefaultCookie('tencent');
        }
    }
    if ($cookie !== '') {
        $m->cookie($cookie);
    }
    return $m;
}

/** 内置兜底 Cookie（与 handsome 一致；失效时请在后台填入自己的 Cookie）。 */
function weuiMusicDefaultCookie($server)
{
    static $netease = 'MUSIC_U=9d1749e7dc18cec133052c452e124c29b9e9c1146ae0da54ee54d4e51591487f993166e004087dd3d78b6050a17a35e705925a4e6992f61dfe3f0151024f9e31; __remember_me=true; _iuqxldmzr_=32; NMTID=00OIdHlNGdvfHBKMkXpoN4iXdT-tvYAAAF_fp9eTg; WEVNSM=1.0.0;';
    static $tencent = 'pgv_pvid=7011075408; login_type=1; qqmusic_key=Q_H_L_53n8FLhZv-LcsnVvHyAlZC7ImWHIcrDMZW73pexh5vpfIaKVsdkKRXg; uin=1875812278;';
    return $server === 'tencent' ? $tencent : $netease;
}

/**
 * 把 Meting 的「歌曲列表」规整成前端要的结构（仿 handsome）：
 * 每首歌返回 name / artist / url / cover / lrc —— 但 url、cover、lrc 都是
 * **指向本站代理的地址**（各自带 auth），由前端/浏览器按需再请求，
 * 这样歌单接口本身很快，播放地址与歌词到需要时才取。
 */
function weuiMusicListFromMeting($server, $json)
{
    $arr = json_decode((string) $json, true);
    if (!is_array($arr)) {
        return json_encode([], JSON_UNESCAPED_UNICODE);
    }
    $out = [];
    foreach ($arr as $song) {
        if (!is_array($song) || empty($song['name'])) {
            continue;
        }
        $src = !empty($song['source']) ? $song['source'] : $server;
        $artist = isset($song['artist']) ? $song['artist'] : '';
        if (is_array($artist)) {
            $artist = implode(' / ', $artist);
        }
        $album = isset($song['album']) ? $song['album'] : '';
        if (is_array($album)) {
            $album = implode(' / ', $album);
        }
        $urlId = !empty($song['url_id']) ? $song['url_id'] : (isset($song['id']) ? $song['id'] : '');
        $picId = !empty($song['pic_id']) ? $song['pic_id'] : $urlId;
        $lrcId = !empty($song['lyric_id']) ? $song['lyric_id'] : $urlId;
        $out[] = [
            'name'   => $song['name'],
            'artist' => $artist,
            'album'  => $album,
            'url'    => weuiMusicProxyUrl('url', $src, $urlId),
            'cover'  => weuiMusicProxyUrl('pic', $src, $picId),
            'lrc'    => weuiMusicProxyUrl('lrc', $src, $lrcId),
        ];
    }
    return json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}


/**
 * Meting 代理端点：GET ?weui_music=<type>&server=&id=&auth=
 * type: playlist | song | album | artist | url | lyric | pic
 * 返回 JSON（原文即 JSON 字符串）。命中后直接结束请求。
 */
function weuiMusicHandle()
{
    @header('Content-Type: application/json; charset=UTF-8');
    // 歌单/歌词等文本代理：允许短缓存但必须回源重验证（避免改了返回结构后浏览器仍用旧缓存）
    @header('Cache-Control: no-cache, max-age=0, must-revalidate');

    $out = function ($s) {
        echo $s;
        exit;
    };

    $type = isset($_GET['weui_music']) ? (string) $_GET['weui_music'] : '';
    $server = weuiMusicServer(isset($_GET['server']) ? $_GET['server'] : 'netease');
    $id = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
    $auth = isset($_GET['auth']) ? (string) $_GET['auth'] : '';

    $allowed = ['playlist', 'song', 'album', 'artist', 'url', 'lyric', 'lrc', 'pic'];
    if ($type === '' || !in_array($type, $allowed, true) || $id === '') {
        $out(json_encode(['error' => 'bad request'], JSON_UNESCAPED_UNICODE));
    }
    // 注意：auth 用「原始 type」校验（前端按 lrc 签的），所以先校验再映射 lrc→lyric
    if ($auth !== weuiMusicAuth($server, $type, $id)) {
        $out(json_encode(['error' => 'bad auth'], JSON_UNESCAPED_UNICODE));
    }
    // lrc 是前端惯用写法，映射到 Meting 的 lyric
    if ($type === 'lrc') {
        $type = 'lyric';
    }

    if (!weuiMetingLoad()) {
        $out(json_encode(['error' => 'meting missing'], JSON_UNESCAPED_UNICODE));
    }

    // 屏蔽 Meting 库在较新 PHP（8.2+）上的弃用告警，避免污染 JSON/文本输出。
    // 库本身逻辑不改（用户确认可用），只是把告警关掉。
    $__oldER = error_reporting();
    @ini_set('display_errors', '0');
    error_reporting($__oldER & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    // 缓存键带格式版本号：调整返回结构（如新增 album 字段）时改版本号即可令旧缓存失效。
    $cacheKey = 'wf_music_v2_' . $server . '_' . $type . '_' . $id;

    try {
        // ----- 歌词：返回纯 LRC 文本（仿 handsome） -----
        if ($type === 'lyric') {
            $data = weuiMusicCacheGet($cacheKey, 86400);
            if ($data === null) {
                $m = weuiMusicInstance($server);
                $data = $m->lyric($id);
                weuiMusicCacheSet($cacheKey, $data);
            }
            @header('Content-Type: text/plain; charset=UTF-8');
            $d = json_decode($data, true);
            echo (is_array($d) && isset($d['lyric'])) ? $d['lyric'] : '';
            exit;
        }

        // ----- 封面：302 跳到真实图片地址 -----
        if ($type === 'pic') {
            $data = weuiMusicCacheGet($cacheKey, 86400);
            if ($data === null) {
                $m = weuiMusicInstance($server);
                $data = $m->pic($id, 300);
                weuiMusicCacheSet($cacheKey, $data);
            }
            $d = json_decode($data, true);
            $url = (is_array($d) && !empty($d['url'])) ? $d['url'] : '';
            @header('Location: ' . $url);
            exit;
        }

        // ----- 播放地址：302 跳到真实音频地址（带各平台 URL 修正，仿 handsome） -----
        if ($type === 'url') {
            $data = weuiMusicCacheGet($cacheKey, 1200);
            if ($data === null) {
                $m = weuiMusicInstance($server);
                $data = $m->url($id, 320);
                weuiMusicCacheSet($cacheKey, $data);
            }
            $d = json_decode($data, true);
            $url = (is_array($d) && !empty($d['url'])) ? $d['url'] : '';
            if ($url !== '') {
                if ($server === 'netease') {
                    $url = str_replace('://m7c.', '://m7.', $url);
                    $url = str_replace('://m8c.', '://m8.', $url);
                } elseif ($server === 'tencent') {
                    $url = str_ireplace('ws.stream.qqmusic.qq.com', 'dl.stream.qqmusic.qq.com', $url);
                }
                $url = str_replace('http://', $protocol . '://', $url);
            }
            @header('Location: ' . $url);
            exit;
        }

        // ----- 歌单 / 单曲 / 专辑 / 歌手：返回「指向本站代理」的歌曲列表 -----
        $data = weuiMusicCacheGet($cacheKey, 7200);
        if ($data === null) {
            $m = weuiMusicInstance($server);
            if ($type === 'playlist') {
                $raw = $m->playlist($id);
            } elseif ($type === 'album') {
                $raw = $m->album($id);
            } elseif ($type === 'artist') {
                $raw = $m->artist($id);
            } else { // song
                $raw = $m->song($id);
            }
            $data = weuiMusicListFromMeting($server, $raw);
            weuiMusicCacheSet($cacheKey, $data);
        }
        @header('Content-Type: application/json; charset=UTF-8');
        echo $data;
    } catch (\Throwable $e) {
        @header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'server error'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

/**
 * 输出音乐播放器的 FAB 按钮与面板（自研播放器，不依赖 APlayer）。
 * 放在 #wf-fab 内、返回顶部按钮之后；DOM 在 .page 之外，PJAX 换页不会重建 → 播放不中断。
 * 面板里的结构由 theme.js 的 initMusic() 驱动：拉歌单、渲染列表、播放/切歌、贴底歌词。
 */
function weuiMusicHtml()
{
    if (weuiOpt('musicEnable', '0') !== '1') {
        return;
    }
    $items = weuiMusicItems();
    if (!$items) {
        return;
    }
    ?>
    <div class="wf-music" id="wf-music" hidden>
        <div class="wf-music__hd">
            <span class="wf-music__title"><?php _e('音乐'); ?></span>
            <button type="button" class="wf-music__close" data-fab-music-close aria-label="<?php _e('收起播放器'); ?>">&#10005;</button>
        </div>

        <?php /* 当前曲：封面 + 曲名/歌手 */ ?>
        <div class="wf-music__now">
            <div class="wf-music__cover" id="wf-music-cover"></div>
            <div class="wf-music__meta">
                <div class="wf-music__name" id="wf-music-name"><?php _e('未在播放'); ?></div>
                <div class="wf-music__artist" id="wf-music-artist"></div>
            </div>
        </div>

        <?php /* 进度条（可拖动） */ ?>
        <div class="wf-music__progress">
            <span class="wf-music__time" id="wf-music-cur">00:00</span>
            <input type="range" class="wf-music__range" id="wf-music-seek" min="0" max="1000" value="0" step="1" aria-label="<?php _e('播放进度'); ?>">
            <span class="wf-music__time" id="wf-music-dur">00:00</span>
        </div>

        <?php /* 控制按钮：播放顺序 / 上一首 / 播放暂停 / 下一首 / 歌词开关 */ ?>
        <div class="wf-music__ctrl">
            <button type="button" class="wf-music__btn wf-music__btn--mode" data-music-mode aria-label="<?php _e('列表循环'); ?>" title="<?php _e('列表循环'); ?>">
                <svg class="wf-music__mode-icon" data-mode-list viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M7 7h10v3l4-4-4-4v3H5v6h2zm10 10H7v-3l-4 4 4 4v-3h12v-6h-2z"/></svg>
                <svg class="wf-music__mode-icon" data-mode-random viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" hidden><path fill="currentColor" d="M10.59 9.17L5.41 4 4 5.41l5.17 5.17 1.42-1.41zM14.5 4l2.04 2.04L4 18.59 5.41 20 17.96 7.46 20 9.5V4h-5.5zm.33 9.41l-1.41 1.41 3.13 3.13L14.5 20H20v-5.5l-2.04 2.04-3.13-3.13z"/></svg>
                <svg class="wf-music__mode-icon" data-mode-single viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" hidden><path fill="currentColor" d="M7 7h10v3l4-4-4-4v3H5v6h2zm10 10H7v-3l-4 4 4 4v-3h12v-6h-2zM11 8h1.5v8H11V9.7L9.5 10.4V9L11 8z"/></svg>
            </button>
            <button type="button" class="wf-music__btn" data-music-prev aria-label="<?php _e('上一首'); ?>">
                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M6 6h2v12H6zm3.5 6l8.5 6V6z"/></svg>
            </button>
            <button type="button" class="wf-music__btn wf-music__btn--play" data-music-toggle aria-label="<?php _e('播放'); ?>">
                <svg class="wf-music__icon-play" viewBox="0 0 24 24" width="26" height="26" aria-hidden="true"><path fill="currentColor" d="M8 5v14l11-7z"/></svg>
                <svg class="wf-music__icon-pause" viewBox="0 0 24 24" width="26" height="26" aria-hidden="true" hidden><path fill="currentColor" d="M6 5h4v14H6zm8 0h4v14h-4z"/></svg>
            </button>
            <button type="button" class="wf-music__btn" data-music-next aria-label="<?php _e('下一首'); ?>">
                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M16 6h2v12h-2zM6 18l8.5-6L6 6z"/></svg>
            </button>
            <button type="button" class="wf-music__btn wf-music__btn--lyric" data-music-lyric-toggle aria-label="<?php _e('显示歌词'); ?>" aria-pressed="true">
                <span class="wf-music__lyric-word" aria-hidden="true">词</span>
            </button>
        </div>

        <?php /* 歌曲列表（自身滚动，不会溢出面板）+ 定位当前播放条的悬浮圆按钮 */ ?>
        <div class="wf-music__listwrap">
            <ol class="wf-music__list" id="wf-music-list" data-loading="1">
                <li class="wf-music__tip"><?php _e('正在加载…'); ?></li>
            </ol>
            <button type="button" class="wf-music__locate" data-music-locate aria-label="<?php _e('定位当前播放'); ?>" title="<?php _e('定位当前播放'); ?>">
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" d="M12 4a8 8 0 1 0 0 16 8 8 0 0 0 0-16z"/><circle cx="12" cy="12" r="3" fill="currentColor"/></svg>
            </button>
        </div>

        <?php /* 真正的播放器元素（隐藏，仅作音频输出） */ ?>
        <audio id="wf-music-audio" preload="metadata"></audio>
    </div>
    <button type="button" class="wf-fab__btn wf-fab__btn--music" data-fab-music aria-label="<?php _e('音乐播放器'); ?>" aria-expanded="false">
        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M12 3v10.55A4 4 0 1 0 14 17V7h4V3h-6z"/></svg>
    </button>
    <?php
}

/**
 * 贴底歌词容器（放在 body 末尾、.page 之外，PJAX 不重建）。
 * 由 theme.js 填歌词并随播放滚动高亮。
 */
function weuiMusicLrcHtml()
{
    if (weuiOpt('musicEnable', '0') !== '1') {
        return;
    }
    // 注意：歌词容器必须**始终渲染**（只要音乐功能开启且有歌单）。
    // 「默认显示歌词」(musicLyric) 只作为前台开关的初值/默认状态，不能用来决定是否输出容器；
    // 否则后台关掉默认显示后，页面里没有 #wf-lrc，前台开关打开也无处可显示（开关失效）。
    if (!weuiMusicItems()) {
        return;
    }
    ?>
    <div class="wf-lrc" id="wf-lrc" aria-hidden="true"></div>
    <?php
}

/*
 * 投票请求入口。
 * 这里用「POST 里带 weui_vote」来识别，因此不需要新增路由或插件：
 * 请求就是打到当前页面地址，functions.php 在 Typecho 处理流程早期就被加载，直接接管并返回 JSON。
 */
if (
    !defined('__TYPECHO_ADMIN__')
    && isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['weui_vote'])
) {
    weuiVoteHandle();
}

/*
 * 音乐代理请求入口。
 * 用「GET 里带 weui_music」识别，同样不需要路由/插件；命中后直接返回 JSON 并结束。
 * 必须放在主题设置等前置逻辑之外，且不要求用户已登录。
 */
if (
    !defined('__TYPECHO_ADMIN__')
    && isset($_GET['weui_music'])
) {
    weuiMusicHandle();
}

/* ============================================================================
 * 评论「跳转页码」修正（不改核心）
 *
 * 背景
 *   提交评论后 Typecho 会 302 到 /文章/comment-page-N#comment-X，这个 N 由核心
 *   var/Widget/Base/Comments.php 的 ___commentPage() 估算，分三步：
 *     ① 顺着 parent 往上爬到顶层祖先；
 *     ② 只取「coid >= 那个祖先」的这一段评论；
 *     ③ 数这一段里「看起来是顶层」的条数 —— parent == 0，**或者 parent 不在这一段里**。
 *   第 ③ 步的第二个条件是误伤：「回复了更早评论」的回复，它的 parent 天然不在
 *   这一段里，于是被当成顶层多算了一条，$total 偏大 → 算出的页码偏后。
 *
 *   而真正的分页（Widget\Comments\Archive::execute）是按**全部顶层评论**切的：
 *     array_slice($this->stack, ($page - 1) * commentsPageSize, commentsPageSize)
 *   其中 threaded 模式下 $this->stack 只装顶层评论，回复跟着它的祖先出现在同一页。
 *
 *   两边口径不一致，结果就是：跳过去的那一页里根本没有你刚发的那条评论。
 *   越老、回复越多的文章越明显 —— 「回复更早评论」的回复越多，被多算的就越多。
 *
 * 做法
 *   不碰核心，在主题里按「分页的真实口径」重新算一遍，覆盖掉那个估算值。
 *   挂载点选 Widget\Feedback::finishComment —— 它在「评论已入库、302 之前」触发，
 *   此时把 $comment->commentPage 写上正确值，随后的 ___permalink() 就会用它拼地址。
 *   同一个算式也用在评论列表的「回复」链接上（那条链接同样带页码）。
 * ========================================================================== */

/**
 * 取某篇文章的评论文本结构：parent 映射 + 顶层评论序列（已按 commentsOrder 排好）。
 * 同一请求内只查一次库。
 *
 * @return array{parents: array<int,int>, tops: array<int,int>}
 */
function weuiCommentTree(int $cid, $options): array
{
    static $cache = [];

    if (isset($cache[$cid])) {
        return $cache[$cid];
    }

    $db = \Typecho\Db::get();

    // 与 Widget\Comments\Archive::execute() 保持同一个「可见评论」口径
    $unapprovedId = (int) \Typecho\Cookie::get('__typecho_unapproved_comment', 0);
    $select = $db->select('coid', 'parent')->from('table.comments')
        ->where('cid = ?', $cid)
        ->where('status = ? OR (coid = ? AND status <> ?)', 'approved', $unapprovedId, 'approved')
        ->order('table.comments.coid', 'ASC');
    if ($options->commentsShowCommentOnly) {
        $select->where('table.comments.type = ?', 'comment');
    }
    $rows = $db->fetchAll($select);

    // 父评论也在集合里的 → 是回复；否则（parent = 0，或父评论不可见）→ 算顶层
    $parents = [];
    foreach ($rows as $row) {
        $parents[(int) $row['coid']] = (int) $row['parent'];
    }

    $tops = [];
    foreach ($rows as $row) {
        $pid = (int) $row['parent'];
        if ($options->commentsThreaded && 0 != $pid && isset($parents[$pid])) {
            continue;
        }
        $tops[] = (int) $row['coid'];
    }

    // Archive 里是先按 coid 升序取、DESC 时再整体反转
    if ('DESC' == $options->commentsOrder) {
        $tops = array_reverse($tops);
    }

    $cache[$cid] = ['parents' => $parents, 'tops' => $tops];
    return $cache[$cid];
}

/**
 * 一条评论实际落在第几页（1 起）。未开启评论分页时返回 0，与核心保持一致。
 */
function weuiCommentRealPage(\Widget\Base\Comments $comment): int
{
    $options = \Widget\Options::alloc();

    if (!$options->commentsPageBreak) {
        return 0;
    }

    $cid = (int) $comment->cid;
    if ($cid <= 0) {
        return 0;
    }

    $size = max(1, (int) $options->commentsPageSize);
    $tree = weuiCommentTree($cid, $options);
    $parents = $tree['parents'];
    $tops = $tree['tops'];

    // 定位锚点：回复看它的父评论；顶层评论就是它自己（parent 为 0，不能拿 0 去匹配）。
    // 注意别直接用评论自己的 coid 去找祖先 —— 刚提交的待审核评论还不在可见列表里。
    $top = (int) $comment->parent;
    if (0 == $top) {
        $top = (int) $comment->coid;
    }

    $guard = 0;
    while ($options->commentsThreaded && $guard++ < 64) {
        $pid = isset($parents[$top]) ? $parents[$top] : 0;
        if (0 == $pid || !isset($parents[$pid])) {
            break;
        }
        $top = $pid;
    }

    $pos = array_search($top, $tops, true);

    if (false === $pos) {
        // 没找到（顶层新评论，或者父评论还没通过审核）：
        // 按「新评论会插在排序最前 / 最后」估一个位置，顶层评论在 DESC 时必然落在第 1 页。
        $pos = ('DESC' == $options->commentsOrder) ? 0 : count($tops);
    }

    return (int) floor($pos / $size) + 1;
}

/**
 * 评论提交完成（已入库、尚未 302）时，把核心算偏的页码覆盖成正确的。
 * 出错时静默放过 —— 宁可沿用核心的旧行为，也不能让评论提交失败。
 */
function weuiFixCommentPage(\Widget\Feedback $feedback): void
{
    try {
        $page = weuiCommentRealPage($feedback);
        if ($page > 0) {
            // Widget::__set 会写进 row['#commentPage']，随后 ___permalink() 中的
            // Router::url('comment_page', $this) 就会读到这个值。
            $feedback->commentPage = $page;
        }
    } catch (\Throwable $e) {
        // ignore
    }
}

if (class_exists('\Typecho\Plugin')) {
    \Typecho\Plugin::factory('Widget\Feedback')->finishComment = 'weuiFixCommentPage';
}


