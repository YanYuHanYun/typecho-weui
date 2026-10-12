<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<!DOCTYPE HTML>
<html>
<head>
    <meta charset="<?php $this->options->charset(); ?>">
    <meta name="renderer" content="webkit">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
    <title><?php $this->archiveTitle([
            'category' => _t('分类 %s 下的文章'),
            'tag'      => _t('标签 %s 下的文章'),
            'author'   => _t('%s 发布的文章')
        ], '', ' - '); ?><?php $this->options->title(); ?></title>

    <!-- 仅引入本主题实际使用的 WeUI 样式子集，其余样式保留在官方 dist 中按需添加 -->
    <!-- 资源地址附带文件修改时间，确保版本更新后浏览器不会继续使用缓存的旧样式 -->
    <link rel="stylesheet" href="<?php $this->options->themeUrl('assets/weui.css'); ?><?php $t = @filemtime($this->options->themeFile($this->options->theme, 'assets/weui.css')); echo $t ? '?v=' . $t : ''; ?>">
    <link rel="stylesheet" href="<?php $this->options->themeUrl('style.css'); ?><?php $t = @filemtime($this->options->themeFile($this->options->theme, 'style.css')); echo $t ? '?v=' . $t : ''; ?>">
    <link rel="stylesheet" href="<?php $this->options->themeUrl('assets/highlight.css'); ?><?php $t = @filemtime($this->options->themeFile($this->options->theme, 'assets/highlight.css')); echo $t ? '?v=' . $t : ''; ?>">
    <?php /* 媒体灯箱：Fancybox v5（相册网格 + 正文图片/视频统一走它） */ ?>
    <link rel="stylesheet" href="<?php $this->options->themeUrl('assets/fancybox.css'); ?><?php $t = @filemtime($this->options->themeFile($this->options->theme, 'assets/fancybox.css')); echo $t ? '?v=' . $t : ''; ?>">
    <script src="<?php $this->options->themeUrl('assets/highlight.min.js'); ?><?php $t = @filemtime($this->options->themeFile($this->options->theme, 'assets/highlight.min.js')); echo $t ? '?v=' . $t : ''; ?>"></script>

    <?php
    /* 后台设置：默认配色、明暗切换按钮开关、按时间的亮色区间 */
    $wfThemeSwitch = weuiOpt('themeSwitch', '1');
    $wfThemeDefault = weuiOpt('themeDefault', 'system');
    $wfHourStart = (int) weuiOpt('themeLightStart', '7');
    $wfHourEnd = (int) weuiOpt('themeLightEnd', '19');
    $wfHourStart = max(0, min(23, $wfHourStart));
    $wfHourEnd = max(0, min(23, $wfHourEnd));
    ?>
    <script>
    window.__wfThemeCfg = {
        allowSwitch: <?php echo $wfThemeSwitch === '1' ? 'true' : 'false'; ?>,
        def: <?php echo json_encode($wfThemeDefault, JSON_UNESCAPED_UNICODE); ?>,
        lightStart: <?php echo $wfHourStart; ?>,
        lightEnd: <?php echo $wfHourEnd; ?>
    };
    </script>

    <?php /* 尽早确定配色，避免刷新时闪一下默认色：
             读取本地保存的配色模式（system / time / light / dark），
             解析成 light|dark 写到 <html> 上；<body> 开始时再同步一份给 WeUI 变量。 */ ?>
    <script>
    (function () {
        var cfg = window.__wfThemeCfg || {};
        var mode = cfg.def || 'system';
        // 后台关了切换按钮就不再读本地选择，一律用默认配色
        if (cfg.allowSwitch) {
            try {
                var saved = localStorage.getItem('wf_theme_mode');
                if (saved) mode = saved;
            } catch (e) {}
        }
        if (mode !== 'system' && mode !== 'time' && mode !== 'light' && mode !== 'dark') mode = 'system';
        var resolved = mode;
        if (mode === 'system') {
            resolved = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
        } else if (mode === 'time') {
            var h = new Date().getHours();
            var a = typeof cfg.lightStart === 'number' ? cfg.lightStart : 7;
            var b = typeof cfg.lightEnd === 'number' ? cfg.lightEnd : 19;
            // 支持跨夜区间，例如 20 点到次日 6 点
            var isLight = (a <= b) ? (h >= a && h < b) : (h >= a || h < b);
            resolved = isLight ? 'light' : 'dark';
        }
        document.documentElement.setAttribute('data-wf-theme', resolved);
        window.__wfThemeMode = mode;
    })();
    </script>

    <?php $this->header(); ?>
</head>
<body>
<script>
(function () {
    var t = document.documentElement.getAttribute('data-wf-theme');
    if (t) document.body.setAttribute('data-weui-theme', t);
})();
</script>
<div class="page<?php if ($this->is('single')): ?> article<?php endif; ?>">
    <header class="site-header<?php if ($this->options->stickyHeader == '1'): ?> site-header--fixed<?php endif; ?>">
        <div class="site-brand">
            <a class="site-title" href="<?php $this->options->siteUrl(); ?>"><?php $this->options->title(); ?></a>
            <?php if ($this->options->description && weuiOpt('showDescription', '1') === '1'): ?>
                <span class="site-desc"><?php $this->options->description(); ?></span>
            <?php endif; ?>
        </div>
        <nav class="site-nav">
            <a<?php if ($this->is('index')): ?> class="current"<?php endif; ?>
               href="<?php $this->options->siteUrl(); ?>"><?php _e('首页'); ?></a>
            <?php \Widget\Contents\Page\Rows::alloc()->to($pages); ?>
            <?php while ($pages->next()): ?>
                <a<?php if ($this->is('page', $pages->slug)): ?> class="current"<?php endif; ?>
                   href="<?php $pages->permalink(); ?>"><?php $pages->title(); ?></a>
            <?php endwhile; ?>
            <?php if (weuiOpt('searchEnable', '1') === '1'): ?>
                <button type="button" class="site-search-toggle" data-search-toggle
                        aria-label="<?php _e('搜索'); ?>" aria-expanded="false">
                    <svg viewBox="0 0 24 24" width="17" height="17" aria-hidden="true"><path fill="currentColor" d="M16.31 15.561l4.114 4.115-.848.848-4.123-4.123a7 7 0 1 1 .857-.84zM16.8 11a5.8 5.8 0 1 0-11.6 0 5.8 5.8 0 0 0 11.6 0z"/></svg>
                </button>
            <?php endif; ?>
        </nav>

        <?php if (weuiOpt('searchEnable', '1') === '1'): ?>
            <?php /* WeUI 搜索栏：默认隐藏，点导航右侧的放大镜展开 / 收起 */ ?>
            <div class="site-search" id="site-search" hidden>
                <?php /* action 用站点 index（带 / 或 /index.php），提交后由 Typecho 跳到
                           /search/<关键词>/ 结果页；无 JS 时 ?s= 也能正常搜索。 */ ?>
                <form class="weui-search-bar weui-search-bar_focusing" method="get"
                      action="<?php echo htmlspecialchars((string) $this->options->index, ENT_QUOTES, 'UTF-8'); ?>" role="search">
                    <div class="weui-search-bar__form">
                        <div class="weui-search-bar__box">
                            <svg class="site-search__icon" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M16.31 15.561l4.114 4.115-.848.848-4.123-4.123a7 7 0 1 1 .857-.84zM16.8 11a5.8 5.8 0 1 0-11.6 0 5.8 5.8 0 0 0 11.6 0z"/></svg>
                            <input type="search" class="weui-search-bar__input" name="s"
                                   placeholder="<?php _e('搜索文章…'); ?>" aria-label="<?php _e('搜索'); ?>">
                            <button type="button" class="site-search__clear" data-search-clear
                                    aria-label="<?php _e('清空'); ?>" hidden>
                                <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M13.06 12l3.006-3.005-1.06-1.06L12 10.938 8.995 7.934l-1.06 1.06L10.938 12l-3.005 3.005 1.06 1.06L12 13.062l3.005 3.005 1.06-1.06L13.062 12zM12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/></svg>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="weui-search-bar__cancel-btn"><?php _e('搜索'); ?></button>
                </form>
            </div>
        <?php endif; ?>
    </header>
