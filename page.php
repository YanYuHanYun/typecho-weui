<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php $this->need('header.php'); ?>

<div class="article-wrap">
    <article class="weui-article">
        <h1><?php $this->title(); ?></h1>
        <section class="wf-post-content">
            <?php $this->content(); ?>
        </section>
    </article>

    <?php $this->need('comments.php'); ?>
</div>

<?php $this->need('footer.php'); ?>
