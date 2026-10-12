<?php
/**
 * 关于
 *
 * @package custom
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>
<div class="article-wrap">
    <article class="weui-article">
        <h1><?php $this->title(); ?></h1>
        <section class="wf-post-content">
            <?php
            ob_start();
            $this->content();
            echo weuiPrepareContent(ob_get_clean());
            ?>
        </section>
    </article>

    <?php $this->need('comments.php'); ?>
</div>
<?php $this->need('footer.php'); ?>
