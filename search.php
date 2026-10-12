<?php
/**
 * 搜索结果页
 *
 * 这是 Typecho 的**保留模板名**，专门用于 `search` 类型路由（/search/关键词/）。
 * 之前主题没有这个文件，搜索页由 index.php 兜底渲染，容易混进历史遗留的搜索框等杂物。
 * 这里给搜索页一个干净、独立的模板：只显示标题 + 结果列表 + 分页，不再额外渲染搜索框
 * （页面顶部的搜索栏是 header.php 里那个按需展开的，默认隐藏）。
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>

<h1 class="archive-title"><?php $this->archiveTitle([
        'search' => _t('包含关键字 %s 的文章')
    ], '', ''); ?></h1>

<?php
// 相册文章（album=1）不在列表出现，这里跳过
$shown = 0;
ob_start();
if ($this->have()) {
    while ($this->next()) {
        if (weuiIsAlbum($this)) {
            continue;
        }
        weuiPostCard($this);
        $shown++;
    }
}
$cards = ob_get_clean();
?>
<div class="weui-panel">
    <div class="weui-panel__bd">
        <?php if ($shown): ?>
            <?php echo $cards; ?>
        <?php else: ?>
            <div class="weui-media-box weui-media-box_text">
                <div class="weui-media-box__bd">
                    <strong class="weui-media-box__title"><?php _e('没有找到相关内容'); ?></strong>
                    <p class="weui-media-box__desc"><?php _e('换一个关键词试试，或减少关键词数量。'); ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php weuiPagination($this); ?>

<?php $this->need('footer.php'); ?>
