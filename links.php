<?php
/**
 * 友链
 *
 * @package custom
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 将正文按行拆成纯文本，解析“名称|网址|图片|介绍”格式
$text = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", (string) $this->content));
$links = [];
foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
    $line = trim($line);
    if ($line === '' || strpos($line, '|') === false) {
        continue;
    }
    $parts = array_map('trim', explode('|', $line));
    $name = isset($parts[0]) ? $parts[0] : '';
    $url  = isset($parts[1]) ? $parts[1] : '';
    $img  = isset($parts[2]) ? $parts[2] : '';
    $desc = isset($parts[3]) ? $parts[3] : '';
    if ($name === '' || $url === '') {
        continue;
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = '//' . ltrim($url, '/');
    }
    $links[] = ['name' => $name, 'url' => $url, 'img' => $img, 'desc' => $desc];
}

$this->need('header.php');
?>
<div class="article-wrap">
    <article class="weui-article">
        <h1><?php $this->title(); ?></h1>
        <section>
            <?php if ($links): ?>
                <div class="link-grids">
                    <?php foreach ($links as $link): ?>
                        <a class="link-grid" href="<?php echo htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener nofollow">
                            <?php if ($link['img'] !== ''): ?>
                                <span class="link-grid__icon">
                                    <?php echo weuiLazyImg($link['img']); ?>
                                </span>
                            <?php endif; ?>
                            <span class="link-grid__name"><?php echo htmlspecialchars($link['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if ($link['desc'] !== ''): ?>
                                <span class="link-grid__desc"><?php echo htmlspecialchars($link['desc'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <?php $this->content(); ?>
            <?php endif; ?>
        </section>
    </article>

    <?php $this->need('comments.php'); ?>
</div>
<?php $this->need('footer.php'); ?>
