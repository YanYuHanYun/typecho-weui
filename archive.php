<?php
/**
 * 通用归档模板（标签 / 作者 / 日期归档，以及没有专门模板时的分类归档）
 *
 * 说明：这是 Typecho 的**保留文件名**，用于所有归档类型页面的兜底渲染。
 * 刻意**不加** `@package custom`，这样它不会出现在后台「自定义模板」下拉里
 * （避免和自定义页面模板「归档 archives.php」重名混淆）。
 *
 * 渲染结构与首页列表保持一致（weui-panel + 图文卡片 + 分页）。
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>

<h1 class="archive-title"><?php $this->archiveTitle([
        'category' => _t('分类 %s 下的文章'),
        'tag'      => _t('标签 %s 下的文章'),
        'author'   => _t('%s 发布的文章'),
        'date'     => _t('%s 发布的文章')
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
                    <strong class="weui-media-box__title"><?php _e('没有找到内容'); ?></strong>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php weuiPagination($this); ?>

<?php $this->need('footer.php'); ?>
