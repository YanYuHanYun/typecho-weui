<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
    <footer class="site-footer">
        <?php
        // 后台自定义的页脚内容（支持 HTML），显示在版权信息上方
        $wfFooterExtra = (string) weuiOpt('footerExtra', '');
        if (trim($wfFooterExtra) !== ''):
            ?>
            <div class="site-footer__extra"><?php echo $wfFooterExtra; ?></div>
        <?php endif; ?>
        <div class="weui-footer">
            <?php
            // 版权：Copyright © 建站年份-当前年份 网站名（本站链接）
            $wfStart = (int) weuiOpt('siteStartYear', '');
            $wfNow = (int) date('Y');
            $wfYears = ($wfStart > 0 && $wfStart < $wfNow) ? ($wfStart . '-' . $wfNow) : (string) $wfNow;
            ?>
            <p class="weui-footer__text">
                Copyright &copy; <?php echo $wfYears; ?>
                <a href="<?php $this->options->siteUrl(); ?>"><?php $this->options->title(); ?></a>
                All Rights Reserved.
            </p>
            <p class="weui-footer__text wf-powered">
                <?php _e('由'); ?>
                <a class="wf-powered__link" href="https://typecho.org" target="_blank" rel="noopener" aria-label="Typecho">
                    <?php /* Typecho 官方 logo，直接内联（用 currentColor，颜色交给 CSS 变量） */ ?>
                    <svg class="wf-typecho-logo" viewBox="0 0 110 26" role="img" aria-label="Typecho" focusable="false">
                        <path fill="currentColor" d="M34.75,5.288 C34.288,6.542 33.76,7.73 32.22,7.862 L32,9.468 L33.562,9.468 L33.562,15.342 C33.562,16.882 33.54,18.994 36.972,18.994 C38.006,18.994 39.106,18.686 39.766,18.224 L39.106,16.53 C38.754,16.75 38.204,16.992 37.61,16.992 C36.708,16.992 36.18,16.596 36.18,15.254 L36.18,9.468 L38.886,9.468 L39.106,7.62 L36.18,7.62 L36.18,5.288 L34.75,5.288 Z M48.258,18.268 C48.258,20.27 47.444,21.502 45.42,21.502 C44.76,21.502 44.276,21.436 43.858,21.282 C43.462,21.128 43.352,20.908 43.352,20.49 L43.352,19.434 L41.262,19.61 L41.262,22.668 C42.186,23.13 44.012,23.394 45.398,23.394 C48.676,23.394 50.502,21.898 50.502,18.268 L50.502,7.62 L46.63,7.62 L46.63,9.424 L47.334,9.468 C47.752,9.468 47.884,9.644 47.884,10.128 L47.884,14.11 C47.884,15.254 47.07,16.288 45.53,16.288 C44.122,16.288 43.902,15.276 43.902,13.934 L43.902,7.62 L40.03,7.62 L40.03,9.424 L40.734,9.468 C41.108,9.49 41.284,9.622 41.284,10.084 L41.284,14.506 C41.284,17.102 42.494,18.312 44.694,18.312 C46.146,18.312 47.488,17.696 48.258,16.596 L48.258,18.268 Z M54,20.776 C54,21.326 53.78,21.458 53.362,21.502 L52.636,21.568 L52.636,23.24 L58.312,23.24 L58.312,21.502 L56.53,21.414 L56.53,18.378 C57.102,18.73 58.026,19.016 58.884,19.016 C61.788,19.016 63.702,16.926 63.702,12.878 C63.702,8.94 62.162,7.29 59.72,7.29 C57.85,7.29 56.64,8.302 56.244,9.05 L56.244,7.62 L52.526,7.62 L52.526,9.402 L53.45,9.468 C53.868,9.468 54,9.644 54,10.128 L54,20.776 Z M60.974,13.098 C60.974,15.012 60.336,16.926 58.466,16.926 C57.894,16.926 57.102,16.75 56.53,16.376 L56.53,11.316 C56.53,10.304 57.498,9.424 58.752,9.424 C59.918,9.424 60.974,10.172 60.974,13.098 Z M70.786,7.29 C67.178,7.29 65.352,10.15 65.352,13.406 C65.352,16.684 66.804,18.972 70.544,18.972 C72.612,18.972 74.064,18.048 74.416,17.74 L73.58,15.958 C73.052,16.332 72.106,16.926 70.808,16.926 C68.938,16.926 68.19,15.76 68.102,14.33 C70.698,14.308 74.372,13.736 74.372,10.348 C74.372,8.39 72.942,7.29 70.786,7.29 Z M71.952,10.392 C71.952,12.086 69.642,12.46 68.014,12.482 C68.08,10.854 68.872,9.16 70.632,9.16 C71.424,9.16 71.952,9.578 71.952,10.392 Z M81.192,16.97 C79.234,16.97 78.354,15.43 78.354,13.032 C78.354,10.59 79.256,9.27 81.016,9.27 C81.346,9.27 81.61,9.314 81.874,9.402 C82.27,9.534 82.336,9.732 82.336,10.15 L82.336,11.206 L84.36,11.052 L84.36,8.192 C83.304,7.62 82.248,7.29 80.928,7.29 C78.442,7.29 75.692,8.83 75.692,13.296 C75.692,16.948 77.606,18.994 80.84,18.994 C82.468,18.994 83.81,18.422 84.668,17.718 L83.722,16.024 C82.82,16.684 82.05,16.97 81.192,16.97 Z M87.286,16.222 C87.286,16.772 87.066,16.904 86.648,16.948 L85.922,17.014 L85.922,18.686 L91.158,18.686 L91.158,16.926 L89.904,16.86 L89.904,11.536 C89.904,10.392 90.718,9.314 92.258,9.314 C93.666,9.314 93.974,10.348 93.974,11.69 L93.974,16.222 C93.974,16.772 93.754,16.904 93.336,16.948 L92.61,17.014 L92.61,18.686 L97.846,18.686 L97.846,16.926 L96.592,16.86 L96.592,11.118 C96.592,8.522 95.294,7.29 93.094,7.29 C91.642,7.29 90.542,7.972 89.882,8.918 L89.882,3 L85.966,3 L85.966,4.826 L86.736,4.87 C87.154,4.892 87.286,5.024 87.286,5.508 L87.286,16.222 Z M98.924,13.142 C98.924,17.124 100.86,19.016 103.808,19.016 C106.712,19.016 109.066,17.08 109.066,12.856 C109.066,7.796 105.788,7.29 104.16,7.29 C101.894,7.29 98.924,8.566 98.924,13.142 Z M103.984,17.08 C101.872,17.08 101.586,14.88 101.586,12.834 C101.586,10.722 102.29,9.226 104.028,9.226 C105.788,9.226 106.382,10.744 106.382,13.208 C106.382,15.496 105.7,17.08 103.984,17.08 Z"/>
                        <path fill="currentColor" d="M13,26 C3.36833333,26 0,22.631 0,13 C0,3.36866667 3.36833333,0 13,0 C22.6316667,0 26,3.36866667 26,13 C26,22.631 22.6316667,26 13,26 Z M6,9 L20,9 L20,7 L6,7 L6,9 Z M6,14 L16,14 L16,12 L6,12 L6,14 Z M6,19 L18,19 L18,17 L6,17 L6,19 Z"/>
                    </svg>
                </a>
                <?php _e('强力驱动'); ?>
            </p>
        </div>
    </footer>
</div><!-- end .page -->

<?php
/* 侧边悬浮控制：文章目录 + 外观设置 + 返回顶部
   （放在 .page 之外，PJAX 换页时不会被替换掉）
   后台关闭「明暗模式切换按钮」后，只保留返回顶部。
   注意：目录按钮/面板必须**始终输出**（默认 hidden）。它不在 .page 里，
   PJAX 换页不会重建，若只在文章页服务端渲染，就会出现
   「无刷新进文章不显示 / 无刷新离开文章按钮还在」。显隐统一交给
   theme.js 的 initToc()，每次（含 PJAX 后）按当前正文标题数决定。 */
$wfShowThemeBtn = weuiOpt('themeSwitch', '1') === '1';
$wfToc = isset($GLOBALS['weui_toc']) && is_array($GLOBALS['weui_toc']) ? $GLOBALS['weui_toc'] : [];
// 至少两个标题才值得给目录（只有一个标题意义不大）；JS 会再校正一次
$wfShowToc = count($wfToc) >= 2;
?>
<div class="wf-fab" id="wf-fab">
    <?php /* 文章目录面板：服务端已有标题列表时直接输出（无 JS 也能看），
             否则留空由 theme.js 依据当前正文重建。默认 hidden，由 initToc() 控制。 */ ?>
    <nav class="wf-toc" id="wf-toc" aria-label="<?php _e('文章目录'); ?>" hidden>
        <div class="wf-toc__hd">
            <span class="wf-toc__title"><?php _e('文章目录'); ?></span>
            <button type="button" class="wf-toc__close" data-toc-close aria-label="<?php _e('收起目录'); ?>">&#10005;</button>
        </div>
        <ol class="wf-toc__list">
            <?php foreach ($wfToc as $item): ?>
                <li class="wf-toc__item wf-toc__item--h<?php echo (int) $item['level']; ?>">
                    <a href="#<?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>"
                       data-toc-target="<?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($item['text'], ENT_QUOTES, 'UTF-8'); ?></a>
                </li>
            <?php endforeach; ?>
        </ol>
    </nav>
    <button type="button" class="wf-fab__btn wf-fab__btn--toc" data-fab-toc aria-label="<?php _e('文章目录'); ?>" aria-expanded="false"<?php echo $wfShowToc ? '' : ' hidden'; ?>>
        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M3 5h18v2H3zm0 6h12v2H3zm0 6h18v2H3z"/></svg>
    </button>

    <?php if ($wfShowThemeBtn): ?>
    <div class="wf-fab__panel" id="wf-fab-panel" role="radiogroup" aria-label="<?php _e('网站配色'); ?>" hidden>
        <div class="wf-fab__panel-hd">
            <span class="wf-fab__panel-title"><?php _e('网站配色'); ?></span>
            <button type="button" class="wf-fab__panel-close" data-fab-theme-close aria-label="<?php _e('收起配色'); ?>">&#10005;</button>
        </div>
        <button type="button" class="wf-fab__opt" role="radio" data-theme-mode="system" aria-checked="false">
            <span class="wf-fab__opt-name"><?php _e('跟随系统'); ?></span>
            <svg class="wf-fab__check" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        </button>
        <button type="button" class="wf-fab__opt" role="radio" data-theme-mode="time" aria-checked="false">
            <span class="wf-fab__opt-name"><?php _e('按时间自动'); ?></span>
            <svg class="wf-fab__check" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        </button>
        <button type="button" class="wf-fab__opt" role="radio" data-theme-mode="light" aria-checked="false">
            <span class="wf-fab__opt-name"><?php _e('亮色'); ?></span>
            <svg class="wf-fab__check" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        </button>
        <button type="button" class="wf-fab__opt" role="radio" data-theme-mode="dark" aria-checked="false">
            <span class="wf-fab__opt-name"><?php _e('暗色'); ?></span>
            <svg class="wf-fab__check" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        </button>
    </div>

    <button type="button" class="wf-fab__btn wf-fab__btn--theme" data-fab-theme aria-label="<?php _e('外观设置'); ?>" aria-expanded="false">
        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58a.49.49 0 0 0 .12-.61l-1.92-3.32a.488.488 0 0 0-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54a.484.484 0 0 0-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58a.49.49 0 0 0-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6a3.6 3.6 0 1 1 0-7.2 3.6 3.6 0 0 1 0 7.2z"/></svg>
    </button>
    <?php endif; ?>

    <?php /* 音乐播放器：与目录/设置按钮同处 #wf-fab（.page 之外），PJAX 换页不重建 → 播放不中断 */ ?>
    <?php weuiMusicHtml(); ?>

    <button type="button" class="wf-fab__btn wf-fab__btn--top" data-fab-top aria-label="<?php _e('返回顶部'); ?>">
        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M4 12l1.41 1.41L11 7.83V20h2V7.83l5.58 5.59L20 12l-8-8-8 8z"/></svg>
    </button>
</div>

<?php
/* 顶部提示条（WeUI toptips）：评论失败 / 密码错误等提示用。放在 .page 之外，
   PJAX 换页不会被替换；默认隐藏，由 theme.js 的 wfToptips() 控制显隐。 */
?>
<div class="weui-toptips weui-toptips_warn" id="wf-toptips" role="alert" aria-live="assertive" hidden></div>

<?php
/* 统计代码：
   只在整页加载时输出一次（它在 .page 之外，PJAX 换页不会重新执行，因此不会重复加载脚本）。
   无刷新跳转时，由 theme.js 调用各统计平台的标准接口补发一次浏览，
   这样既不会漏统计，也不会因为脚本重复执行而重复统计。 */
$wfAnalytics = (string) weuiOpt('analyticsCode', '');
if (trim($wfAnalytics) !== '') {
    echo "\n<!-- 统计代码（首次加载执行一次，无刷新时由 theme.js 补发 pageview） -->\n";
    echo $wfAnalytics . "\n";
}
?>
<?php $this->footer(); ?>
<?php
/* 分享面板的「微信扫码」需要二维码库（qrcodejs，MIT，约 20KB）。
   和 highlight.min.js 一样整站加载：footer 的 script 在 .page 之外，
   PJAX 无刷新跳转到文章页时不会重新执行，若按页面条件加载会取不到 QRCode。 */
?>
<script src="<?php $this->options->themeUrl('assets/qrcode.min.js'); ?><?php $t = @filemtime($this->options->themeFile($this->options->theme, 'assets/qrcode.min.js')); echo $t ? '?v=' . $t : ''; ?>"></script>
<?php
/* 媒体灯箱：Fancybox v5（UMD）。同样整站加载，PJAX 换页后仍可用。 */
?>
<script src="<?php $this->options->themeUrl('assets/fancybox.umd.js'); ?><?php $t = @filemtime($this->options->themeFile($this->options->theme, 'assets/fancybox.umd.js')); echo $t ? '?v=' . $t : ''; ?>"></script>
<?php
/* 音乐播放器配置：整站加载（footer 的 script 在 .page 之外，PJAX 换页不重执行）。
   歌单 ID/平台与代理地址交给 theme.js 的 initMusic() 使用；播放器本身自研，不引第三方库。 */
$wfMusicItems = (weuiOpt('musicEnable', '0') === '1') ? weuiMusicItems() : [];
if ($wfMusicItems):
    $wfMusicCfgItems = [];
    foreach ($wfMusicItems as $it) {
        $wfMusicCfgItems[] = [
            'server' => $it['server'],
            'type'   => $it['type'],
            'id'     => $it['id'],
            'auth'   => weuiMusicAuth($it['server'], $it['type'], $it['id']),
        ];
    }
    ?>
    <script>
    window.__wfMusicCfg = {
        endpoint: <?php echo json_encode(\Widget\Options::alloc()->siteUrl . '/?weui_music=', JSON_UNESCAPED_UNICODE); ?>,
        items: <?php echo json_encode($wfMusicCfgItems, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>,
        autoplay: <?php echo weuiOpt('musicAutoplay', '0') === '1' ? 'true' : 'false'; ?>,
        lyric: <?php echo weuiOpt('musicLyric', '1') === '1' ? 'true' : 'false'; ?>,
        mode: <?php echo json_encode(in_array(weuiOpt('musicMode', 'list'), ['list','random','single'], true) ? weuiOpt('musicMode', 'list') : 'list'); ?>
    };
    </script>
    <?php
endif;
?>
<?php /* 贴底歌词容器（.page 之外，PJAX 换页不重建） */ ?>
<?php weuiMusicLrcHtml(); ?>
<script src="<?php $this->options->themeUrl('assets/theme.js'); ?><?php $t = @filemtime($this->options->themeFile($this->options->theme, 'assets/theme.js')); echo $t ? '?v=' . $t : ''; ?>"></script>
</body>
</html>
