<?php
/**
 * 相册详情：正方形网格 + 懒加载淡入 + Fancybox 灯箱。
 * 由 post.php 在文章标记为相册（自定义字段 album = 1）时引入。
 * 媒体取自文章正文：<img> 为图片，<video> 为视频（Markdown 会原样保留 raw HTML）。
 * 图片说明取 alt，视频说明取 title / alt / data-caption。
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 阅读次数 +1
weuiViewsAdd((int) $this->cid);

$this->need('header.php');

$media = weuiGetMedia($this);
$placeholder = weuiPlaceholderSrc();
$videoPlaceholder = weuiVideoPlaceholderSrc();
?>
<div class="article-wrap">
    <article class="weui-article">
        <h1><?php $this->title(); ?></h1>
        <?php weuiArticleMeta($this); ?>
        <section>
            <?php if ($media): ?>
                <?php /* 正方形网格：所有缩略图裁成 1:1 依次排列（不再瀑布流）。
                         每个格子是一个 data-fancybox 触发器，灯箱由 Fancybox 统一接管。 */ ?>
                <div class="wf-grid">
                    <?php foreach ($media as $m): ?>
                        <?php
                        $isVideo = ($m['type'] === 'video');
                        $hasPoster = ($m['poster'] !== '');
                        // 视频缩略图：有 poster 用它；没有就让 JS 抽一帧当预览（data-wf-video-src）；
                        // 抽帧失败时仍显示深色占位块。
                        $thumb = $isVideo ? ($hasPoster ? $m['poster'] : $videoPlaceholder) : $m['src'];
                        $ph = $isVideo ? $videoPlaceholder : $placeholder;
                        ?>
                        <a class="wf-grid__item<?php echo $isVideo ? ' wf-grid__item--video' : ''; ?>"
                           href="<?php echo htmlspecialchars($m['src'], ENT_QUOTES, 'UTF-8'); ?>"
                           data-fancybox="wf-album"
                           data-no-pjax
                           data-type="<?php echo $isVideo ? 'html5video' : 'image'; ?>"
                           <?php if ($isVideo && $hasPoster): ?>data-poster="<?php echo htmlspecialchars($m['poster'], ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>
                           data-caption="<?php echo htmlspecialchars($m['caption'], ENT_QUOTES, 'UTF-8'); ?>">
                            <img class="wf-img" src="<?php echo $ph; ?>" data-wf-src="<?php echo htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8'); ?>"<?php if ($isVideo && !$hasPoster): ?> data-wf-video-src="<?php echo htmlspecialchars($m['src'], ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?> alt="<?php echo htmlspecialchars($m['caption'], ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" decoding="async">
                            <?php if ($isVideo): ?>
                                <span class="wf-play" aria-hidden="true">
                                    <svg viewBox="0 0 1024 1024" width="28" height="28"><path fill="currentColor" d="M320 200l480 312-480 312V200z"/></svg>
                                </span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="gallery-empty">这篇相册还没有内容。在正文中插入图片或视频即可显示。</p>
            <?php endif; ?>
        </section>
    </article>

    <?php
    /* 点赞 / 打赏 / 分享 单独一行，放在标签上方 */
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

    <?php $this->need('comments.php'); ?>
</div>
<?php $this->need('footer.php'); ?>
