<?php
/**
 * WeUI 主题
 *
 * 基于腾讯 WeUI 开发的主题。
 *
 * @package WeUI
 * @author 烟雨寒云
 * @version 1.0.0
 * @link https://yyhy.me/
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>

<?php if (!$this->is('index')): ?>
    <h1 class="archive-title"><?php $this->archiveTitle([
            'category' => _t('分类 %s 下的文章'),
            'tag'      => _t('标签 %s 下的文章'),
            'author'   => _t('%s 发布的文章')
        ], '', ''); ?></h1>
<?php endif; ?>

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
