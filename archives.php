<?php
/**
 * 归档
 *
 * @package custom
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/*
 * 重要：这个文件同时是 Typecho 的「归档兜底模板」候选之一。
 * 当访问分类 / 标签 / 作者 / 日期归档页时，Typecho 会按
 *   tag.php → 最后 archive.php / single.php → index.php
 * 的顺序找模板；本主题没有 archive.php，于是这些归档页会走到本文件！
 *
 * 如果再在本文件里用 Recent::alloc() 查询，就会破坏归档组件自身的查询状态，
 * 导致随后 index.php 里的分页取不到参数而报错（表现为 500 或空白）。
 * 所以：只有真正作为「独立页面」渲染时才输出归档时间线；其它归档类型直接交给 index.php。
 */
if (!$this->is('page')) {
    $this->need('index.php');
    return;
}

$this->need('header.php');
?>
<div class="article-wrap">
    <article class="weui-article">
        <h1><?php $this->title(); ?></h1>
        <section>
            <?php weuiArchiveTimeline(); ?>
        </section>
    </article>
</div>
<?php $this->need('footer.php'); ?>
