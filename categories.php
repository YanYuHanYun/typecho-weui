<?php
/**
 * 分类
 *
 * @package custom
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 统计每个分类下的文章数，排除相册文章
$counts = [];
$rows = \WeuiAlbumRows::alloc('pageSize=2000');
while ($rows->next()) {
    if (weuiIsAlbum($rows)) {
        continue;
    }
    foreach ($rows->categories as $cat) {
        $mid = (int) $cat['mid'];
        $counts[$mid] = (isset($counts[$mid]) ? $counts[$mid] : 0) + 1;
    }
}

$this->need('header.php');
?>
<div class="article-wrap">
    <article class="weui-article">
        <h1><?php $this->title(); ?></h1>
        <section>
            <div class="weui-cells">
                <?php \Widget\Metas\Category\Rows::alloc()->to($categories); ?>
                <?php while ($categories->next()): $mid = (int) $categories->mid; ?>
                    <a class="weui-cell weui-cell_access" href="<?php $categories->permalink(); ?>">
                        <span class="weui-cell__bd"><?php $categories->name(); ?></span>
                        <span class="weui-cell__ft"><?php echo isset($counts[$mid]) ? $counts[$mid] : 0; ?> 篇</span>
                    </a>
                <?php endwhile; ?>
            </div>
        </section>
    </article>
</div>
<?php $this->need('footer.php'); ?>
