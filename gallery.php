<?php
/**
 * 相册
 *
 * @package custom
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>
<?php
// 只渲染有内容的相册（有缩略图 / 正文里有图片或视频），卡片使用与首页完全相同的图文结构
$albumRows = \WeuiAlbumRows::alloc('pageSize=200');
$shown = 0;
ob_start();
while ($albumRows->next()) {
    if (!weuiIsAlbum($albumRows)) {
        continue;
    }
    // 没有缩略图、正文里也没有任何媒体（图片/视频）的相册不渲染
    if (weuiGetThumb($albumRows) === '' && !weuiGetMedia($albumRows)) {
        continue;
    }
    weuiPostCard($albumRows);
    $shown++;
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
                    <strong class="weui-media-box__title"><?php _e('还没有相册'); ?></strong>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php $this->need('footer.php'); ?>
