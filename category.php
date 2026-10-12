<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php $this->need('header.php'); ?>

<h1 class="archive-title"><?php $this->archiveTitle([
        'category' => _t('分类 %s 下的文章')
    ], '', ''); ?></h1>

<?php
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
                    <strong class="weui-media-box__title"><?php _e('该分类下暂无文章'); ?></strong>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php weuiPagination($this); ?>

<?php $this->need('footer.php'); ?>
