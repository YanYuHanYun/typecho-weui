<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php
// 相册文章（自定义字段 album = 1）用相册布局展示
if (weuiIsAlbum($this)) {
    $this->need('gallery_album.php');
    exit;
}
// 阅读次数 +1（同一请求只加一次）
weuiViewsAdd((int) $this->cid);
$this->need('header.php');
?>
<div class="article-wrap">
    <article class="weui-article">
        <h1><?php $this->title(); ?></h1>
        <?php weuiArticleMeta($this); ?>
        <section class="wf-post-content">
            <?php
            ob_start();
            $this->content();
            $wfContent = weuiPrepareContent(ob_get_clean());
            // 生成文章目录：给 h2/h3/h4 补 id，并把目录树存到全局供 footer 渲染按钮
            $wfToc = weuiBuildToc($wfContent);
            $GLOBALS['weui_toc'] = $wfToc['items'];
            echo $wfToc['html'];
            ?>
        </section>
    </article>

    <?php
    /* 点赞 / 打赏 / 分享 单独一行，放在标签上方。
       打赏只在后台配了二维码时才出现；分享可在后台关闭；两者都排在点赞右边。 */
    $wfVotePost = weuiOpt('votePost', '1') === '1';
    ob_start();
    weuiRewardHtml();
    weuiShareHtml($this);
    $wfMiddle = trim(ob_get_clean());
    ?>
    <?php if ($wfVotePost || $wfMiddle !== ''): ?>
    <div class="article-actions">
        <?php if ($wfVotePost): ?>
            <?php weuiVoteHtml('post', (int) $this->cid, $wfMiddle); ?>
        <?php else: ?>
            <span class="wf-vote wf-vote--actions-only"><?php echo $wfMiddle; ?></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="article-footer">
        <span><?php _e('标签'); ?>: <?php $this->tags(', ', true, _t('无')); ?></span>
    </div>

    <nav class="post-near">
        <?php
        // 上一篇 / 下一篇：排除相册文章（thePrev/theNext 不经过 Archive::query 钩子）
        $wfPrev = weuiNearPost($this, '_prev');
        $wfNext = weuiNearPost($this, '_next');
        ?>
        <div><?php _e('上一篇'); ?>: <?php echo $wfPrev !== '' ? $wfPrev : _t('没有了'); ?></div>
        <div><?php _e('下一篇'); ?>: <?php echo $wfNext !== '' ? $wfNext : _t('没有了'); ?></div>
    </nav>

    <?php $this->need('comments.php'); ?>
</div>

<?php $this->need('footer.php'); ?>
