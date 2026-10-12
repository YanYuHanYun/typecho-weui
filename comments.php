<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php
/* 评论框位置：top = 评论列表上方（默认）；bottom = 所有评论下方。
   评论区结构 = [表单] + [评论列表 + 翻页]，这里按配置决定两者先后，
   所以用输出缓冲把表单块整段捕获，再按顺序 echo。 */
$weuiFormPos = weuiOpt('commentFormPos', 'top');
?>
<div class="comments<?php echo $weuiFormPos === 'bottom' ? ' comments--form-bottom' : ''; ?>" id="comments">
    <?php $this->comments()->to($comments); ?>

    <?php
    /* 评论输入框：默认放在「已有 N 条评论」上方（先写评论，再看评论列表）。 */
    ob_start();
    ?>
    <?php if ($this->allow('comment')): ?>
        <div id="<?php $this->respondId(); ?>" class="respond">
            <form method="post" action="<?php $this->commentUrl(); ?>" id="comment-form" class="comment-form" role="form">
                <?php /* 反垃圾令牌：服务端直接渲染，保证 PJAX / AJAX 换新表单后依然存在 */ ?>
                <?php $weuiToken = weuiAntiSpamToken(); ?>
                <?php if ($weuiToken !== ''): ?>
                    <input type="hidden" name="_" value="<?php echo htmlspecialchars($weuiToken, ENT_QUOTES, 'UTF-8'); ?>">
                <?php endif; ?>
                <?php if ($this->user->hasLogin()): ?>
                    <div class="comment-form-login"><?php _e('登录身份'); ?>:
                        <a href="<?php $this->options->profileUrl(); ?>"><?php $this->user->screenName(); ?></a>
                        <a class="logout" href="<?php $this->options->logoutUrl(); ?>"><?php _e('退出'); ?></a>
                        <span class="cancel-comment-reply"><?php $comments->cancelReply(); ?></span>
                    </div>
                <?php else: ?>
                    <?php
                    // 游客身份行里的头像：用 Gravatar（后台可配 CDN 加速）。
                    // 先按 cookie 里记住的邮箱渲染，之后 JS 会在邮箱变化时实时更新。
                    $weuiMail = (string) $this->remember('mail', true);
                    $weuiAvatarSrc = weuiGravatarUrl($weuiMail, 48);
                    $weuiAvatarBase = weuiGravatarDir();
                    ?>
                    <div class="comment-form-meta">
                        <span class="comment-identity">
                            <img class="comment-identity__avatar" data-identity-avatar
                                 src="<?php echo htmlspecialchars($weuiAvatarSrc, ENT_QUOTES, 'UTF-8'); ?>"
                                 data-avatar-base="<?php echo htmlspecialchars($weuiAvatarBase, ENT_QUOTES, 'UTF-8'); ?>"
                                 alt="" width="24" height="24" loading="lazy">
                            <span class="comment-identity__name" data-comment-identity><?php _e('游客评论'); ?></span>
                            <button type="button" class="comment-pencil" data-profile-open aria-label="<?php _e('填写评论信息'); ?>">
                                <svg viewBox="0 0 1024 1024" width="14" height="14" aria-hidden="true"><path fill="currentColor" d="M874.7 224.1 799.9 149.3c-19.9-19.9-52.2-19.9-72.1 0L206.3 670.8c-3.5 3.5-6 7.8-7.3 12.5l-47.6 171.6c-3 10.8 0.3 22.3 8.7 30.1 6 6 14.1 9.2 22.3 9.2 2.6 0 5.2-0.4 7.8-1.2l171.6-47.6c4.7-1.3 9-3.8 12.5-7.3l521.5-521.5c19.9-19.9 19.9-52.2-0.1-72.1zM352.6 809.7l-97.4 27 27-97.4L727.9 293.6l70.4 70.4-445.7 445.7z"/></svg>
                            </button>
                        </span>
                        <span class="cancel-comment-reply"><?php $comments->cancelReply(); ?></span>
                    </div>
                    <?php /* 昵称 / 邮箱 / 网站由弹窗填写，这里只保留隐藏字段承载提交值；
                             服务端已用 cookie 记忆值预填，回头客无需再填。 */ ?>
                    <input type="hidden" name="author" id="author" value="<?php $this->remember('author'); ?>" required>
                    <input type="hidden" name="mail" id="mail" value="<?php $this->remember('mail'); ?>"<?php if ($this->options->commentsRequireMail): ?> data-required="1"<?php endif; ?>>
                    <input type="hidden" name="url" id="url" value="<?php $this->remember('url'); ?>"<?php if ($this->options->commentsRequireUrl): ?> data-required="1"<?php endif; ?>>
                <?php endif; ?>

                <?php /*
                    默认输出原生 textarea（无 JS 时也能评论）；
                    JS 启用后把它替换成 contenteditable 富文本，以便表情/贴纸所见即所得，
                    提交前再把内容序列化成 [sticker:名字] / [emoji:编号] 短代码写回 textarea。
                */ ?>
                <div class="comment-editor">
                    <textarea rows="4" name="text" id="textarea" class="comment-textarea" placeholder="<?php _e('写下你的想法…'); ?>" required><?php $this->remember('text'); ?></textarea>
                    <div class="comment-rich" id="comment-rich" contenteditable="true" role="textbox" aria-multiline="true" hidden
                         data-placeholder="<?php _e('写下你的想法…'); ?>"
                         data-emoji-base="<?php $this->options->themeUrl('assets/emoticons'); ?>"></div>
                </div>

                <?php if (!$this->user->hasLogin()): ?>
                    <div class="comment-profile-hint" data-profile-hint hidden></div>
                <?php endif; ?>

                <?php /* 底部工具行：表情面板绝对定位在这一行之上、向上展开覆盖输入框 */ ?>
                <div class="comment-actions-row">
                    <div class="comment-emoji" id="comment-emoji">
                        <div class="comment-emoji__hd">
                            <button type="button" class="comment-emoji__tab is-active" data-emoji-tab="stickers"><?php _e('贴纸'); ?></button>
                            <button type="button" class="comment-emoji__tab" data-emoji-tab="emoji"><?php _e('表情'); ?></button>
                            <button type="button" class="comment-emoji__close" data-emoji-close aria-label="<?php _e('收起表情'); ?>">&#10005;</button>
                        </div>
                        <div class="comment-emoji__list" id="comment-emoji-list"></div>
                    </div>

                    <div class="comment-toolbar">
                        <button type="button" class="comment-tool comment-tool-emoji" data-emoji-toggle aria-label="<?php _e('表情'); ?>">
                            <svg class="comment-tool-icon" viewBox="0 0 1024 1024" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M512 92C280.3 92 92 280.3 92 512s188.3 420 420 420 420-188.3 420-420S743.7 92 512 92zm0 768c-192 0-348-156-348-348S320 164 512 164s348 156 348 348-156 348-348 348zm-118-424c-19.9 0-36 16.1-36 36s16.1 36 36 36 36-16.1 36-36-16.1-36-36-36zm236 0c-19.9 0-36 16.1-36 36s16.1 36 36 36 36-16.1 36-36-16.1-36-36-36zM512 710c-79.5 0-149.9-45.1-184.9-111.5-8.5-16.1-28.5-22.3-44.6-13.8-16.1 8.5-22.3 28.5-13.8 44.6C314.2 707 407.3 766 512 766s197.8-59 243.3-136.7c8.5-16.1 2.3-36.1-13.8-44.6-16.1-8.5-36.1-2.3-44.6 13.8C661.9 664.9 591.5 710 512 710z"/></svg>
                            <span class="comment-tool-text"><?php _e('表情'); ?></span>
                        </button>
                        <button type="submit" class="comment-tool comment-submit" aria-label="<?php _e('发送'); ?>">
                            <i class="weui-primary-loading comment-submit__spin" aria-hidden="true"></i>
                            <span class="comment-tool-text"><?php _e('发送'); ?></span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    <?php else: ?>
        <h3 class="section-title"><?php _e('评论已关闭'); ?></h3>
    <?php endif; ?>
    <?php
    $weuiFormHtml = ob_get_clean();
    ?>

    <?php if ($weuiFormPos !== 'bottom') echo $weuiFormHtml; ?>

    <?php if ($comments->have()): ?>
        <h3 class="section-title"><?php $comments->num(_t('暂无评论'), _t('仅有一条评论'), _t('已有 %d 条评论')); ?></h3>

        <?php
        /* 评论平级输出：整个列表只有一个 <ol class="comment-list">。
           这里手动包一层 wrapper，再把 listComments 的 before/after 置空 ——
           因为核心 threadedComments() 会在**每一层子评论组**前后都 echo 一次
           before/after，若把 <ol> 放进去，递归就会产出层层嵌套的 <ol>。
           缩进由 CSS 的 .comment-child 一层 margin 表达（见 style.css）。 */
        ?>
        <div class="comment-list-wrap"><ol class="comment-list">
        <?php
        $comments->listComments([
            'replyWord'  => _t('回复'),
            'avatarSize' => 56,
            'before'     => '',
            'after'      => '',
        ]);
        ?>
        </ol></div>
        <?php weuiCommentsNav($comments); ?>
    <?php endif; ?>

    <?php /* 评论框置于所有评论下方时，放在列表 + 翻页之后 */ ?>
    <?php if ($weuiFormPos === 'bottom') echo $weuiFormHtml; ?>
</div>

<?php if ($this->allow('comment') && !$this->user->hasLogin()): ?>
    <?php /* 评论信息：WeUI 弹窗（https://weui.io/#dialog，参考 ngx-weui dialog 示例） */ ?>
    <div class="weui-mask" id="comment-profile-mask" data-profile-close></div>
    <div class="weui-dialog" id="comment-profile-dialog" role="dialog" aria-modal="true" aria-label="<?php _e('评论信息'); ?>">
        <div class="weui-dialog__hd"><strong class="weui-dialog__title"><?php _e('评论信息'); ?></strong></div>
        <div class="weui-dialog__bd">
            <div class="comment-profile-fields">
                <input type="text" class="comment-input" id="profile-author" placeholder="<?php _e('昵称'); ?>" autocomplete="nickname">
                <input type="email" class="comment-input" id="profile-mail" placeholder="<?php _e('邮箱'); ?>" autocomplete="email">
                <input type="url" class="comment-input" id="profile-url" placeholder="<?php _e('网站（可选）'); ?>" autocomplete="url">
            </div>
        </div>
        <div class="weui-dialog__ft">
            <a href="javascript:;" class="weui-dialog__btn weui-dialog__btn_default" data-profile-close><?php _e('取消'); ?></a>
            <a href="javascript:;" class="weui-dialog__btn weui-dialog__btn_primary" data-profile-confirm><?php _e('确定'); ?></a>
        </div>
    </div>
<?php endif; ?>
