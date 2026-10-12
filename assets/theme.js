/* WeUI 主题交互脚本：PJAX / 图片懒加载 / 灯箱 / 加载更多 / 外链新窗口
   所有初始化都收敛到 initPage()，PJAX 换页后重新执行。 */
(function () {
  'use strict';

  /* ============ 通用工具 ============ */

  // 是否走 PJAX 的站内链接（同源、非新窗口）。
  // 正文 / 评论内容里的**站内**链接也走 PJAX（不再新窗口打开），只有站外链接才新窗口。
  function isInternalLink(a) {
    if (!a || !a.href) return false;
    if (a.target === '_blank') return false;
    if (a.hasAttribute('data-no-pjax')) return false;
    if (a.hasAttribute('download')) return false;
    var raw = a.getAttribute('href') || '';
    if (raw === '#') return false;
    // 纯锚点链接（#comment-1 / #comments 等）交给浏览器原生滚动，不走 PJAX
    if (raw.charAt(0) === '#') return false;
    if (/^(mailto:|tel:|javascript:)/i.test(raw)) return false;
    try {
      var u = new URL(a.href, location.href);
      return u.origin === location.origin;
    } catch (e) {
      return false;
    }
  }

  // 链接处理：只把**站外**链接设成新窗口打开（含正文 / 评论里的站外链接）；
  // 站内链接保持原样，交给 PJAX 无刷新跳转。
  function externalizeLinks(root) {
    (root || document).querySelectorAll('a[href]').forEach(function (a) {
      var href = a.getAttribute('href') || '';
      if (/^(#|javascript:|mailto:|tel:)/i.test(href)) return;
      if (a.hasAttribute('download')) return;
      if (a.hasAttribute('data-no-pjax')) return;

      try {
        var u = new URL(a.href, location.href);
        if (u.origin !== location.origin) {
          a.setAttribute('target', '_blank');
          if ((a.getAttribute('rel') || '').indexOf('noopener') === -1) {
            a.setAttribute('rel', ((a.getAttribute('rel') || '') + ' noopener').trim());
          }
        }
      } catch (e) { /* ignore */ }
    });
  }

  /* ============ 图片懒加载 ============ */
  var lazyObserver = null;
  // 与后端 weuiPlaceholderSrc() 一致的内联「透明」SVG 占位图（懒加载期间显示）。
  // 可见底色由 CSS 的 background-color 决定，随亮/暗主题变化，不会闪白。
  var WF_IMG_PLACEHOLDER = 'data:image/svg+xml;charset=utf-8,'
    + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 300"></svg>');

  // 视频封面：没有 poster 时，用 <video> 抽取一帧当预览图（结果按地址缓存，
  // 避免同一视频多次生成）。成功回调收到 dataURL，失败为 ''。
  //
  // Safari 兼容要点（PC Chrome 能过、Safari 过不去，基本都栽在这几条上）：
  //  1) preload 用 'auto'：Safari 在 'metadata' 下常常一个可解码帧都没有，
  //     seek 过去也是黑屏 / videoWidth=0；必须让它真正缓冲一段。
  //  2) 属性顺序：先 muted / playsinline / setAttribute，再赋 src。Safari 对
  //     顺序敏感，晚设 muted 会被当成「有声音自动播放」而拒绝解码。
  //  3) crossOrigin 只在**跨域**时加：同源视频加它会触发一次没必要（且 Safari
  //     更严格）的 CORS 检查，反而把请求弄挂。跨域则必须加，否则画到 canvas 会
  //     污染、toDataURL 抛异常。
  //  4) seek 拿不到帧就重试：Safari 头几次 seeked 时 videoWidth 可能仍是 0，
  //     直接放弃就永久退回占位；这里最多重试若干次，并配合 canplay。
  //  5) 部分 Safari 版本只有「播过一下」才会解码出帧：短暂 play() 后立刻 pause()。
  var videoPosterCache = {};

  function sameOriginUrl(url) {
    try { return new URL(url, location.href).origin === location.origin; }
    catch (e) { return false; }
  }

  function grabVideoFrame(src, cb) {
    if (Object.prototype.hasOwnProperty.call(videoPosterCache, src)) {
      cb(videoPosterCache[src]);
      return;
    }
    var v = document.createElement('video');
    // 顺序很重要：先属性后 src（Safari）
    v.muted = true;
    v.defaultMuted = true;
    v.setAttribute('muted', '');
    v.playsInline = true;
    v.setAttribute('playsinline', '');
    v.setAttribute('webkit-playsinline', '');
    v.preload = 'auto';
    if (!sameOriginUrl(src)) v.crossOrigin = 'anonymous';

    var done = false;
    var seekTries = 0;
    var finished = false;
    var inDom = false;

    // iOS Safari 对「不在 DOM 里」的 video 常常拒绝缓冲/解码（尤其省电模式、蜂窝网），
    // 所以把临时 video 挂到 DOM 上（1px、不可见、不占位、不响应指针），用完再摘掉。
    function attach() {
      if (inDom) return;
      v.setAttribute('aria-hidden', 'true');
      v.style.cssText = 'position:fixed;left:-9999px;top:0;width:1px;height:1px;opacity:0;pointer-events:none;';
      document.body.appendChild(v);
      inDom = true;
    }

    function cleanup() {
      v.removeEventListener('loadeddata', onReady);
      v.removeEventListener('canplay', onReady);
      v.removeEventListener('seeked', onSeeked);
      v.removeEventListener('error', onError);
      try { v.pause(); } catch (e) { /* ignore */ }
      v.removeAttribute('src');
      try { v.load(); } catch (e) { /* ignore */ }
      if (inDom && v.parentNode) v.parentNode.removeChild(v);
      inDom = false;
    }

    function finish(url) {
      if (done) return;
      done = true;
      finished = true;
      cleanup();
      videoPosterCache[src] = url;
      cb(url);
    }

    function draw() {
      try {
        var w = v.videoWidth, h = v.videoHeight;
        if (!w || !h) return false;
        // 缩到 480 宽以内即可，封面不需要原分辨率
        var scale = Math.min(1, 480 / w);
        var cv = document.createElement('canvas');
        cv.width = Math.round(w * scale);
        cv.height = Math.round(h * scale);
        cv.getContext('2d').drawImage(v, 0, 0, cv.width, cv.height);
        var url = cv.toDataURL('image/jpeg', 0.8);
        finish(url);
        return true;
      } catch (e) {
        // 跨域视频污染画布：toDataURL 会抛异常，退回占位
        finish('');
        return true;
      }
    }

    // 跳到 0.1s（避开纯黑首帧）后抽帧；Safari 上 videoWidth 可能还是 0，重试。
    function seekAndGrab() {
      if (finished) return;
      var t = Math.min(0.1, (v.duration || 1) * 0.1);
      try {
        v.currentTime = t;
      } catch (e) { /* ignore */ }
      // 有些实现 seek 到同一个时间点不再触发 seeked，这里补一次检测
      setTimeout(function () {
        if (finished) return;
        if (draw()) return;
        if (seekTries++ < 3) {
          try { v.currentTime = t + (seekTries * 0.05); } catch (e) { /* ignore */ }
        } else {
          finish('');
        }
      }, 400);
    }

    function onReady() {
      if (finished) return;
      // 尝试播一下再停：部分 Safari 只有解码过才会填 videoWidth
      try {
        var p = v.play();
        if (p && typeof p.then === 'function') {
          p.then(function () { try { v.pause(); } catch (e) {} seekAndGrab(); },
                 function () { seekAndGrab(); });
        } else {
          try { v.pause(); } catch (e) {}
          seekAndGrab();
        }
      } catch (e) {
        seekAndGrab();
      }
    }

    function onSeeked() {
      if (finished) return;
      if (draw()) return;
      if (seekTries++ < 3) {
        setTimeout(seekAndGrab, 150);
      } else {
        finish('');
      }
    }

    function onError() { finish(''); }

    v.addEventListener('loadeddata', onReady, { once: true });
    v.addEventListener('canplay', onReady, { once: true });
    v.addEventListener('seeked', onSeeked);
    v.addEventListener('error', onError, { once: true });
    // 兜底超时：网络太慢/格式不支持时不要一直卡着（Safari 首次解码偏慢，给宽一点）
    setTimeout(function () { finish(''); }, 8000);
    attach();
    v.src = src;
  }

  function revealImg(img) {
    // 视频封面：没有 poster，用抽帧生成预览
    var vsrc = img.getAttribute('data-wf-video-src');
    if (vsrc) {
      grabVideoFrame(vsrc, function (url) {
        if (url) {
          img.setAttribute('data-wf-src', url);
          var s = img.getAttribute('data-wf-src');
          if (s && img.src !== s) img.src = s;
          img.classList.add('is-loaded');
          img.classList.remove('is-generating');
          return;
        }
        // 抽帧失败（Safari 等）：不再退回纯色占位，改用 <video> 元素本身当封面。
        // 浏览器会原生绘制首帧，不需要 canvas，也不受跨域污染影响。
        useNativeVideoThumb(img, vsrc);
      });
      return;
    }
    var src = img.getAttribute('data-wf-src');
    if (src && img.src !== src) img.src = src;
    if (img.complete) {
      img.classList.add('is-loaded');
    } else {
      img.addEventListener('load', function () { img.classList.add('is-loaded'); });
      img.addEventListener('error', function () { img.classList.add('is-loaded'); });
    }
  }

  // 抽帧失败时的兜底：把 <video> 叠在占位图上，让它原生画首帧。
  // 必须放在 <img> **之后**（并给个 higher z-index），否则会被占位图盖住看不见。
  // 只负责画首帧，不自动播放；点格子仍走 Fancybox 打开大图/播视频。
  function useNativeVideoThumb(img, vsrc) {
    if (!img.parentNode || img.dataset.wfNativeFallback === '1') {
      img.classList.add('is-loaded');
      return;
    }
    img.dataset.wfNativeFallback = '1';

    var v = document.createElement('video');
    v.className = 'wf-video-thumb';
    v.muted = true;
    v.defaultMuted = true;
    v.setAttribute('muted', '');
    v.playsInline = true;
    v.setAttribute('playsinline', '');
    v.setAttribute('webkit-playsinline', '');
    v.preload = 'auto';
    v.setAttribute('aria-hidden', 'true');
    v.setAttribute('disablepictureinpicture', '');
    v.tabIndex = -1;
    // 盖在占位图上面（占位图 is-loaded 后不透明，会挡住下面的元素）
    v.style.zIndex = '1';

    var revealed = false;
    function show() {
      if (revealed) return;
      revealed = true;
      v.classList.add('is-loaded');
    }
    // 先 seek 到 0.1s 拿一张非纯黑的首帧（Safari 也支持）
    v.addEventListener('loadeddata', function () {
      try { v.currentTime = Math.min(0.1, (v.duration || 1) * 0.1); } catch (e) { show(); }
    }, { once: true });
    v.addEventListener('seeked', show, { once: true });
    v.addEventListener('canplay', show, { once: true });
    // loadeddata 后若 seek 不可用，也别一直不显示
    v.addEventListener('loadedmetadata', function () {
      setTimeout(function () {
        if (!revealed) { try { v.currentTime = 0.1; } catch (e) { show(); } }
      }, 300);
    }, { once: true });
    v.addEventListener('error', function () { /* 视频也加载失败：保留占位图 */ }, { once: true });
    setTimeout(show, 3000);

    v.src = vsrc;
    // 插到 img 之后，保证盖在占位图之上
    if (img.nextSibling) img.parentNode.insertBefore(v, img.nextSibling);
    else img.parentNode.appendChild(v);
    img.classList.add('is-loaded'); // 占位图作为底，video 淡入盖在上面
  }
  // 视口兜底揭示：把当前已经在（或接近）视口内的待加载 .wf-img 直接揭示。
  // 防止 IntersectionObserver 不回调导致「在屏内却不加载」。
  var viewportRevealTimer = null;
  function scheduleViewportReveal() {
    if (viewportRevealTimer) return;
    viewportRevealTimer = setTimeout(function () {
      viewportRevealTimer = null;
      revealVisibleImgs();
    }, 800);
  }
  function revealVisibleImgs() {
    var vh = window.innerHeight || document.documentElement.clientHeight || 0;
    var pend = document.querySelectorAll('img[data-wf-src].wf-observed:not(.is-loaded)');
    // 待加载图片很多时（例如上千首歌曲封面）跳过逐个 getBoundingClientRect，
    // 交给 IntersectionObserver 处理，避免滚动时大量同步布局计算造成掉帧。
    if (pend.length > 40) return;
    for (var i = 0; i < pend.length; i++) {
      var r = pend[i].getBoundingClientRect();
      if (r.top <= vh + 200 && r.bottom >= -200) revealImg(pend[i]);
    }
  }

  function initLazy() {
    // 懒加载对象统一以「带 data-wf-src 的图片」为准（不再依赖 .wf-img 类，
    // 否则会把 .wf-img 的 width:100% 样式带给表情/头像等小图，导致撑爆）。
    var imgs = document.querySelectorAll('img[data-wf-src]:not(.wf-observed)');
    if (imgs.length) {
      if ('IntersectionObserver' in window) {
        if (!lazyObserver) {
          lazyObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
              if (entry.isIntersecting) {
                revealImg(entry.target);
                lazyObserver.unobserve(entry.target);
              }
            });
          }, { rootMargin: '0px 0px 200px 0px' });
        }
        // 监听一次滚动/改变尺寸做兜底（IO 正常时这些元素早已被揭示，只是空跑）
        if (!initLazy._scrollBound) {
          initLazy._scrollBound = true;
          var onScroll = function () { revealVisibleImgs(); };
          window.addEventListener('scroll', onScroll, { passive: true });
          window.addEventListener('resize', onScroll);
        }
        imgs.forEach(function (img) {
          img.classList.add('wf-observed');
          lazyObserver.observe(img);
        });
        // 兜底：IntersectionObserver 在个别环境/浏览器里可能不回调（或首次
        // 视口内元素迟迟不触发）。这里主动检查一次，把已经在视口内的媒体直接
        // 揭示，避免「明明在屏内却一直不加载」。
        scheduleViewportReveal();
      } else {
        imgs.forEach(function (img) { img.classList.add('wf-observed'); revealImg(img); });
      }
    }
    // 兜底：监听后续插入 / 属性变化的懒加载图（PJAX 换页、脚本动态渲染、
    // 服务器列表图在 boot 之后才就位等），确保它们也能被观察。
    if (!initLazy._bound) {
      initLazy._bound = true;
      initLazy._retry = function () {
        var more = document.querySelectorAll('img[data-wf-src]:not(.wf-observed)');
        if (more.length) initLazy();
      };
      if (typeof MutationObserver !== 'undefined') {
        var mo = new MutationObserver(function (muts) {
          for (var i = 0; i < muts.length; i++) {
            var m = muts[i];
            if (m.addedNodes && m.addedNodes.length) { initLazy._retry(); return; }
            if (m.type === 'attributes' && m.target && m.target.tagName === 'IMG') { initLazy._retry(); return; }
          }
        });
        mo.observe(document.documentElement, { childList: true, subtree: true, attributes: true, attributeFilter: ['data-wf-src'] });
      }
      // 页面资源加载完成后再补一次（此时服务器渲染的图片一定已在 DOM 中）
      window.addEventListener('load', function () { initLazy(); });
    }
    // 正文里没有 poster 的 <video>：用抽帧的结果当封面
    document.querySelectorAll('video:not([poster]):not(.wf-poster-done)').forEach(function (v) {
      var src = v.getAttribute('src');
      if (!src) {
        var s = v.querySelector('source[src]');
        src = s ? s.getAttribute('src') : '';
      }
      if (!src) return;
      v.classList.add('wf-poster-done');
      grabVideoFrame(src, function (url) {
        if (url) v.setAttribute('poster', url);
      });
    });
  }

  /* ============ 评论：Ctrl/Cmd + Enter 提交 ============ */
  function initCommentKeys() {
    if (initCommentKeys._done) return;
    initCommentKeys._done = true;
    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter' || !(e.ctrlKey || e.metaKey)) return;
      var form = e.target && e.target.form;
      if (!form || form.id !== 'comment-form') return;
      e.preventDefault();
      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
      } else if (typeof form.submit === 'function') {
        form.submit();
      }
    });
  }

  /* ============ 媒体灯箱：Fancybox v5 ============
     相册网格（data-fancybox="wf-album"）与正文媒体（data-fancybox="wf-content"）
     都靠 Fancybox 接管点击。这里只负责 bind；分组 / 切换 / 视频 / 说明文字
     全部由 Fancybox 处理（它自己读 data-caption、data-poster、data-type）。
     bind 是幂等的（同一容器 + 同一选择器只注册一次），PJAX 换页后调用无副作用。 */
  function initFancybox() {
    if (!window.Fancybox || typeof window.Fancybox.bind !== 'function') return;
    // 注意：第一个参数必须是 Element（或省略）。传 document 会被 Fancybox 内部
    // 的 `instanceof Element` 校验挡掉、静默不注册——这里用选择器字符串形式，
    // 容器默认落到 document.body。
    window.Fancybox.bind('[data-fancybox]', {
      // 相册与正文媒体都允许左右切换、键盘导航、滚轮缩放
      Toolbar: { display: { left: ['infobar'], middle: [], right: ['slideshow', 'thumbs', 'close'] } },
      Thumbs: { type: 'classic' }
    });
  }

  /* ============ 分页：瀑布流自动无限加载 ============
     滚到列表底部自动抓取下一页并追加，加载态 / 结束态参考 https://weui.io/#loadmore
     （weui-loadmore + weui-loading 转圈、weui-loadmore_line 横线 + 「已无更多内容」）。
     容器里那个 <a> 只是「无 JS」时的兜底，JS 接管后隐藏。 */
  var loadMoreState = null;

  function initLoadMore() {
    // button（点击按钮追加）与 loadmore（滚动到底自动追加）共用同一套逻辑，
    // 只有「何时触发 load()」不同，故用 data-pagination 区分。两者都匹配。
    var box = document.querySelector('.wf-loadmore[data-pagination]');

    // 同一个容器只接管一次；PJAX 换页后容器变了，先断开旧的观察器
    if (loadMoreState) {
      if (loadMoreState.box === box) return;
      if (loadMoreState.observer) loadMoreState.observer.disconnect();
      loadMoreState = null;
    }
    if (!box) return;

    var panel = document.querySelector('.weui-panel__bd');
    if (!panel) return;

    var isAuto = box.getAttribute('data-pagination') === 'loadmore';
    // 按钮模式点它；瀑布流模式的兜底 <a> 同样复用
    var btn = box.querySelector('.wf-loadmore__btn') || box.querySelector('.wf-loadmore__fallback');

    var st = {
      box: box,
      panel: panel,
      isAuto: isAuto,
      nextUrl: box.getAttribute('data-next') || '',
      loading: false,
      observer: null
    };
    loadMoreState = st;

    // 标记 JS 已接管：隐藏兜底按钮，之后显隐交给 is-loading / is-end
    box.classList.add('wf-loadmore--js');

    function showEnd() {
      box.classList.remove('is-loading');
      box.classList.add('is-end');
      if (st.observer) st.observer.disconnect();
    }

    function load() {
      if (loadMoreState !== st || st.loading) return;
      if (!st.nextUrl) { showEnd(); return; }

      // 按钮模式：加载中禁用按钮，避免连点重复抓取
      if (!st.isAuto && btn) btn.classList.add('is-loading');

      st.loading = true;
      box.classList.add('is-loading');

      fetch(st.nextUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.text(); })
        .then(function (html) {
          if (loadMoreState !== st) return; // 期间已翻到别的页面，丢弃结果
          var doc = new DOMParser().parseFromString(html, 'text/html');

          var newPanel = doc.querySelector('.weui-panel__bd');
          if (newPanel) {
            Array.prototype.slice.call(newPanel.children).forEach(function (node) {
              st.panel.appendChild(document.importNode(node, true));
            });
          }

          var newBox = doc.querySelector('.wf-loadmore[data-pagination]');
          st.nextUrl = newBox ? (newBox.getAttribute('data-next') || '') : '';
          box.setAttribute('data-next', st.nextUrl);
        })
        .catch(function () { /* 失败静默：容器仍在，可继续重试 */ })
        .then(function () {
          if (loadMoreState !== st) return;
          st.loading = false;
          box.classList.remove('is-loading');
          if (!st.isAuto && btn) btn.classList.remove('is-loading');
          if (!st.nextUrl) { showEnd(); return; }
          // 新追加的内容也需要重新绑定（时间、点赞等）
          runInit();
          // 追加后若仍不足一屏，观察器不会再次触发，主动补一次
          checkViewport();
        });
    }

    // 容器是否已进入（或接近）视口 —— 用于「页面太短、观察器不再触发」的补加载
    function checkViewport() {
      if (loadMoreState !== st || st.loading || !st.nextUrl) return;
      if (!st.isAuto) return;
      var rect = box.getBoundingClientRect();
      var vh = window.innerHeight || document.documentElement.clientHeight || 0;
      if (rect.top <= vh + 400) load();
    }

    if (!st.nextUrl) { showEnd(); return; }

    if (!st.isAuto) {
      // 按钮模式：点击加载更多
      if (btn) btn.addEventListener('click', function (e) { e.preventDefault(); load(); });
      return;
    }

    if ('IntersectionObserver' in window) {
      // 提前 400px 预取，滚动时几乎无等待感
      st.observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) load();
        });
      }, { rootMargin: '0px 0px 400px 0px' });
      st.observer.observe(box);
      checkViewport();
    } else {
      // 不支持 IntersectionObserver：退回点击加载
      if (btn) {
        btn.addEventListener('click', function (e) { e.preventDefault(); load(); });
      }
    }
  }

  /* ============ 统计：无刷新时补发一次 pageview ============
     统计脚本只在整页加载时执行一次（避免重复加载导致重复计数），
     这里在 PJAX 换页后调用各平台的标准接口补发浏览，避免漏统计。 */
  function wfTrackPageview(url) {
    var path = url || location.href;
    try {
      // 百度统计
      if (window._hmt && typeof window._hmt.push === 'function') {
        window._hmt.push(['_trackPageview', path]);
      }
      // Google Analytics 4 (gtag)
      if (typeof window.gtag === 'function') {
        window.gtag('event', 'page_view', { page_path: path, page_location: path });
      }
      // 通用 analytics.js
      if (typeof window.ga === 'function') {
        window.ga('send', 'pageview', path);
      }
      // Matomo / Piwik
      if (window._paq && typeof window._paq.push === 'function') {
        window._paq.push(['setCustomUrl', path]);
        window._paq.push(['trackPageView']);
      }
      // 友盟 / CNZZ 等：提供全局钩子，方便自行接入
      if (typeof window.wfOnPjaxPageview === 'function') {
        window.wfOnPjaxPageview(path);
      }
    } catch (e) { /* 统计失败不影响页面 */ }
  }

  /* ============ 顶部提示条（WeUI toptips） ============
     错误提示统一从页面顶部弹出，参考 https://cipchk.github.io/ngx-weui/#/example/toptips。
     容器 #wf-toptips 由 footer.php 输出（在 .page 之外，PJAX 不会替换）。 */
  var wfToptipsTimer = null;
  function wfToptips(msg, isError) {
    var el = document.getElementById('wf-toptips');
    if (!el) return;
    el.textContent = String(msg == null ? '' : msg);
    el.classList.toggle('weui-toptips_warn', isError !== false);
    el.hidden = false;
    // 重新触发一次入场动画
    el.classList.remove('wf-toptips-in');
    void el.offsetWidth;
    el.classList.add('wf-toptips-in');
    if (wfToptipsTimer) clearTimeout(wfToptipsTimer);
    wfToptipsTimer = setTimeout(function () {
      el.classList.remove('wf-toptips-in');
      el.hidden = true;
    }, 3000);
  }

  /* ============ PJAX ============ */
  var PJAX_SELECTOR = '.page';
  function initPjax() {
    if (initPjax._done) return;
    initPjax._done = true;

    document.addEventListener('click', function (e) {
      if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      var a = e.target.closest && e.target.closest('a');
      if (!a) return;
      if (a.target === '_blank') return;
      if (!isInternalLink(a)) return;

      var url = new URL(a.href, location.href);
      // 只对 HTML 页面做 PJAX（跳过 feed / 附件）
      if (/\.(xml|json|rss|atom|zip|png|jpe?g|gif|webp|pdf)$/i.test(url.pathname)) return;

      e.preventDefault();
      navigate(url.href);
    });

    // 头部搜索表单也走无刷新
    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (!form || !form.classList || !form.classList.contains('weui-search-bar')) return;
      var input = form.querySelector('input[name=s]');
      var kw = input ? String(input.value || '').trim() : '';
      if (!kw) {
        e.preventDefault();
        if (input) { try { input.focus(); } catch (err) {} }
        return;
      }
      e.preventDefault();
      // Typecho 的搜索路由是 /search/<关键词>/（而不是 ?s=）。form action 是站点
      // index，可能是 https://site、https://site/、https://site/index.php 或子目录……
      // 先去掉结尾斜杠，再丢掉结尾的 index.php，最后去掉可能剩下的斜杠，
      // 保证无论 action 怎么写都能拼出正确的 /search/<kw>/。
      var action = form.getAttribute('action') || (location.origin + '/');
      action = action.replace(/\/+$/, '').replace(/\/index\.php$/i, '').replace(/\/+$/, '');
      if (!action) action = location.origin;
      navigate(action + '/search/' + encodeURIComponent(kw) + '/');
    });

    window.addEventListener('popstate', function () {
      navigate(location.href, true);
    });
  }

  var pjaxing = false;
  function navigate(url, isPop) {
    if (pjaxing) return;
    pjaxing = true;

    var page = document.querySelector(PJAX_SELECTOR);
    if (page) page.classList.add('wf-pjax-leave');

    // 目标 URL 是否带锚点：带锚点时滚动到锚点，不带锚点（或锚点不存在）时回到顶部。
    // 例外：评论分页链接带 #comments 锚点，切换后要停在评论区而不是跳到页面顶部。
    var hash = '';
    try { hash = new URL(url, location.href).hash.replace(/^#/, ''); } catch (e) { hash = ''; }

    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.text(); })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var newPage = doc.querySelector(PJAX_SELECTOR);
        if (!newPage) { location.href = url; return; }

        // 同步标题
        var newTitle = doc.querySelector('title');
        if (newTitle) document.title = newTitle.textContent;

        if (page) {
          page.replaceWith(document.importNode(newPage, true));
        } else {
          document.body.appendChild(document.importNode(newPage, true));
        }

        if (!isPop) history.pushState(null, '', url);

        // 滚动策略：带锚点 -> 滚到锚点；否则一律回顶部。
        var target = hash ? document.getElementById(decodeURIComponent(hash)) : null;
        if (target) {
          // 评论区锚点：留一点可视边距，别让评论贴到屏幕最上沿
          var top = target.getBoundingClientRect().top + (window.pageYOffset || window.scrollY || 0);
          var pad = /^comments$|^comment-\d+$/.test(hash) ? 12 : 0;
          window.scrollTo(0, Math.max(0, top - pad));
        } else {
          window.scrollTo(0, 0);
        }
        // 无刷新跳转后补发一次浏览统计（首次整页加载由统计脚本自己记，不在这里重复）
        wfTrackPageview(url);
        runInit();
      })
      .catch(function () { location.href = url; })
      .then(function () { pjaxing = false; });
  }

  /* ============ 代码高亮 + 复制按钮 ============ */
  function initHighlight() {
    if (typeof window.hljs === 'undefined') return;
    document.querySelectorAll('.weui-article pre code, .comment-content pre code').forEach(function (block) {
      if (block.dataset.wfHl === '1') return;
      block.dataset.wfHl = '1';
      try { window.hljs.highlightElement(block); } catch (e) { /* ignore */ }

      var pre = block.parentNode;
      if (pre && !pre.querySelector('.wf-copy-btn')) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'wf-copy-btn';
        btn.textContent = '复制';
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          copyText(block.innerText, btn);
        });
        pre.appendChild(btn);
      }
    });
  }

  function copyText(text, btn) {
    function done() {
      if (!btn) return;
      var old = btn.textContent;
      btn.textContent = '已复制';
      setTimeout(function () { btn.textContent = old; }, 1500);
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done, function () { fallbackCopy(text, done); });
    } else {
      fallbackCopy(text, done);
    }
  }

  function fallbackCopy(text, done) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); } catch (e) { /* ignore */ }
    document.body.removeChild(ta);
    done();
  }

  /* ============ 评论：用户信息（WeUI 半屏弹窗）+ 本地记忆 ============ */
  var PROFILE_KEY = 'wf_comment_profile';

  function readProfile() {
    try {
      return JSON.parse(localStorage.getItem(PROFILE_KEY) || '{}') || {};
    } catch (e) {
      return {};
    }
  }
  function saveProfile(data) {
    try { localStorage.setItem(PROFILE_KEY, JSON.stringify(data)); } catch (e) { /* ignore */ }
  }

  function openProfileDialog() {
    var dlg = document.getElementById('comment-profile-dialog');
    var mask = document.getElementById('comment-profile-mask');
    if (!dlg || !mask) return;

    // 打开弹窗时，用当前生效的值（隐藏字段 / 本地记忆）填进输入框。
    // 每次都重新填，避免上次的残留或未同步。
    var form = document.getElementById('comment-form');
    var fields = {
      author: document.getElementById('profile-author'),
      mail: document.getElementById('profile-mail'),
      url: document.getElementById('profile-url')
    };
    if (form) {
      var aEl = form.querySelector('#author');
      var mEl = form.querySelector('#mail');
      var uEl = form.querySelector('#url');
      if (fields.author) fields.author.value = aEl ? aEl.value : '';
      if (fields.mail) fields.mail.value = mEl ? mEl.value : '';
      if (fields.url) fields.url.value = uEl ? uEl.value : '';
    }

    mask.classList.add('is-open');
    dlg.classList.add('is-open');
    setTimeout(function () {
      if (fields.author) fields.author.focus();
    }, 300);
  }

  function closeProfileDialog() {
    var dlg = document.getElementById('comment-profile-dialog');
    var mask = document.getElementById('comment-profile-mask');
    if (!dlg || !mask) return;
    mask.classList.remove('is-open');
    dlg.classList.remove('is-open');
  }

  // 更新「游客评论 / 昵称」显示，并同步 Gravatar 头像
  /**
   * 更新身份行头像。
   * @param {string} [mailOverride] 传入时用这个邮箱（用于弹窗里实时输入的场景）
   */
  function updateIdentityAvatar(mailOverride) {
    var form = document.getElementById('comment-form');
    if (!form) return;
    var img = form.querySelector('[data-identity-avatar]');
    if (!img) return;
    var addr = mailOverride;
    if (typeof addr !== 'string') {
      var mail = form.querySelector('#mail');
      addr = mail && mail.value ? String(mail.value).trim().toLowerCase() : '';
    } else {
      addr = String(addr).trim().toLowerCase();
    }
    // data-avatar-base 是后端 weuiGravatarDir() 归一化后的「目录」，
    // 已经带 /avatar（如 https://cn.cravatar.com/avatar），这里直接接 /<hash> 即可。
    var media = (img.getAttribute('data-avatar-base') || 'https://www.gravatar.com/avatar').replace(/\/+$/, '');
    var hash = md5(addr);
    img.src = media + '/' + hash + '?s=48&d=mp';
  }

  function syncIdentity() {
    var form = document.getElementById('comment-form');
    if (!form) return;
    var label = form.querySelector('[data-comment-identity]');
    var author = form.querySelector('#author');
    var name = author && author.value ? author.value.trim() : '';
    if (label) label.textContent = name || '游客评论';
    updateIdentityAvatar();
  }

  function initCommentProfile() {
    var form = document.getElementById('comment-form');
    if (!form || form.dataset.wfProfile === '1') return;
    form.dataset.wfProfile = '1';

    var author = form.querySelector('#author');
    var mail = form.querySelector('#mail');
    var url = form.querySelector('#url');
    // 登录用户没有这些隐藏字段：无需信息弹窗
    if (!author) return;

    // 本地保存的信息优先于服务端 cookie：cookie 只有「提交过评论」后才会由
    // Typecho 写入/更新，所以用户刚在弹窗里改好的信息，若只按 cookie 预填就会
    // 被旧值覆盖，表现为「保存后不生效，非要评论一次才记住」。
    // 这里改成：只要本地有存过，就用本地值覆盖隐藏字段（本地是用户最近一次显式填写）。
    var saved = readProfile();
    if (saved.author) author.value = saved.author;
    if (mail && saved.mail) mail.value = saved.mail;
    if (url && saved.url) url.value = saved.url;
    syncIdentity();

    // 铅笔按钮 → 打开弹窗
    form.querySelectorAll('[data-profile-open]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        openProfileDialog();
      });
    });

    var confirm = document.querySelector('#comment-profile-dialog [data-profile-confirm]');
    var dialog = document.getElementById('comment-profile-dialog');
    var mask = document.getElementById('comment-profile-mask');

    if (dialog && dialog.dataset.wfBound !== '1') {
      dialog.dataset.wfBound = '1';
      dialog.querySelectorAll('[data-profile-close]').forEach(function (btn) {
        btn.addEventListener('click', function (e) { e.preventDefault(); closeProfileDialog(); });
      });
      if (mask) mask.addEventListener('click', function () { closeProfileDialog(); });
      if (confirm) {
        confirm.addEventListener('click', function (e) {
          e.preventDefault();
          applyProfile();
        });
      }
      // 弹窗输入框里回车 = 确定
      dialog.querySelectorAll('.comment-input').forEach(function (input) {
        input.addEventListener('keydown', function (e) {
          if (e.key === 'Enter') { e.preventDefault(); applyProfile(); }
        });
      });
      // 邮箱边输入边换头像（用后台配置的 CDN 前缀拼接）
      var liveMail = dialog.querySelector('#profile-mail');
      if (liveMail) {
        liveMail.addEventListener('input', function () {
          updateIdentityAvatar(liveMail.value);
        });
      }
      // 昵称边输入边更新身份行文字
      var liveAuthor = dialog.querySelector('#profile-author');
      if (liveAuthor) {
        liveAuthor.addEventListener('input', function () {
          var label = document.querySelector('#comment-form [data-comment-identity]');
          if (label) label.textContent = liveAuthor.value.trim() || '游客评论';
        });
      }
    }

    function applyProfile() {
      var f = document.getElementById('comment-form');
      if (!f) return;
      var a = f.querySelector('#author');
      var m = f.querySelector('#mail');
      var u = f.querySelector('#url');
      var pa = document.getElementById('profile-author');
      var pm = document.getElementById('profile-mail');
      var pu = document.getElementById('profile-url');

      var name = pa ? pa.value.trim() : '';
      var mailVal = pm ? pm.value.trim() : '';
      if (!name) { if (pa) pa.focus(); return; }
      if (m && m.dataset.required === '1' && !mailVal) { if (pm) pm.focus(); return; }

      if (a) a.value = name;
      if (m) m.value = mailVal;
      if (u) u.value = pu ? pu.value.trim() : '';

      saveProfile({ author: name, mail: mailVal, url: u ? u.value : '' });
      // 邮箱变了头像也要跟着变
      updateIdentityAvatar();
      syncIdentity();

      var hint = f.querySelector('[data-profile-hint]');
      if (hint) { hint.hidden = true; hint.textContent = ''; }

      closeProfileDialog();

      // 信息是「点发送时才发现缺失」→ 填完自动继续提交，用户不用再点一次
      if (pendingSubmit && document.contains(pendingSubmit)) {
        var f2 = pendingSubmit;
        pendingSubmit = null;
        if (typeof f2.requestSubmit === 'function') f2.requestSubmit();
        else f2.submit();
      }
    }
  }

  // 待补信息后要继续提交的表单
  var pendingSubmit = null;

  // 读取某个容器上的评论总数（「已有 N 条评论」里的 N）
  function totalOf(root) {
    if (!root) return -1;
    var h = root.querySelector('.section-title') || root.querySelector('h3');
    if (!h) return -1;
    var m = (h.textContent || '').match(/(\d+)/);
    return m ? parseInt(m[1], 10) : -1;
  }

  // 评论区翻页控件：分页条 / 加载更多 / 瀑布流容器。三者互斥，最多存在一个。
  var COMMENT_NAV_SEL = '.weui-pagination, .wf-loadmore[data-comment-pagination]';

  // 把「返回页面」里的翻页控件同步到当前 DOM。
  // 翻页控件在 .comment-list-wrap 之外，提交后只换列表是不会更新它的：
  //  - 原本没有翻页控件（单页）→ 提交后变多页：需要**新增**；
  //  - 已有 → 需要用新的（页数 / data-next 可能变了）；
  //  - 返回页没有（走到最后一页 / 只剩一页）→ 需要**移除**。
  // 另外瀑布流/按钮模式下，返回的容器是新建的，要重新 runInit() 才能重新绑定观察器。
  function syncCommentNav(oldComments, newComments, hidden) {
    if (!oldComments || !newComments) return;
    var oldNav = oldComments.querySelector(COMMENT_NAV_SEL);
    var newNav = newComments.querySelector(COMMENT_NAV_SEL);
    if (!newNav) {
      // 返回页没有翻页控件：移除旧的，避免出现「明明没有更多了却还留着按钮」。
      if (oldNav && oldNav.parentNode) oldNav.parentNode.removeChild(oldNav);
      return;
    }
    var fresh = document.importNode(newNav, true);
    if (oldNav && oldNav.parentNode) {
      oldNav.replaceWith(fresh);
    } else {
      // 原来没有：插在评论列表之后（若列表缺失则插到 #comments 末尾）。
      var anchor = oldComments.querySelector('.comment-list-wrap');
      if (anchor && anchor.parentNode) anchor.parentNode.insertBefore(fresh, anchor.nextSibling);
      else oldComments.appendChild(fresh);
    }
  }

  // Typecho 对「待审核」的评论会写这个 cookie，用来识别「已提交但未显示」。
  // 注意：Typecho 的 cookie 名前面会带一段站点前缀（形如 ef301f25…__typecho_unapproved_comment），
  // 所以不能要求名字紧跟 "; "，否则永远匹配不到 —— 之前那个正则就是这样失效的。
  function pendingCookie() {
    var m = document.cookie.match(/(?:^|;\s*)[^;=\s]*__typecho_unapproved_comment=([^;]+)/);
    return m ? m[1] : '';
  }

  /* 注：「待审核」标记由服务端输出（functions.php 的 threadedComments，
     依据评论真实 status 判断），不在这里用 cookie 猜 —— 那样在评论通过审核后
     还会残留标记，直到浏览器会话结束。 */

  // 列出所有评论节点的 id（comment-123）
  function commentIds(root) {
    var ids = [];
    (root || document).querySelectorAll('#comments li[id^="comment-"]').forEach(function (li) {
      ids.push(li.id);
    });
    return ids;
  }

  function coidOf(li) {
    var m = li && /^comment-(\d+)$/.exec(li.id || '');
    return m ? parseInt(m[1], 10) : 0;
  }
  function commentParentOf(li) {
    var p = parseInt((li && li.getAttribute('data-parent')) || '0', 10);
    return isNaN(p) ? 0 : p;
  }
  function commentDepth(li) {
    var d = parseInt((li && li.getAttribute('data-depth')) || '0', 10);
    return isNaN(d) ? 0 : d;
  }
  function stripHash(u) {
    return String(u || '').split('#')[0];
  }

  /* ---------- 评论分页「来源页」标记 ----------
     Typecho 提交成功后 302 的目标页码是**估算**出来的（统计顶层评论时会把
     「父评论不在统计范围内」的回复也算成顶层），所以它给的页码可能偏大 ——
     新评论不一定出现在那一页上。稳妥的做法是不依赖这个跳转页，而是记住每条
     评论是从哪一页来的，提交后只把「被回复评论所属的那一页」重新拉一遍。 */
  var commentPageState = { host: null, current: '' };

  function tagCommentPages() {
    var host = document.getElementById('comments');
    if (!host) {
      commentPageState = { host: null, current: '' };
      return null;
    }
    if (commentPageState.host !== host) {
      commentPageState = { host: host, current: stripHash(location.href) };
    }
    var cur = commentPageState.current;
    host.querySelectorAll('li[id^="comment-"]').forEach(function (li) {
      if (!li.getAttribute('data-wf-page')) li.setAttribute('data-wf-page', cur);
    });
    return commentPageState;
  }

  // 顺着 data-parent 往上找到顶层祖先：同一条根评论下的回复都渲染在根评论所在的分页里
  function commentRootOf(li) {
    var guard = 0;
    while (li && guard++ < 50) {
      var pid = commentParentOf(li);
      if (!pid) return li;
      var parent = document.getElementById('comment-' + pid);
      if (!parent) return li;
      li = parent;
    }
    return li;
  }

  function commentPageOf(li) {
    var root = commentRootOf(li);
    return (root && root.getAttribute('data-wf-page')) || commentPageState.current || stripHash(location.href);
  }

  // 当前列表里出现过的所有来源分页（按首次出现顺序）
  function loadedCommentPages() {
    tagCommentPages();
    var urls = [];
    document.querySelectorAll('#comments li[id^="comment-"]').forEach(function (li) {
      var p = li.getAttribute('data-wf-page');
      if (p && urls.indexOf(p) === -1) urls.push(p);
    });
    if (!urls.length && commentPageState.current) urls.push(commentPageState.current);
    return urls;
  }

  /* ---------- 补齐评论区骨架 ----------
     文章还没有任何评论时，服务端渲染的 #comments 里**只有表单**：
     既没有「已有 N 条评论」标题，也没有 .comment-list-wrap 容器。
     这时提交后的新评论就没地方落 —— 表现为「评论写进库了，页面上却看不到，
     还被误报成『等待审核』」（有评论的文章不会出现，因为容器本来就在）。
     这里从「提交返回的页面」把标题和列表容器搬过来补齐，插入位置跟随
     「评论框位置」设置（top = 表单在列表上方，bottom = 表单在列表下方）。 */
  function ensureCommentSkeleton(newComments) {
    var host = document.getElementById('comments');
    if (!host || !newComments) return null;

    var listBottom = host.classList.contains('comments--form-bottom');
    var pending = [];   // 待插入节点，按「标题 → 列表」的先后顺序

    // ① 评论计数标题
    var hostTitle = host.querySelector(':scope > .section-title');
    var srcTitle = newComments.querySelector('#comments > .section-title');
    if (srcTitle) {
      if (hostTitle) {
        hostTitle.innerHTML = srcTitle.innerHTML;
      } else {
        pending.push(document.importNode(srcTitle, true));
      }
    }

    // ② 评论列表容器（含 <ol class="comment-list">）
    var wrap = host.querySelector('.comment-list-wrap');
    if (!wrap) {
      var srcWrap = newComments.querySelector('#comments .comment-list-wrap');
      if (srcWrap) {
        wrap = document.importNode(srcWrap, true);
        pending.push(wrap);
      }
    }

    if (pending.length) {
      var respond = host.querySelector(':scope > .respond') || host.querySelector('.respond');
      if (listBottom) {
        // 表单在所有评论之后 → 补进来的内容排在表单之前
        if (respond && respond.parentNode === host) {
          pending.forEach(function (n) { host.insertBefore(n, respond); });
        } else {
          pending.forEach(function (n) { host.appendChild(n); });
        }
      } else if (respond && respond.parentNode === host) {
        // 表单在评论之前 → 依次插到表单后面，保持「标题 → 列表」的顺序
        var after = respond;
        pending.forEach(function (n) {
          after.parentNode.insertBefore(n, after.nextSibling);
          after = n;
        });
      } else {
        pending.forEach(function (n) { host.appendChild(n); });
      }
    }

    return wrap ? (wrap.querySelector('.comment-list') || wrap) : null;
  }

  /* ---------- 合并评论列表（绝不删除本地已有的评论） ----------
     「加载更多 / 瀑布流」模式下列表里同时有第 1…k 页的评论，而服务端返回的
     只是其中某一页。整体替换会把已加载的评论全部冲掉，所以改成合并：
       · 两边都有的 id：原位替换（内容、状态可能变了）
       · 只在返回页里的 id：按它在返回页中的相邻关系插到正确位置
       · 只在本地有的 id：保留
     返回本次真正新增的 <li> 列表。 */
  function mergeCommentList(oldWrap, newWrap, srcPage) {
    var added = [];
    if (!oldWrap || !newWrap) return added;

    var page = srcPage || commentPageState.current || stripHash(location.href);
    var target = oldWrap.querySelector('.comment-list') || oldWrap;
    var incoming = newWrap.querySelector('.comment-list') || newWrap;
    var nodes = Array.prototype.slice.call(incoming.children).filter(function (n) {
      return n.tagName === 'LI' && /^comment-\d+$/.test(n.id || '');
    });
    if (!nodes.length) return added;

    // 只认「当前列表里」的节点，避免误用 #comments 之外的偶然同 id 元素
    function localOf(id) {
      var el = id ? document.getElementById(id) : null;
      return el && el.closest && el.closest('.comment-list') ? el : null;
    }

    nodes.forEach(function (node, idx) {
      var local = localOf(node.id);

      if (local) {
        // 原位替换：位置与缩进都不动，只刷新内容
        var same = document.importNode(node, true);
        var kept = local.getAttribute('data-wf-page');
        same.setAttribute('data-wf-page', kept || page);
        local.replaceWith(same);
        return;
      }

      // 新增节点：优先挂在返回页里它前面最近的、本地已存在的兄弟之后
      var anchor = null;
      for (var i = idx - 1; i >= 0; i--) {
        var prev = localOf(nodes[i].id);
        if (prev) { anchor = prev; break; }
      }

      var fresh = document.importNode(node, true);
      fresh.setAttribute('data-wf-page', page);

      if (anchor) {
        // 落到锚点「整棵子树」之后，这样它自己的回复仍排在它下面
        var after = anchor;
        var d = commentDepth(anchor);
        while (after.nextElementSibling && commentDepth(after.nextElementSibling) > d) {
          after = after.nextElementSibling;
        }
        after.parentNode.insertBefore(fresh, after.nextSibling);
      } else {
        // 前面没有锚点：找后面第一个本地存在的评论，插到它前面
        var nextLocal = null;
        for (var j = idx + 1; j < nodes.length; j++) {
          var nx = localOf(nodes[j].id);
          if (nx) { nextLocal = nx; break; }
        }
        if (nextLocal && nextLocal.parentNode === target) {
          target.insertBefore(fresh, nextLocal);
        } else {
          target.appendChild(fresh);
        }
      }
      added.push(fresh);
    });

    return added;
  }

  /* 重新拉取若干个评论分页。
     opts.replace = true 时用「按顺序重建」代替合并（新增顶层评论会让各页内容整体位移）；
     否则逐页合并（只增不删）。done(lastDoc) 里带回最后一页的文档，便于同步翻页控件。
     只要有一页没取到，就退回「只合并」——绝不让列表因为一次失败而丢评论。 */
  function refreshCommentPages(urls, opts, done) {
    var list = [];
    (urls || []).forEach(function (u) {
      if (u && list.indexOf(u) === -1) list.push(u);
    });
    var replace = !!(opts && opts.replace);
    var frames = [];
    var lastDoc = null;
    var i = 0;
    if (!list.length) { done(null); return; }

    function step() {
      if (i >= list.length) {
        if (!frames.length) { done(lastDoc); return; }
        // 文章原本没有评论时，本地没有列表容器 —— 先从返回页把骨架搬过来
        ensureCommentSkeleton(lastDoc);
        var oldWrap = document.querySelector('#comments .comment-list-wrap');
        if (!oldWrap) { done(lastDoc); return; }
        if (replace && frames.length === list.length) {
          rebuildCommentList(oldWrap, frames);
        } else {
          frames.forEach(function (frame) {
            mergeCommentList(oldWrap, frame.wrap, stripHash(frame.url));
          });
        }
        done(lastDoc);
        return;
      }
      var url = list[i++];
      fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.text(); })
        .then(function (html) {
          var doc = new DOMParser().parseFromString(html, 'text/html');
          lastDoc = doc;
          var newWrap = doc.querySelector('#comments .comment-list-wrap');
          if (newWrap) frames.push({ url: url, wrap: newWrap });
        })
        .catch(function () { /* 单页失败不致命，继续 */ })
        .then(step);
    }
    step();
  }

  // 用若干页的列表按顺序重建本地列表，并给每个节点标上它的来源页
  function rebuildCommentList(oldWrap, frames) {
    if (typeof oldWrap === 'object' && oldWrap && oldWrap.length !== undefined && !oldWrap.nodeType) {
      // 兼容旧签名 rebuildCommentList(frames)
      frames = oldWrap;
      oldWrap = document.querySelector('#comments .comment-list-wrap');
    }
    if (!oldWrap) return;
    var target = oldWrap.querySelector('.comment-list') || oldWrap;
    var merged = document.createDocumentFragment();
    var seen = {};
    frames.forEach(function (frame) {
      var src = frame.wrap.querySelector('.comment-list') || frame.wrap;
      var page = stripHash(frame.url);
      Array.prototype.slice.call(src.children).forEach(function (node) {
        if (node.tagName !== 'LI' || !/^comment-\d+$/.test(node.id || '')) return;
        if (seen[node.id]) return;
        seen[node.id] = 1;
        var copy = document.importNode(node, true);
        copy.setAttribute('data-wf-page', page);
        merged.appendChild(copy);
      });
    });
    target.innerHTML = '';
    target.appendChild(merged);
  }

  // 用「某一页」（通常是重建时的最后一页）的翻页控件同步当前列表
  function syncCommentNavFromDoc(doc) {
    var oldComments = document.querySelector('#comments');
    var newComments = doc ? doc.querySelector('#comments') : null;
    if (!oldComments || !newComments) return;
    syncCommentNav(oldComments, newComments, false);
  }

  // 计算 Gravatar 需要的 md5（纯前端实现，避免为了头像再引入一个库）
  function md5(input) {
    function add32(a, b) { return (a + b) & 0xFFFFFFFF; }
    function cmn(q, a, b, x, s1, t) { a = add32(add32(a, q), add32(x, t)); return add32((a << s1) | (a >>> (32 - s1)), b); }
    function ff(a, b, c, d, x, s1, t) { return cmn((b & c) | ((~b) & d), a, b, x, s1, t); }
    function gg(a, b, c, d, x, s1, t) { return cmn((b & d) | (c & (~d)), a, b, x, s1, t); }
    function hh(a, b, c, d, x, s1, t) { return cmn(b ^ c ^ d, a, b, x, s1, t); }
    function ii(a, b, c, d, x, s1, t) { return cmn(c ^ (b | (~d)), a, b, x, s1, t); }

    function md5cycle(x, k) {
      var a = x[0], b = x[1], c = x[2], d = x[3];
      a = ff(a, b, c, d, k[0], 7, -680876936);
      d = ff(d, a, b, c, k[1], 12, -389564586);
      c = ff(c, d, a, b, k[2], 17, 606105819);
      b = ff(b, c, d, a, k[3], 22, -1044525330);
      a = ff(a, b, c, d, k[4], 7, -176418897);
      d = ff(d, a, b, c, k[5], 12, 1200080426);
      c = ff(c, d, a, b, k[6], 17, -1473231341);
      b = ff(b, c, d, a, k[7], 22, -45705983);
      a = ff(a, b, c, d, k[8], 7, 1770035416);
      d = ff(d, a, b, c, k[9], 12, -1958414417);
      c = ff(c, d, a, b, k[10], 17, -42063);
      b = ff(b, c, d, a, k[11], 22, -1990404162);
      a = ff(a, b, c, d, k[12], 7, 1804603682);
      d = ff(d, a, b, c, k[13], 12, -40341101);
      c = ff(c, d, a, b, k[14], 17, -1502002290);
      b = ff(b, c, d, a, k[15], 22, 1236535329);
      a = gg(a, b, c, d, k[1], 5, -165796510);
      d = gg(d, a, b, c, k[6], 9, -1069501632);
      c = gg(c, d, a, b, k[11], 14, 643717713);
      b = gg(b, c, d, a, k[0], 20, -373897302);
      a = gg(a, b, c, d, k[5], 5, -701558691);
      d = gg(d, a, b, c, k[10], 9, 38016083);
      c = gg(c, d, a, b, k[15], 14, -660478335);
      b = gg(b, c, d, a, k[4], 20, -405537848);
      a = gg(a, b, c, d, k[9], 5, 568446438);
      d = gg(d, a, b, c, k[14], 9, -1019803690);
      c = gg(c, d, a, b, k[3], 14, -187363961);
      b = gg(b, c, d, a, k[8], 20, 1163531501);
      a = gg(a, b, c, d, k[13], 5, -1444681467);
      d = gg(d, a, b, c, k[2], 9, -51403784);
      c = gg(c, d, a, b, k[7], 14, 1735328473);
      b = gg(b, c, d, a, k[12], 20, -1926607734);
      a = hh(a, b, c, d, k[5], 4, -378558);
      d = hh(d, a, b, c, k[8], 11, -2022574463);
      c = hh(c, d, a, b, k[11], 16, 1839030562);
      b = hh(b, c, d, a, k[14], 23, -35309556);
      a = hh(a, b, c, d, k[1], 4, -1530992060);
      d = hh(d, a, b, c, k[4], 11, 1272893353);
      c = hh(c, d, a, b, k[7], 16, -155497632);
      b = hh(b, c, d, a, k[10], 23, -1094730640);
      a = hh(a, b, c, d, k[13], 4, 681279174);
      d = hh(d, a, b, c, k[0], 11, -358537222);
      c = hh(c, d, a, b, k[3], 16, -722521979);
      b = hh(b, c, d, a, k[6], 23, 76029189);
      a = hh(a, b, c, d, k[9], 4, -640364487);
      d = hh(d, a, b, c, k[12], 11, -421815835);
      c = hh(c, d, a, b, k[15], 16, 530742520);
      b = hh(b, c, d, a, k[2], 23, -995338651);
      a = ii(a, b, c, d, k[0], 6, -198630844);
      d = ii(d, a, b, c, k[7], 10, 1126891415);
      c = ii(c, d, a, b, k[14], 15, -1416354905);
      b = ii(b, c, d, a, k[5], 21, -57434055);
      a = ii(a, b, c, d, k[12], 6, 1700485571);
      d = ii(d, a, b, c, k[3], 10, -1894986606);
      c = ii(c, d, a, b, k[10], 15, -1051523);
      b = ii(b, c, d, a, k[1], 21, -2054922799);
      a = ii(a, b, c, d, k[8], 6, 1873313359);
      d = ii(d, a, b, c, k[15], 10, -30611744);
      c = ii(c, d, a, b, k[6], 15, -1560198380);
      b = ii(b, c, d, a, k[13], 21, 1309151649);
      a = ii(a, b, c, d, k[4], 6, -145523070);
      d = ii(d, a, b, c, k[11], 10, -1120210379);
      c = ii(c, d, a, b, k[2], 15, 718787259);
      b = ii(b, c, d, a, k[9], 21, -343485551);
      x[0] = add32(a, x[0]);
      x[1] = add32(b, x[1]);
      x[2] = add32(c, x[2]);
      x[3] = add32(d, x[3]);
    }

    function md5blk(s) {
      var md5blks = [], i;
      for (i = 0; i < 64; i += 4) {
        md5blks[i >> 2] = s.charCodeAt(i) + (s.charCodeAt(i + 1) << 8) +
          (s.charCodeAt(i + 2) << 16) + (s.charCodeAt(i + 3) << 24);
      }
      return md5blks;
    }

    function md51(s) {
      var n = s.length, state = [1732584193, -271733879, -1732584194, 271733878], i, length, tail, tmp, lo, hi;
      for (i = 64; i <= n; i += 64) { md5cycle(state, md5blk(s.substring(i - 64, i))); }
      s = s.substring(i - 64);
      length = s.length;
      tail = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
      for (i = 0; i < length; i += 1) { tail[i >> 2] |= s.charCodeAt(i) << ((i % 4) << 3); }
      tail[i >> 2] |= 0x80 << ((i % 4) << 3);
      if (i > 55) {
        md5cycle(state, tail);
        for (i = 0; i < 16; i += 1) { tail[i] = 0; }
      }
      tmp = n * 8;
      tmp = tmp.toString(16).match(/(.*?)(.{0,8})$/);
      lo = parseInt(tmp[2], 16);
      hi = parseInt(tmp[1], 16) || 0;
      tail[14] = lo;
      tail[15] = hi;
      md5cycle(state, tail);
      return state;
    }

    function rhex(n) {
      var s = '', j;
      for (j = 0; j < 4; j += 1) { s += hex_chr[(n >> (j * 8 + 4)) & 0x0F] + hex_chr[(n >> (j * 8)) & 0x0F]; }
      return s;
    }
    var hex_chr = '0123456789abcdef'.split('');

    function hex(x) {
      for (var i = 0; i < x.length; i += 1) { x[i] = rhex(x[i]); }
      return x.join('');
    }

    function utf8Encode(str) {
      str = str.replace(/\r\n/g, '\n');
      var utftext = '';
      for (var n = 0; n < str.length; n++) {
        var c = str.charCodeAt(n);
        if (c < 128) { utftext += String.fromCharCode(c); }
        else if ((c > 127) && (c < 2048)) {
          utftext += String.fromCharCode((c >> 6) | 192);
          utftext += String.fromCharCode((c & 63) | 128);
        } else {
          utftext += String.fromCharCode((c >> 12) | 224);
          utftext += String.fromCharCode(((c >> 6) & 63) | 128);
          utftext += String.fromCharCode((c & 63) | 128);
        }
      }
      return utftext;
    }

    return hex(md51(utf8Encode(String(input))));
  }

  // 游客信息是否齐全（不齐全时提交前先弹窗）
  function commentNeedsProfile(form) {
    var author = form.querySelector('#author');
    var mail = form.querySelector('#mail');
    if (!author) return false;
    if (!author.value.trim()) return true;
    if (mail && mail.dataset.required === '1' && !mail.value.trim()) return true;
    return false;
  }

  // 提交按钮的加载态：转圈 + 文案变「提交中」 + 禁用（参考 WeUI 的 weui-btn_loading）
  function setSubmitLoading(btn, loading) {
    if (!btn) return;
    var label = btn.querySelector('.comment-tool-text');
    if (label) {
      if (loading) {
        if (typeof label.dataset.wfLabel === 'undefined') {
          label.dataset.wfLabel = label.textContent;
        }
        label.textContent = '提交中';
      } else if (typeof label.dataset.wfLabel !== 'undefined') {
        label.textContent = label.dataset.wfLabel;
      }
    }
    btn.disabled = !!loading;
    btn.classList.toggle('is-loading', !!loading);
    btn.setAttribute('aria-busy', loading ? 'true' : 'false');
  }

  /* ============ 评论：无刷新提交 ============ */
  /* 提交中标志：防止「连点发送 / 回车 + 点击 / 脚本触发」把同一条内容提交两次。
     按钮的 disabled 只挡鼠标点击，挡不住表单的 submit 事件本身，所以这里再加一道锁。 */
  var commentSubmitting = false;

  function initCommentAjax() {
    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (!form || form.id !== 'comment-form') return;
      if (typeof form.action !== 'string' || !form.action) return;
      e.preventDefault();

      // 上一次提交还没结束：忽略这一次
      if (commentSubmitting) return;

      // 富文本编辑器：提交前把内容写回 textarea
      syncRichToTextarea();

      var submitBtn = form.querySelector('.comment-submit');
      var notice = form.querySelector('.comment-form-notice');
      if (!notice) {
        notice = document.createElement('p');
        notice.className = 'comment-form-notice';
        form.appendChild(notice);
      }

      var failure = function (msg) {
        notice.textContent = '';
        notice.classList.remove('is-error');
        wfToptips(msg, true);
      };

      // 内容为空
      var ta = form.querySelector('#textarea');
      if (ta && !String(ta.value || '').trim()) {
        failure('请输入评论内容');
        if (richEditor) richEditor.focus();
        return;
      }

      // 游客还没填昵称/邮箱：先弹「评论信息」弹窗，填完自动接着提交
      if (commentNeedsProfile(form)) {
        var hint = form.querySelector('[data-profile-hint]');
        if (hint) {
          hint.hidden = false;
          hint.textContent = '请先填写评论信息（昵称、邮箱）';
          hint.classList.add('is-error');
        }
        pendingSubmit = form;
        openProfileDialog();
        return;
      }

      // 本次回复的对象（0 = 顶层评论）。必须在 doCancelReply() 之前读出来 ——
      // 它会把表单里的 parent 隐藏字段删掉。
      var parentInput = form.querySelector('input[name=parent]');
      var submittedParent = parentInput ? String(parentInput.value || '0') : '0';

      // 提交前的评论 id 集合 / 总数 / 待审核 cookie：用来判断这次到底有没有写进去
      var idsBefore = commentIds(document);
      var titleBefore = totalOf(document.getElementById('comments'));
      var pendingBefore = pendingCookie();

      // 提交中：按钮进入加载态（转圈 + 禁用），不再另外显示「提交中…」文字
      notice.textContent = '';
      notice.classList.remove('is-error');
      setSubmitLoading(submitBtn, true);
      commentSubmitting = true;

      fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        redirect: 'follow'
      })
        .then(function (r) {
          // Typecho 提交成功后 302 到文章页；失败时可能抛异常渲染错误页（500/403），
          // 也可能被 protect() 用 goBack 退回原页（200）。这里把状态码和正文都带出去统一判断。
          var finalUrl = r.url;
          return r.text().then(function (html) {
            return { ok: r.ok, status: r.status, html: html, url: finalUrl };
          });
        })
        .then(function (res) {
          var doc = new DOMParser().parseFromString(res.html, 'text/html');

          // 服务端错误页（校验失败 / 频率限制 / 反垃圾不通过）：把页面上的原因显示出来
          var box = doc.querySelector('.container');
          if (!doc.querySelector('#comments') && box) {
            var reason = (box.textContent || '').trim().replace(/\s+/g, ' ');
            throw new Error(reason || ('提交失败（HTTP ' + res.status + '）'));
          }
          if (!res.ok) {
            throw new Error('提交失败（HTTP ' + res.status + '）');
          }

          var newComments = doc.querySelector('#comments');
          var oldComments = document.querySelector('#comments');
          if (!newComments || !oldComments) { location.reload(); return; }

          /* 判断是否真的写进去了。多信号并用，任一条成立即视为成功：
               1) 返回页里有本地没有的评论 id；
               2) 顶部「已有 N 条评论」变多（那是**顶层**评论数，回复不会让它变）；
               3) 返回页里能看到刚提交的那段文字；
               4) 待审核评论会写 __typecho_unapproved_comment cookie（注意 cookie 名带站点前缀）；
               5) 提交成功 Typecho 必定 302 到「评论分页」地址，而反垃圾不通过是 goBack 回原页，
                  两者的最终地址路径可区分。
             注意：浏览器的 fetch 会丢掉 #fragment，所以**不能**再从 res.url 里取 #comment-COID
             （老代码就是这么判的，导致「其实成功了却提示失败」）。 */
          var sentText = ta ? String(ta.value || '').trim() : '';
          var probe = sentText.replace(/\[(?:sticker|emoji):[^\]]+\]/g, '').trim();
          if (probe.length > 24) probe = probe.slice(0, 24);

          var idsAfter = commentIds(doc);
          var newIds = idsAfter.filter(function (id) { return idsBefore.indexOf(id) === -1; });
          var grew = totalOf(newComments) > -1 && titleBefore > -1 && totalOf(newComments) > titleBefore;
          var echoed = probe.length >= 4 && res.html.indexOf(probe) !== -1;
          var pendingNow = pendingCookie();
          var becamePending = !!pendingNow && pendingNow !== pendingBefore;

          var finalPath = '';
          try { finalPath = new URL(res.url || form.action, location.href).pathname; } catch (e) { finalPath = ''; }
          var redirectedToCommentPage = /\/comment-page-\d+\/?$/.test(finalPath)
            && finalPath !== location.pathname;

          var succeeded = newIds.length > 0 || grew || echoed || becamePending || redirectedToCommentPage;

          if (!succeeded) {
            // 真失败（多半是反垃圾令牌对不上）：保留本地表单和已写内容，不动列表
            throw new Error('提交失败，请重试');
          }

          var oldTitle = oldComments.querySelector('.section-title');
          var newTitle = newComments.querySelector('.section-title');
          if (newTitle && oldTitle) oldTitle.innerHTML = newTitle.innerHTML;

          // 重要：回复某条评论时，Typecho 会把整个 .respond（含表单）移动到那条评论的 <li> 里。
          // 更新列表前先把它还原回原位，否则表单会跟着被换掉（表现就是「评论完之后评论框没了」）。
          try { doCancelReply(); } catch (e) { /* ignore */ }

          // 清空输入（表单本身保留，它那份 `_` 令牌继续有效）
          if (ta) ta.value = '';
          var richNow = document.getElementById('comment-rich');
          if (richNow) richNow.innerHTML = '';

          // 保险：万一表单还是不见了，从返回页补回来，别让用户没法继续评论。
          if (document.getElementById('comment-form') === null) {
            var fallbackForm = newComments.querySelector('#comment-form');
            if (fallbackForm) {
              var host = document.getElementById('comments') || document.querySelector('.article-wrap');
              if (host) {
                var node = document.importNode(fallbackForm, true);
                if (host.classList.contains('comments--form-bottom')) host.appendChild(node);
                else host.insertBefore(node, host.firstChild);
              }
            }
          }

          /* 更新评论列表：不整体替换，也不依赖 Typecho 给的跳转页（它的页码是估算的，可能不
             包含新评论）。改成「只把用户当前正在显示的那些分页重新拉一遍」：
               · 回复：新回复渲染在「被回复评论的顶层祖先」所在的那一页 → 只拉那一页，1 个请求；
               · 新增顶层评论：各分页内容会整体位移 → 拉回全部分页并按顺序重建；
             这样「加载更多 / 瀑布流」已加载的评论不会被冲掉，新回复也一定拿得到。 */
          var isNewRoot = submittedParent === '0' || submittedParent === '';
          var pages = loadedCommentPages();
          var targetPage = '';
          if (!isNewRoot) {
            var parentLi = document.getElementById('comment-' + submittedParent);
            if (parentLi) targetPage = commentPageOf(parentLi);
          }
          var refetch = (isNewRoot || !targetPage) ? pages : [targetPage];

          var settle = function () {
            // 列表更新完再找「本次新增的评论」：提交前后的 id 差集就是它
            var nowIds = commentIds(document);
            var freshIds = nowIds.filter(function (id) { return idsBefore.indexOf(id) === -1; });
            freshIds.sort(function (a, b) {
              return parseInt(a.slice(8), 10) - parseInt(b.slice(8), 10);
            });
            var anchorId = freshIds.length ? freshIds[freshIds.length - 1] : '';
            var anchorEl = anchorId ? document.getElementById(anchorId) : null;

            runInit();

            var n3 = document.querySelector('.comment-form-notice');
            if (anchorEl) {
              // 提交成功且能看到：定位并高亮新评论
              if (n3) { n3.textContent = ''; n3.classList.remove('is-error'); }
              try { history.replaceState(null, '', '#' + anchorId); } catch (e) { /* ignore */ }
              anchorEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
              anchorEl.classList.add('is-highlight');
              setTimeout(function () { anchorEl.classList.remove('is-highlight'); }, 2000);
            } else {
              // 提交成功但这屏看不到（多半是待审核）：给说明并定位到评论区
              var wrap = document.querySelector('#comments .comment-list-wrap');
              if (wrap && !wrap.querySelector('.comment-pending-tip')) {
                var tip = document.createElement('li');
                tip.className = 'comment-pending-tip';
                tip.textContent = '你的评论已提交，等待审核通过后显示。';
                wrap.appendChild(tip);
              }
              if (n3) {
                n3.textContent = '评论已提交，等待审核通过后显示。';
                n3.classList.remove('is-error');
              }
              try { history.replaceState(null, '', '#comments'); } catch (e) { /* ignore */ }
              var cmtHost = document.getElementById('comments');
              if (cmtHost) cmtHost.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
          };

          refreshCommentPages(refetch, { replace: isNewRoot }, function (lastDoc) {
            // 顶层评论数变了翻页控件才可能变；用重建时的最后一页同步（分页条 / 加载更多 / 瀑布流）
            if (isNewRoot && lastDoc) syncCommentNavFromDoc(lastDoc);
            settle();
          });
        })
        .catch(function (err) {
          var msg = err && err.message ? err.message : '';
          // fetch 自身失败（离线、被拦截）时给个中文提示
          if (!msg || /Failed to fetch|NetworkError|load failed/i.test(msg)) {
            msg = '网络异常，请检查连接后重试';
          }
          notice.textContent = '';
          notice.classList.remove('is-error');
          wfToptips(msg, true);
        })
        .then(function () {
          // 无论成功失败都把按钮恢复回「发送」，并释放提交锁
          setSubmitLoading(submitBtn, false);
          commentSubmitting = false;
        });
    });
  }

  /* ============ 评论：加载更多（按 URL 抓取下一页并追加） ============
     两种模式（与文章列表侧一致）：
       - button  ：点「加载更多评论」按钮追加下一页；
       - loadmore：滚到底部自动追加下一页。
     容器统一是 .wf-loadmore[data-comment-pagination]，分页模式则是 .weui-comments-pager。 */
  var commentLoadMoreState = null;

  function initCommentLoadMore() {
    var box = document.querySelector('.wf-loadmore[data-comment-pagination]');

    // 容器变了（PJAX 换页）先断开旧观察器
    if (commentLoadMoreState) {
      if (commentLoadMoreState.box === box) return;
      if (commentLoadMoreState.observer) commentLoadMoreState.observer.disconnect();
      commentLoadMoreState = null;
    }
    if (!box) return;

    var listWrap = document.querySelector('.comment-list-wrap');
    if (!listWrap) return;

    var isAuto = box.getAttribute('data-comment-pagination') === 'loadmore';
    var btn = box.querySelector('.wf-loadmore__btn') || box.querySelector('.wf-loadmore__fallback');

    var st = {
      box: box,
      listWrap: listWrap,
      isAuto: isAuto,
      nextUrl: box.getAttribute('data-next') || '',
      loading: false,
      observer: null
    };
    commentLoadMoreState = st;

    // JS 接管：隐藏兜底按钮，显隐交给 is-loading / is-end
    box.classList.add('wf-loadmore--js');

    function showEnd() {
      box.classList.remove('is-loading');
      box.classList.add('is-end');
      if (st.observer) st.observer.disconnect();
    }

    function load() {
      if (commentLoadMoreState !== st || st.loading) return;
      if (!st.nextUrl) { showEnd(); return; }

      if (!st.isAuto && btn) btn.classList.add('is-loading');
      st.loading = true;
      box.classList.add('is-loading');
      var sent = st.nextUrl;

      fetch(sent, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.text(); })
        .then(function (html) {
          if (commentLoadMoreState !== st) return;
          var doc = new DOMParser().parseFromString(html, 'text/html');
          var newWrap = doc.querySelector('.comment-list-wrap');
          if (st.listWrap && newWrap) {
            // 追加到已有的 <ol class="comment-list"> 里，避免出现第二个列表
            var target = st.listWrap.querySelector('.comment-list') || st.listWrap;
            var incoming = newWrap.querySelector('.comment-list') || newWrap;
            var srcPage = stripHash(sent);
            Array.prototype.slice.call(incoming.children).forEach(function (node) {
              if (node.tagName !== 'LI' || !/^comment-\d+$/.test(node.id || '')) return;
              // 已经有了就跳过（提交后合并过这一页时会出现重叠）
              if (document.getElementById(node.id)) return;
              var copy = document.importNode(node, true);
              if (!copy.getAttribute('data-wf-page')) copy.setAttribute('data-wf-page', srcPage);
              target.appendChild(copy);
            });
          }
          var newBox = doc.querySelector('.wf-loadmore[data-comment-pagination]');
          st.nextUrl = (sent === st.nextUrl)
            ? (newBox ? (newBox.getAttribute('data-next') || '') : '')
            : st.nextUrl;
          box.setAttribute('data-next', st.nextUrl);
        })
        .catch(function () { /* 失败静默，容器仍在可重试 */ })
        .then(function () {
          if (commentLoadMoreState !== st) return;
          st.loading = false;
          box.classList.remove('is-loading');
          if (!st.isAuto && btn) btn.classList.remove('is-loading');
          if (!st.nextUrl) showEnd();
          runInit();
          checkViewport();
        });
    }

    function checkViewport() {
      if (commentLoadMoreState !== st || st.loading || !st.nextUrl) return;
      if (!st.isAuto) return;
      var rect = box.getBoundingClientRect();
      var vh = window.innerHeight || document.documentElement.clientHeight || 0;
      if (rect.top <= vh + 400) load();
    }

    if (!st.nextUrl) { showEnd(); return; }

    if (!st.isAuto) {
      if (btn) btn.addEventListener('click', function (e) { e.preventDefault(); load(); });
      return;
    }

    if ('IntersectionObserver' in window) {
      st.observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) load();
        });
      }, { rootMargin: '0px 0px 400px 0px' });
      st.observer.observe(box);
      checkViewport();
    } else if (btn) {
      btn.addEventListener('click', function (e) { e.preventDefault(); load(); });
    }
  }

  /* ============ 评论：回复框定位 + 取消回复 ============
     Typecho 自带的 TypechoComment.reply() 会把表单插到「回复按钮所在的直接子元素」之后，
     而回复按钮在 <li> 末尾（其后面还有子评论），于是回复框会跑到子评论下面。
     这里在捕获阶段拦掉它，自己把表单插到被回复评论的正文之后（子评论之前），
     并且不依赖 Typecho 的 holder 机制，AJAX 换页后依然可用。 */
  var REPLY_RESPOND = null;

  function currentForm() {
    return document.getElementById('comment-form');
  }
  function respondOf(form) {
    return form ? form.closest('.respond') : null;
  }

  function parseReplyCoid(a) {
    // 优先用所在 <li> 的 id（最稳：不依赖内联 onclick，也不依赖 Typecho 注入的脚本，
    // 所以 AJAX 换进来的新评论同样能回复）
    var li = a.closest('li.comment-body');
    if (li && /^comment-\d+$/.test(li.id)) {
      return li.id.slice('comment-'.length);
    }
    var onclick = a.getAttribute('onclick') || '';
    var m = /TypechoComment\.reply\(\s*'[^']*'\s*,\s*(\d+)/.exec(onclick);
    if (m) return m[1];
    return a.getAttribute('data-coid') || '';
  }

  function doReply(coid) {
    var form = currentForm();
    if (!form) return;
    var respond = respondOf(form);
    var li = document.getElementById('comment-' + coid);
    if (!respond || !li) return;

    // 之前如果停在别的评论里，先把表单还原，避免「表单已在 A，却要插到 B」的错位
    if (respond.parentElement && respond.parentElement.classList.contains('comment-body')) {
      doCancelReply();
      var home = currentForm();
      respond = home ? respondOf(home) : respond;
      li = document.getElementById('comment-' + coid) || li;
      if (!respond) return;
    }

    // 记住表单原来的位置，取消回复时放回去
    REPLY_RESPOND = { parent: respond.parentNode, next: respond.nextSibling };

    // 写入 parent 隐藏字段（用最新表单里的那个）
    var f = currentForm() || form;
    var parent = f.querySelector('input[name=parent]');
    if (!parent) {
      parent = document.createElement('input');
      parent.type = 'hidden';
      parent.name = 'parent';
      f.appendChild(parent);
    }
    parent.value = coid;

    // 插到正文之后、子评论之前
    var content = li.querySelector('.comment-content');
    li.insertBefore(respond, content ? content.nextSibling : li.firstChild);

    var cancel = document.getElementById('cancel-comment-reply-link');
    if (cancel) cancel.style.display = '';

    li.classList.add('is-replying');
    var ta = f.querySelector('textarea[name=text]');
    if (ta) {
      ta.focus();
      try { ta.setSelectionRange(ta.value.length, ta.value.length); } catch (e) { /* ignore */ }
    }
  }

  // 清空评论输入（原生 textarea + 富文本编辑器同步）
  function clearCommentInput() {
    var form = currentForm();
    if (!form) return;
    var ta = form.querySelector('textarea[name=text]');
    if (ta) ta.value = '';
    var rich = form.querySelector('#comment-rich');
    if (rich) rich.innerHTML = '';
  }

  function doCancelReply() {
    var form = currentForm();
    if (!form) return;
    var respond = respondOf(form);
    if (!respond) return;

    var parent = form.querySelector('input[name=parent]');
    if (parent && parent.parentNode) parent.parentNode.removeChild(parent);

    if (REPLY_RESPOND && REPLY_RESPOND.parent && REPLY_RESPOND.parent.isConnected) {
      REPLY_RESPOND.parent.insertBefore(respond, REPLY_RESPOND.next);
    }
    REPLY_RESPOND = null;

    var cancel = document.getElementById('cancel-comment-reply-link');
    if (cancel) cancel.style.display = 'none';

    document.querySelectorAll('.comment-body.is-replying').forEach(function (el) {
      el.classList.remove('is-replying');
    });

    // 取消回复时把已经写的内容一并清空，避免下次评论还残留着回复别人的内容
    clearCommentInput();
  }

  function initCommentReply() {
    document.addEventListener('click', function (e) {
      if (!e.target.closest) return;

      // 点 @某人：跳到被回复的那条评论（纯锚点，不触发 PJAX）
      var mention = e.target.closest('.comment-mention');
      if (mention) {
        var mcoid = mention.getAttribute('data-mention-coid') || '';
        var mtarget = mcoid ? document.getElementById('comment-' + mcoid) : null;
        if (mtarget) {
          e.preventDefault();
          e.stopPropagation();
          try { history.replaceState(null, '', '#comment-' + mcoid); } catch (err) { /* ignore */ }
          mtarget.scrollIntoView({ behavior: 'smooth', block: 'center' });
          mtarget.classList.add('is-highlight');
          setTimeout(function () { mtarget.classList.remove('is-highlight'); }, 2000);
        }
        return;
      }

      var a = e.target.closest('.comment-actions a');
      if (a) {
        var coid = parseReplyCoid(a);
        if (coid) {
          e.preventDefault();
          e.stopPropagation();
          doReply(coid);
        }
        return;
      }

      var cancel = e.target.closest('#cancel-comment-reply-link');
      if (cancel) {
        e.preventDefault();
        e.stopPropagation();
        doCancelReply();
      }
    }, true);
  }

  /* ============ 评论：富文本编辑器（表情 / 贴纸所见即所得） ============
     原生 textarea 保持存在（无 JS 也能评论），JS 启用后隐藏它、换成 contenteditable，
     表情与贴纸以图片形式直接插入，提交前再序列化成 [sticker:名字] / [emoji:编号] 短代码。 */
  var EMOJI_MANIFEST = null;
  var richEditor = null;
  var savedRange = null;

  function escapeHtmlText(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }
  function escapeAttr(s) {
    return escapeHtmlText(s).replace(/"/g, '&quot;');
  }

  function emojiSrc(kind, name) {
    var base = (richEditor && richEditor.getAttribute('data-emoji-base')) || '';
    base = base.replace(/\/+$/, '');
    return kind === 'sticker'
      ? base + '/stickers/' + encodeURIComponent(name) + '.png'
      : base + '/emoji/' + encodeURIComponent(name) + '.svg';
  }

  // 短代码 -> 预览 HTML（把 [sticker:x]/[emoji:x] 换成图片）
  function shortcodeToPreviewHtml(text) {
    var safe = escapeHtmlText(text || '');
    safe = safe.replace(/\[sticker:([^\]\s]{1,32})\]/g, function (all, name) {
      var sc = '[sticker:' + name + ']';
      return '<img class="wf-sticker" src="' + escapeAttr(emojiSrc('sticker', name))
        + '" alt="' + escapeAttr(sc) + '" data-shortcode="' + escapeAttr(sc) + '">';
    });
    safe = safe.replace(/\[emoji:([^\]\s]{1,32})\]/g, function (all, name) {
      var sc = '[emoji:' + name + ']';
      return '<img class="wf-biaoqing" src="' + escapeAttr(emojiSrc('emoji', name))
        + '" alt="' + escapeAttr(sc) + '" data-shortcode="' + escapeAttr(sc) + '">';
    });
    return safe.replace(/\n/g, '<br>');
  }

  // 编辑器 DOM -> 纯文本（图片还原成短代码）
  function richToText(node) {
    if (!node) return '';
    if (node.nodeType === 3) return node.nodeValue || '';
    if (node.nodeType !== 1) return '';
    var tag = node.tagName;
    if (tag === 'BR') return '\n';
    if (tag === 'IMG') return node.getAttribute('data-shortcode') || node.getAttribute('alt') || '';
    var out = '';
    for (var c = node.firstChild; c; c = c.nextSibling) out += richToText(c);
    if ((tag === 'DIV' || tag === 'P') && out.slice(-1) !== '\n') out += '\n';
    return out;
  }

  function syncRichToTextarea() {
    if (!richEditor) return;
    var form = document.getElementById('comment-form');
    var ta = form && form.querySelector('#textarea');
    if (!ta) return;
    var text = richToText(richEditor).replace(/\u00a0/g, ' ');
    ta.value = text.replace(/[ \t]+\n/g, '\n').replace(/\n{3,}/g, '\n\n').replace(/^\n+|\n+$/g, '');
  }

  function isRangeInEditor(range) {
    if (!richEditor || !range) return false;
    var c = range.commonAncestorContainer;
    if (!c) return false;
    var el = c.nodeType === 1 ? c : c.parentNode;
    return el === richEditor || richEditor.contains(el);
  }

  function saveRichSelection() {
    if (!richEditor || !window.getSelection) return;
    var sel = window.getSelection();
    if (!sel || !sel.rangeCount) return;
    var range = sel.getRangeAt(0);
    if (!isRangeInEditor(range)) return;
    try { savedRange = range.cloneRange(); } catch (e) { /* ignore */ }
  }

  function restoreRichSelection() {
    if (!richEditor || !window.getSelection) return false;
    var sel = window.getSelection();
    if (savedRange && isRangeInEditor(savedRange)) {
      try {
        sel.removeAllRanges();
        sel.addRange(savedRange);
        return true;
      } catch (e) { /* ignore */ }
    }
    return false;
  }

  function placeCaretEnd() {
    if (!richEditor || !window.getSelection) return null;
    var range = document.createRange();
    range.selectNodeContents(richEditor);
    range.collapse(false);
    var sel = window.getSelection();
    sel.removeAllRanges();
    sel.addRange(range);
    savedRange = range.cloneRange();
    return range;
  }

  function insertRichNode(node) {
    if (!richEditor) return;
    richEditor.focus();
    restoreRichSelection();
    var sel = window.getSelection();
    if (!sel || !sel.rangeCount) { placeCaretEnd(); sel = window.getSelection(); }
    if (!sel || !sel.rangeCount) return;

    var range = sel.getRangeAt(0);
    if (!isRangeInEditor(range)) {
      range = placeCaretEnd();
      if (!range) return;
    }
    range.deleteContents();
    var spacer = document.createTextNode(' ');
    range.insertNode(spacer);
    range.insertNode(node);
    range.setStartAfter(spacer);
    range.collapse(true);
    sel.removeAllRanges();
    sel.addRange(range);
    try { savedRange = range.cloneRange(); } catch (e) { /* ignore */ }
  }

  function insertEmoji(kind, name) {
    if (!richEditor || !name) return;
    var sc = '[' + kind + ':' + name + ']';
    var img = document.createElement('img');
    img.className = kind === 'sticker' ? 'wf-sticker' : 'wf-biaoqing';
    img.src = emojiSrc(kind, name);
    img.alt = sc;
    img.setAttribute('data-shortcode', sc);
    insertRichNode(img);
    syncRichToTextarea();
  }

  function initCommentEditor() {
    var form = document.getElementById('comment-form');
    if (!form || form.dataset.wfEditor === '1') return;
    var ta = form.querySelector('#textarea');
    var rich = form.querySelector('#comment-rich');
    if (!ta || !rich) return;
    form.dataset.wfEditor = '1';
    richEditor = rich;

    // 隐藏原生 textarea（避免 required 在提交时弹校验），启用富文本
    ta.removeAttribute('required');
    var wrap = rich.closest('.comment-editor');
    if (wrap) wrap.classList.add('is-rich');
    rich.hidden = false;
    rich.innerHTML = shortcodeToPreviewHtml(ta.value || '');
    syncRichToTextarea();

    rich.addEventListener('input', function () { syncRichToTextarea(); saveRichSelection(); });
    rich.addEventListener('focus', saveRichSelection);
    rich.addEventListener('keyup', saveRichSelection);
    rich.addEventListener('mouseup', saveRichSelection);
    rich.addEventListener('blur', function () { syncRichToTextarea(); saveRichSelection(); });

    // Ctrl / Cmd + Enter 在富文本里直接提交
    rich.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
        e.preventDefault();
        if (typeof form.requestSubmit === 'function') form.requestSubmit();
        else form.submit();
      }
    });

    // 粘贴只保留纯文本
    rich.addEventListener('paste', function (e) {
      e.preventDefault();
      var text = (e.clipboardData || window.clipboardData).getData('text') || '';
      document.execCommand('insertText', false, text);
      syncRichToTextarea();
      saveRichSelection();
    });

    // 原生提交兜底：确保写入 textarea
    form.addEventListener('submit', syncRichToTextarea);
  }

  function loadEmojiManifest(cb) {
    if (EMOJI_MANIFEST) { cb(EMOJI_MANIFEST); return; }
    var base = (richEditor && richEditor.getAttribute('data-emoji-base') || '').replace(/\/+$/, '');
    fetch(base + '/manifest.json', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        EMOJI_MANIFEST = data || { stickers: [], emoji: [] };
        cb(EMOJI_MANIFEST);
      })
      .catch(function () {
        EMOJI_MANIFEST = { stickers: [], emoji: [] };
        cb(EMOJI_MANIFEST);
      });
  }

  function renderEmojiList(kind) {
    var list = document.getElementById('comment-emoji-list');
    if (!list) return;
    list.innerHTML = '';
    var names = (EMOJI_MANIFEST && EMOJI_MANIFEST[kind === 'sticker' ? 'stickers' : 'emoji']) || [];
    names.forEach(function (name) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'comment-emoji__item';
      btn.setAttribute('data-kind', kind);
      btn.setAttribute('data-name', name);
      var img = document.createElement('img');
      img.src = emojiSrc(kind, name);
      img.alt = name;
      img.loading = 'lazy';
      btn.appendChild(img);
      list.appendChild(btn);
    });
  }

  // 点空白 / Esc 收起面板（只注册一次，动态取当前面板）
  function bindEmojiDocOnce() {
    if (bindEmojiDocOnce._done) return;
    bindEmojiDocOnce._done = true;

    function closeCurrent() {
      var panel = document.getElementById('comment-emoji');
      if (!panel || !panel.classList.contains('is-open')) return;
      panel.classList.remove('is-open');
      var t = document.querySelector('[data-emoji-toggle]');
      if (t) t.classList.remove('is-active');
    }

    // 供别处复用（插入表情后自动收起）
    bindEmojiDocOnce.close = closeCurrent;

    document.addEventListener('click', function (e) {
      if (!e.target.closest) return;
      if (e.target.closest('#comment-emoji') || e.target.closest('[data-emoji-toggle]')) return;
      closeCurrent();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeCurrent();
    });
  }

  function initCommentEmoji() {
    var form = document.getElementById('comment-form');
    var panel = document.getElementById('comment-emoji');
    var toggle = form && form.querySelector('[data-emoji-toggle]');
    if (!form || !panel || !toggle || panel.dataset.wfEmoji === '1') return;
    panel.dataset.wfEmoji = '1';
    bindEmojiDocOnce();

    var current = 'sticker';

    function open() {
      panel.classList.add('is-open');
      toggle.classList.add('is-active');
      loadEmojiManifest(function () { renderEmojiList(current); });
    }
    function close() {
      panel.classList.remove('is-open');
      toggle.classList.remove('is-active');
    }

    // 打开面板前先记住编辑器里的光标位置
    toggle.addEventListener('pointerdown', saveRichSelection);
    toggle.addEventListener('mousedown', saveRichSelection);
    toggle.addEventListener('click', function (e) {
      e.preventDefault();
      if (panel.classList.contains('is-open')) close(); else open();
    });

    panel.querySelectorAll('[data-emoji-tab]').forEach(function (tab) {
      tab.addEventListener('click', function (e) {
        e.preventDefault();
        panel.querySelectorAll('[data-emoji-tab]').forEach(function (t) { t.classList.remove('is-active'); });
        tab.classList.add('is-active');
        current = tab.getAttribute('data-emoji-tab') === 'emoji' ? 'emoji' : 'sticker';
        if (EMOJI_MANIFEST) renderEmojiList(current);
      });
    });

    var closeBtn = panel.querySelector('[data-emoji-close]');
    if (closeBtn) closeBtn.addEventListener('click', function (e) { e.preventDefault(); close(); });

    // 面板内按下时不让编辑器的选区丢失
    panel.addEventListener('pointerdown', function (e) { e.preventDefault(); });

    panel.addEventListener('click', function (e) {
      var item = e.target.closest && e.target.closest('.comment-emoji__item');
      if (!item) return;
      e.preventDefault();
      insertEmoji(item.getAttribute('data-kind'), item.getAttribute('data-name'));
      // 点完一个表情就收起面板（按需求：点击表情后关闭）
      close();
    });
  }

  /* ============ 点赞 / 点踩 ============ */
  function initVote() {
    if (initVote._done) return;
    initVote._done = true;

    // 同一内容提交中时忽略重复点击
    var busy = {};

    // 只有点赞：state 为 'like' 表示已赞，'' 表示未赞（再点即取消）
    function paint(box, likes, state) {
      var likeBtn = box.querySelector('.wf-vote__btn--like');
      if (likeBtn) {
        var lc = likeBtn.querySelector('[data-count]');
        if (lc && typeof likes !== 'undefined') lc.textContent = likes;
        likeBtn.classList.toggle('is-voted', state === 'like');
        likeBtn.setAttribute('aria-pressed', state === 'like' ? 'true' : 'false');
      }
      box.setAttribute('data-state', state === 'like' ? 'like' : '');
    }

    document.addEventListener('click', function (e) {
      if (!e.target.closest) return;
      var btn = e.target.closest('.wf-vote__btn');
      if (!btn) return;
      var box = btn.closest('.wf-vote');
      if (!box) return;

      e.preventDefault();
      var type = box.getAttribute('data-vote') || 'post';
      var id = box.getAttribute('data-id') || '';
      var key = type + ':' + id;
      if (busy[key]) return;
      busy[key] = true;

      var body = new FormData();
      body.append('weui_vote', type);
      body.append('weui_id', id);
      body.append('weui_dir', 'like');

      fetch(location.pathname + location.search, {
        method: 'POST',
        body: body,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data || !data.ok) return;
          paint(box, data.likes, data.state || '');
        })
        .catch(function () { /* 失败静默 */ })
        .then(function () { busy[key] = false; });
    });
  }

  /* ============ 头部搜索：点按钮展开 / 收起 ============
     用文档级委托（和投票/时间一致），不依赖初始化时元素是否已经存在，
     PJAX 换页后也能照常工作。 */
  function initSiteSearch() {
    if (initSiteSearch._done) return;
    initSiteSearch._done = true;

    function box() { return document.getElementById('site-search'); }
    function toggleBtn() { return document.querySelector('[data-search-toggle]'); }

    function syncClear() {
      var b = box();
      if (!b) return;
      var clear = b.querySelector('[data-search-clear]');
      var input = b.querySelector('.weui-search-bar__input');
      if (clear && input) clear.hidden = input.value === '';
    }

    function setOpen(open) {
      var b = box(), t = toggleBtn();
      if (!b) return;
      b.hidden = !open;
      if (t) {
        t.classList.toggle('is-active', open);
        t.setAttribute('aria-expanded', open ? 'true' : 'false');
      }
      if (open) {
        var input = b.querySelector('.weui-search-bar__input');
        if (input) {
          try { input.focus(); } catch (e) { /* ignore */ }
        }
      }
      syncClear();
    }

    document.addEventListener('click', function (e) {
      if (!e.target.closest) return;

      if (e.target.closest('[data-search-toggle]')) {
        e.preventDefault();
        var b = box();
        setOpen(b ? b.hidden : false);
        return;
      }
      if (e.target.closest('[data-search-clear]')) {
        e.preventDefault();
        var bb = box();
        var input = bb ? bb.querySelector('.weui-search-bar__input') : null;
        if (input) {
          input.value = '';
          input.focus();
        }
        syncClear();
      }
    });

    document.addEventListener('input', function (e) {
      if (!e.target.closest) return;
      if (e.target.classList && e.target.classList.contains('weui-search-bar__input')) {
        syncClear();
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      var b = box();
      if (b && !b.hidden) setOpen(false);
    });
  }

  /* ============ 时间：点击在「相对时间 / 完整时间」之间切换 ============ */
  function initTime() {
    if (initTime._done) return;
    initTime._done = true;

    document.addEventListener('click', function (e) {
      if (!e.target.closest) return;
      var t = e.target.closest('time[data-reltime]');
      if (!t) return;
      e.preventDefault();
      var full = t.getAttribute('data-full') || '';
      var rel = t.getAttribute('data-rel') || '';
      var showingFull = t.getAttribute('data-showing') === 'full';
      if (showingFull) {
        t.textContent = rel;
        t.setAttribute('data-showing', 'rel');
      } else {
        t.textContent = full;
        t.setAttribute('data-showing', 'full');
      }
    });
  }

  /* ============ 打赏：二维码弹窗 ============ */
  function initReward() {
    if (initReward._done) return;
    initReward._done = true;

    function dlg() { return document.getElementById('wf-reward-dialog'); }
    function mask() { return document.getElementById('wf-reward-mask'); }

    function close() {
      var d = dlg(), m = mask();
      if (d) d.classList.remove('is-open');
      if (m) m.classList.remove('is-open');
    }

    document.addEventListener('click', function (e) {
      if (!e.target.closest) return;

      if (e.target.closest('[data-reward-open]')) {
        e.preventDefault();
        var d = dlg(), m = mask();
        if (!d || !m) return;
        m.classList.add('is-open');
        d.classList.add('is-open');
        return;
      }
      if (e.target.closest('[data-reward-close]')) {
        e.preventDefault();
        close();
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') close();
    });
  }

  /* ============ 分享：分享面板（微博 / QQ空间 / Telegram / 微信扫码 / 复制链接 / 生成海报） ============
     点分享**一律弹自带面板**，不调用 navigator.share（系统分享）：
     桌面 Chrome / Edge 也暴露 navigator.share，但 Windows 的系统分享面板常常没有可用目标，
     一点就弹「我们无法为你显示所有共享方法」，等于把访客卡住；各平台表现又不一致，
     所以干脆不依赖它，统一走面板，行为可预期、样式可控。
     社交项靠设置 <a> 的 href + target="_blank" 打开，不经过 window.open。
     分享地址与素材由服务端渲染在按钮的 data-* 上，这里用**事件委托**读取，
     所以 PJAX 换页后新生成的按钮无需重新绑定。 */
  function initShare() {
    if (initShare._done) return;
    initShare._done = true;

    function dlg() { return document.getElementById('wf-share-dialog'); }
    function mask() { return document.getElementById('wf-share-mask'); }
    function qrBox() { return document.getElementById('wf-share-qr'); }
    function posterDlg() { return document.getElementById('wf-poster-dialog'); }
    function posterMask() { return document.getElementById('wf-poster-mask'); }
    function posterStage() { return document.getElementById('wf-poster-stage'); }
    function posterSave() { return document.querySelector('[data-poster-save]'); }

    function close() {
      var d = dlg(), m = mask(), q = qrBox();
      if (d) d.classList.remove('is-open');
      if (m) m.classList.remove('is-open');
      if (q) { q.hidden = true; q.innerHTML = ''; }
      closePoster();
    }

    function closePoster() {
      var d = posterDlg(), m = posterMask();
      if (d) d.classList.remove('is-open');
      if (m) m.classList.remove('is-open');
      // 清掉上一次的海报，下次打开重新画；顺手释放 blob URL
      posterUrl = '';
      revokePosterBlob();
      var stage = posterStage();
      if (stage) stage.innerHTML = '';
    }

    // 当前正在分享的文章（点面板里的社交项 / 生成海报时用）
    var current = { url: '', title: '', cover: '', excerpt: '', site: '', date: '' };

    function open(d, m, q) {
      m.classList.add('is-open');
      d.classList.add('is-open');
      if (q) { q.hidden = true; q.innerHTML = ''; }
    }

    document.addEventListener('click', function (e) {
      if (!e.target.closest) return;

      // 打开面板
      var openBtn = e.target.closest('[data-share-open]');
      if (openBtn) {
        e.preventDefault();
        var d = dlg(), m = mask();
        if (!d || !m) return;
        current.url = openBtn.getAttribute('data-share-url') || location.href;
        current.title = openBtn.getAttribute('data-share-title') || document.title;
        current.cover = openBtn.getAttribute('data-share-cover') || '';
        current.excerpt = openBtn.getAttribute('data-share-excerpt') || '';
        current.site = openBtn.getAttribute('data-share-site') || '';
        current.date = openBtn.getAttribute('data-share-date') || '';

        open(d, m, qrBox());
        return;
      }

      // 关闭面板
      if (e.target.closest('[data-share-close]')) {
        e.preventDefault();
        close();
        return;
      }

      // 面板内的分享项
      var item = e.target.closest('.wf-share__item');
      if (!item || !dlg() || !dlg().classList.contains('is-open')) return;
      var kind = item.getAttribute('data-share');
      var enc = encodeURIComponent;
      var url = current.url || location.href;
      var title = current.title || document.title;
      var q = qrBox();

      if (kind === 'copy') {
        e.preventDefault();
        var label = item.querySelector('span');
        var old = label ? label.textContent : '';
        var done = function () {
          if (label) {
            label.textContent = '已复制';
            setTimeout(function () { label.textContent = old || '复制链接'; }, 1500);
          }
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(url).then(done, function () { fallbackCopy(url, done); });
        } else {
          fallbackCopy(url, done);
        }
        return;
      }

      if (kind === 'wechat') {
        e.preventDefault();
        if (!q) return;
        // 再点一次收起（和参考站一致）
        if (!q.hidden) { q.hidden = true; q.innerHTML = ''; return; }
        q.hidden = false;
        q.innerHTML = '';
        if (typeof window.QRCode === 'function') {
          new window.QRCode(q, {
            text: url,
            width: 180,
            height: 180,
            correctLevel: window.QRCode.CorrectLevel ? window.QRCode.CorrectLevel.M : 0
          });
        } else {
          q.textContent = '二维码加载失败，请复制链接分享';
        }
        return;
      }

      if (kind === 'poster') {
        e.preventDefault();
        close();
        openPoster();
        return;
      }

      var links = {
        weibo: 'https://service.weibo.com/share/share.php?url=' + enc(url) + '&title=' + enc(title),
        qzone: 'https://sns.qzone.qq.com/cgi-bin/qzshare/cgi_qzshare_onekey?url=' + enc(url) + '&title=' + enc(title),
        telegram: 'https://t.me/share/url?url=' + enc(url) + '&text=' + enc(title)
      };
      // 社交项是 <a target="_blank">：设置 href 后浏览器自然在新窗口打开（与参考站一致）
      if (links[kind]) {
        item.href = links[kind];
      }
    });

    /* ---------- 海报：纯前端 canvas 绘制 ---------- */
    // 画好的海报：展示用 data URL（Safari 长按「存储图像」支持最广）；
    // 下载按钮另用 blob URL —— Safari 会忽略 data: 上的 download 属性，
    // 但对同源 blob: 的 <a download> 会正常触发下载，两边都照顾到。
    var posterUrl = '';      // data URL，给 <img> 显示 / 长按
    var posterBlobUrl = '';  // blob URL，给「保存图片」下载

    function revokePosterBlob() {
      if (posterBlobUrl) {
        try { URL.revokeObjectURL(posterBlobUrl); } catch (e) { /* ignore */ }
        posterBlobUrl = '';
      }
    }

    function openPoster() {
      var d = posterDlg(), m = posterMask();
      if (!d || !m) return;
      // 上一次的海报还在时，「保存图片」也是显示状态；先收起来，
      // 等这次画好再由 finishPoster() 打开，避免出现「按钮已亮但没图」。
      var save = posterSave();
      if (save) save.hidden = true;
      posterUrl = '';
      revokePosterBlob();
      m.classList.add('is-open');
      d.classList.add('is-open');
      drawPoster();
    }

    // 绘制海报：画布 750x1000（2x 便于高清），内容参考 kx.gy 的样式——
    // 顶部站点名、大标题、封面与摘要卡片、底部扫码区与站点/日期。
    function drawPoster() {
      var stage = posterStage();
      if (!stage) return;
      stage.innerHTML = '';
      var W = 750, H = 1000;
      var cv = document.createElement('canvas');
      cv.width = W; cv.height = H;
      cv.className = 'wf-poster__canvas';
      var ctx = cv.getContext('2d');
      if (!ctx) return;

      var C = {
        bg: '#ffffff',
        title: '#111111',
        muted: '#8a8a8a',
        body: '#4a4a4a',
        card: '#f5f6f7',
        brand: '#07c160'
      };

      function roundRect(x, y, w, h, r) {
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
      }

      // 按宽度折行（支持中英文），返回实际占用的行数
      function wrap(text, maxWidth, maxLines) {
        var lines = [];
        var cur = '';
        var truncated = false;
        var chars = String(text || '').split('');
        for (var i = 0; i < chars.length; i++) {
          var test = cur + chars[i];
          if (ctx.measureText(test).width > maxWidth && cur !== '') {
            lines.push(cur);
            cur = chars[i];
            if (maxLines && lines.length >= maxLines) { truncated = true; cur = ''; break; }
          } else {
            cur = test;
          }
        }
        if (cur && (!maxLines || lines.length < maxLines)) lines.push(cur);
        if (truncated && lines.length) {
          // 末行超出加省略号
          var last = lines[lines.length - 1];
          while (last.length > 1 && ctx.measureText(last + '…').width > maxWidth) last = last.slice(0, -1);
          lines[lines.length - 1] = last + '…';
        }
        return lines;
      }

      ctx.fillStyle = C.bg;
      ctx.fillRect(0, 0, W, H);

      var padX = 48;
      var contentW = W - padX * 2;
      var y = 64;

      // 站点名
      ctx.fillStyle = C.brand;
      ctx.font = '600 30px -apple-system, "PingFang SC", "Microsoft YaHei", sans-serif';
      ctx.textBaseline = 'top';
      if (current.site) ctx.fillText(current.site, padX, y);
      y += 52;

      // 标题（最多 2 行）
      ctx.fillStyle = C.title;
      ctx.font = '700 46px -apple-system, "PingFang SC", "Microsoft YaHei", sans-serif';
      var titleLines = wrap(current.title || document.title, contentW, 2);
      for (var t = 0; t < titleLines.length; t++) {
        ctx.fillText(titleLines[t], padX, y);
        y += 62;
      }
      y += 18;

      // 封面（有则等比铺满一个圆角矩形）
      var coverY = y;
      var coverH = 320;
      if (current.cover) {
        ctx.fillStyle = C.card;
        roundRect(padX, y, contentW, coverH, 16);
        ctx.fill();
        y += coverH + 32;
      }

      // 底部二维码区先占好位置，摘要按剩余高度决定行数，避免与页脚重叠
      var qrSize = 150;
      var qrX = W - padX - qrSize;
      var qrY = H - 64 - qrSize;
      var contentBottom = qrY - 40;

      // 摘要卡片（行数按可用高度自适应，最多 4 行）
      var lineH = 46;
      var cardPad = 28;
      ctx.font = '400 30px -apple-system, "PingFang SC", "Microsoft YaHei", sans-serif';
      var maxLines = Math.floor((contentBottom - y - cardPad * 2 - 6) / lineH);
      if (maxLines < 1) maxLines = 1;
      if (maxLines > 4) maxLines = 4;
      var descLines = current.excerpt ? wrap(current.excerpt, contentW - 48, maxLines) : [];
      if (descLines.length) {
        var cardH = descLines.length * lineH + cardPad * 2;
        ctx.fillStyle = C.card;
        roundRect(padX, y, contentW, cardH, 16);
        ctx.fill();
        ctx.fillStyle = C.body;
        var dy = y + cardPad + 6;
        for (var l = 0; l < descLines.length; l++) {
          ctx.fillText(descLines[l], padX + 24, dy);
          dy += lineH;
        }
        y += cardH + 40;
      }

      // 底部：左「扫码阅读全文」+ 站点/日期，右二维码
      ctx.fillStyle = C.title;
      ctx.font = '600 32px -apple-system, "PingFang SC", "Microsoft YaHei", sans-serif';
      ctx.textBaseline = 'alphabetic';
      ctx.fillText('扫码阅读全文', padX, qrY + 52);

      ctx.fillStyle = C.muted;
      ctx.font = '400 26px -apple-system, "PingFang SC", "Microsoft YaHei", sans-serif';
      var footText = [current.site, current.date].filter(Boolean).join(' · ');
      if (footText) ctx.fillText(footText, padX, qrY + 106);

      // 二维码：复用 qrcodejs，画到离屏容器再取 canvas。
      // 二维码与封面都是异步 / 回调形式，必须两块都画完再导出，
      // 否则会导出一张「少二维码」或「少封面」的残缺海报。
      var pending = 2;
      function step() {
        pending--;
        if (pending <= 0) finishPoster(cv, stage);
      }

      drawQrTo(ctx, current.url || location.href, qrX, qrY, qrSize, step);

      // 封面异步加载，避免 CORS / 加载失败时整体卡住
      if (current.cover) {
        loadImage(current.cover, function (img) {
          if (img) drawCover(ctx, img, padX, coverY, contentW, coverH, 16);
          step();
        });
      } else {
        step();
      }
    }

    function drawCover(ctx, img, x, y, w, h, r) {
      ctx.save();
      ctx.beginPath();
      ctx.moveTo(x + r, y);
      ctx.arcTo(x + w, y, x + w, y + h, r);
      ctx.arcTo(x + w, y + h, x, y + h, r);
      ctx.arcTo(x, y + h, x, y, r);
      ctx.arcTo(x, y, x + w, y, r);
      ctx.closePath();
      ctx.clip();
      // 等比裁剪铺满（cover）
      var iw = img.width || 1, ih = img.height || 1;
      var scale = Math.max(w / iw, h / ih);
      var dw = iw * scale, dh = ih * scale;
      ctx.drawImage(img, x + (w - dw) / 2, y + (h - dh) / 2, dw, dh);
      ctx.restore();
    }

    function loadImage(src, cb) {
      if (!src) { cb(null); return; }
      var img = new Image();
      // 封面来自本站（同源）或带 CORS 头的 CDN 时可用；其它情况画不进去也不影响海报
      img.crossOrigin = 'anonymous';
      img.onload = function () { cb(img); };
      img.onerror = function () { cb(null); };
      img.src = src;
    }

    function drawQrTo(ctx, text, x, y, size, done) {
      var callDone = function () { if (done) { done(); done = null; } };
      if (typeof window.QRCode !== 'function') { callDone(); return; }
      var holder = document.createElement('div');
      holder.style.position = 'fixed';
      holder.style.left = '-9999px';
      holder.style.top = '0';
      document.body.appendChild(holder);
      try {
        new window.QRCode(holder, {
          text: text,
          width: size,
          height: size,
          correctLevel: window.QRCode.CorrectLevel ? window.QRCode.CorrectLevel.M : 0
        });
      } catch (err) {
        if (holder.parentNode) holder.parentNode.removeChild(holder);
        callDone();
        return;
      }
      // qrcodejs 同步画好 canvas，取出来贴到海报上
      var src = holder.querySelector('canvas');
      if (src) {
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(x - 8, y - 8, size + 16, size + 16);
        ctx.drawImage(src, x, y, size, size);
      }
      if (holder.parentNode) holder.parentNode.removeChild(holder);
      callDone();
    }

    // 导出并展示海报。
    // 关键：**展示的是 <img>，不是 <canvas>**。Safari（iOS / macOS）只对 <img>
    // 提供「存储到照片 / 存储图像」的长按菜单，对 <canvas> 长按没有任何保存选项——
    // 用户说的「长按根本长按不了」就是这个原因。所以这里把画布转成图片再显示。
    // 显示用 data URL：iOS / macOS Safari 对 data: 图片的长按「存储图像」支持最广，
    // blob: 在部分 iOS 版本上长按不会出现保存项。
    function finishPoster(cv, stage) {
      if (!stage) return;
      revokePosterBlob();

      function show(url) {
        posterUrl = url;
        var img = document.createElement('img');
        img.className = 'wf-poster__canvas';
        img.alt = current.title ? String(current.title) : '海报';
        img.src = url;
        stage.appendChild(img);
        var save = posterSave();
        if (save) save.hidden = false;
      }

      // 画布被跨域图片污染时 toDataURL 会失败，这时退回直接显示画布
      function fallbackToCanvas() {
        posterUrl = '';
        cv.classList.add('wf-poster__canvas');
        stage.appendChild(cv);
        var save = posterSave();
        if (save) save.hidden = true;
        wfToptips('图片导出受限，请长按海报手动保存');
      }

      // data URL：给 <img> 显示 + Safari 长按保存
      var dataUrl = '';
      try { dataUrl = cv.toDataURL('image/png'); } catch (err) { dataUrl = ''; }
      if (!dataUrl) { fallbackToCanvas(); return; }

      // blob URL：给「保存图片」按钮下载（Safari 会忽略 data: 的 download 属性，
      // 但对同源 blob: 的 <a download> 会正常下载，桌面 Chromium 亦然）。
      try {
        var bin = atob(dataUrl.split(',')[1]);
        var arr = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) arr[i] = bin.charCodeAt(i);
        var blob = new Blob([arr], { type: 'image/png' });
        posterBlobUrl = URL.createObjectURL(blob);
      } catch (e) {
        posterBlobUrl = '';
      }

      show(dataUrl);
    }

    // 保存海报：优先用 blob URL + <a download>（Safari / Chrome / Edge / Firefox 均可）。
    // 老浏览器抛异常时退回 data URL，再不行就只靠长按保存。
    function posterFilename() {
      return (current.title || 'poster').replace(/[\\/:*?"<>|\s]+/g, '_').slice(0, 60) + '.png';
    }

    document.addEventListener('click', function (e) {
      if (!e.target.closest) return;

      if (e.target.closest('[data-poster-close]')) {
        e.preventDefault();
        closePoster();
        return;
      }

      var saveBtn = e.target.closest('[data-poster-save]');
      if (!saveBtn) return;
      e.preventDefault();
      var href = posterBlobUrl || posterUrl;
      if (!href) return;

      var a = document.createElement('a');
      a.href = href;
      a.download = posterFilename();
      a.rel = 'noopener';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') close();
    });
  }

  /* ============ 侧边悬浮控制：外观设置 + 返回顶部 ============ */
  var THEME_KEY = 'wf_theme_mode';
  var themeMedia = null;
  var themeCfg = window.__wfThemeCfg || {};

  function resolveTheme(mode) {
    if (mode === 'light' || mode === 'dark') return mode;
    if (mode === 'time') {
      var h = new Date().getHours();
      var a = typeof themeCfg.lightStart === 'number' ? themeCfg.lightStart : 7;
      var b = typeof themeCfg.lightEnd === 'number' ? themeCfg.lightEnd : 19;
      var isLight = (a <= b) ? (h >= a && h < b) : (h >= a || h < b);
      return isLight ? 'light' : 'dark';
    }
    // system（默认）
    try {
      return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    } catch (e) {
      return 'light';
    }
  }

  function readThemeMode() {
    // 后台关掉切换按钮时，一律用后台设定的默认配色，忽略本地记录
    if (themeCfg.allowSwitch === false) {
      var d = themeCfg.def;
      if (d === 'system' || d === 'time' || d === 'light' || d === 'dark') return d;
      return 'system';
    }
    var mode = null;
    try { mode = localStorage.getItem(THEME_KEY); } catch (e) { /* ignore */ }
    if (mode !== 'system' && mode !== 'time' && mode !== 'light' && mode !== 'dark') {
      mode = null;
      var def = themeCfg.def;
      if (def === 'system' || def === 'time' || def === 'light' || def === 'dark') mode = def;
      if (!mode) mode = 'system';
    }
    return mode;
  }

  function syncThemePanel() {
    var mode = readThemeMode();
    document.querySelectorAll('.wf-fab__opt').forEach(function (btn) {
      var on = btn.getAttribute('data-theme-mode') === mode;
      btn.classList.toggle('is-active', on);
      btn.setAttribute('aria-checked', on ? 'true' : 'false');
    });
  }

  function applyThemeMode(mode, persist) {
    var resolved = resolveTheme(mode);
    document.documentElement.setAttribute('data-wf-theme', resolved);
    if (document.body) document.body.setAttribute('data-weui-theme', resolved);
    if (persist) {
      try { localStorage.setItem(THEME_KEY, mode); } catch (e) { /* ignore */ }
    }
    syncThemePanel();
    syncAdaptiveImages(resolved);
  }

  /* ---------- 图片随明暗主题自动切换 ----------
     用法：在任意 <img> 上写两个自定义属性即可，无需 JS：
         <img light-src="a.jpg" dark-src="b.jpg" alt="">
     浅色主题显示 light-src，深色主题显示 dark-src；主题切换时自动替换 src。
     兼容：① 运行时插入的图片（MutationObserver 监听）；② PJAX 换页后新出现的图片；
     ③ 只写一个 src 的情况（缺省回退到另一个，不闪空图）。 */
  var ADAPT_OBS = null;
  function currentThemeResolved() {
    var r = document.documentElement.getAttribute('data-wf-theme');
    if (r === 'light' || r === 'dark') return r;
    try { return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
    catch (e) { return 'light'; }
  }

  function adaptOneImage(img) {
    if (!img || img.tagName !== 'IMG') return;
    var dark = img.getAttribute('dark-src');
    var light = img.getAttribute('light-src');
    if (!dark && !light) return;                       // 非自适应图片，跳过
    var theme = currentThemeResolved();
    var want = (theme === 'dark') ? (dark || light) : (light || dark);
    if (!want) return;
    // 记录原始选择，避免重复赋值导致闪烁；值不同才替换
    if (img.getAttribute('src') !== want || img._wfAdaptiveWant !== want) {
      img._wfAdaptiveWant = want;
      img.setAttribute('src', want);
    }
    img.setAttribute('data-wf-adaptive', theme);
  }

  function syncAdaptiveImages(theme) {
    var list = document.querySelectorAll('img[dark-src], img[light-src]');
    for (var i = 0; i < list.length; i++) adaptOneImage(list[i]);
  }

  // 监听 DOM 变化：运行时插入/替换的 <img> 也自动适配（含 PJAX 与插到页尾的图片）
  function observeAdaptiveImages() {
    if (ADAPT_OBS || typeof MutationObserver === 'undefined') return;
    ADAPT_OBS = new MutationObserver(function (muts) {
      for (var i = 0; i < muts.length; i++) {
        var m = muts[i];
        if (m.type === 'attributes' && m.target && m.target.tagName === 'IMG' &&
            (m.attributeName === 'dark-src' || m.attributeName === 'light-src')) {
          adaptOneImage(m.target);
        }
        if (m.addedNodes && m.addedNodes.length) {
          for (var j = 0; j < m.addedNodes.length; j++) {
            var n = m.addedNodes[j];
            if (n.nodeType !== 1) continue;
            if (n.tagName === 'IMG') adaptOneImage(n);
            else if (n.querySelectorAll) {
              var inner = n.querySelectorAll('img[dark-src], img[light-src]');
              for (var k = 0; k < inner.length; k++) adaptOneImage(inner[k]);
            }
          }
        }
      }
    });
    ADAPT_OBS.observe(document.documentElement, {
      childList: true, subtree: true,
      attributes: true, attributeFilter: ['dark-src', 'light-src']
    });
  }

  function initTheme() {
    applyThemeMode(readThemeMode(), false);
    // 自适应图片：立即同步一次 + 监听后续插入（运行时/ PJAX / 页尾手插的 <img>）
    syncAdaptiveImages();
    observeAdaptiveImages();

    // 跟随系统：系统切换时实时更新（只初始化一次监听）
    if (!themeMedia) {
      try {
        themeMedia = window.matchMedia('(prefers-color-scheme: dark)');
        var onChange = function () {
          if (readThemeMode() === 'system') applyThemeMode('system', false);
        };
        if (themeMedia.addEventListener) themeMedia.addEventListener('change', onChange);
        else if (themeMedia.addListener) themeMedia.addListener(onChange);
      } catch (e) { /* ignore */ }
    }

    // 按时间自动：跨过早晚分界时自动切换（每分钟检查一次）
    if (!initTheme._timer) {
      initTheme._timer = setInterval(function () {
        if (readThemeMode() === 'time') applyThemeMode('time', false);
      }, 60000);
    }
  }

  function bindFabDocOnce() {
    if (bindFabDocOnce._done) return;
    bindFabDocOnce._done = true;

    function closePanel() {
      var panel = document.getElementById('wf-fab-panel');
      var btn = document.querySelector('[data-fab-theme]');
      if (!panel) return;
      panel.classList.remove('is-open');
      if (btn) btn.setAttribute('aria-expanded', 'false');
    }

    document.addEventListener('click', function (e) {
      if (!e.target.closest) return;
      if (e.target.closest('#wf-fab')) return;
      closePanel();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closePanel();
    });

    var mainBtn = document.querySelector('[data-fab-theme]');
    if (mainBtn) {
      mainBtn.addEventListener('click', function (e) {
        e.preventDefault();
        var panel = document.getElementById('wf-fab-panel');
        if (!panel) return;
        var open = panel.hidden || !panel.classList.contains('is-open');
        // hidden 只用于首帧；之后统一用 class 控制显隐动画
        panel.hidden = false;
        if (open) {
          closeFabPanels('theme');   // 互斥：先关其它面板
          syncThemePanel();
          panel.classList.add('is-open');
          mainBtn.setAttribute('aria-expanded', 'true');
        } else {
          panel.classList.remove('is-open');
          mainBtn.setAttribute('aria-expanded', 'false');
        }
      });
    }

    document.querySelectorAll('.wf-fab__opt').forEach(function (opt) {
      opt.addEventListener('click', function (e) {
        e.preventDefault();
        var mode = opt.getAttribute('data-theme-mode');
        applyThemeMode(mode, true);
        closePanel();
      });
    });

    // 配色面板右上角关闭按钮
    var closeBtn = document.querySelector('[data-fab-theme-close]');
    if (closeBtn) {
      closeBtn.addEventListener('click', function (e) {
        e.preventDefault();
        closePanel();
      });
    }
  }

  /* ============ 文章目录 ============
     服务端已在正文 h2/h3/h4 上写好 id，并渲染了目录面板（wf-toc）。
     目录按钮/面板在 .page 之外，PJAX 换页不会替换它，所以这里在每次 init 时
     依据当前正文重新生成/校正目录内容，并控制按钮与面板的显隐、滚动高亮。
     这是显隐的**唯一权威**：无论整页加载还是 PJAX 换页，都按当前
     .wf-post-content 的标题数决定按钮是否出现——避免出现
     「无刷新进文章不显示 / 无刷新离开文章按钮还在」。 */
  var tocState = null;

  function initToc() {
    var fab = document.getElementById('wf-fab');
    if (!fab) return;
    var btn = fab.querySelector('[data-fab-toc]');
    var panel = document.getElementById('wf-toc');
    // 按钮/面板始终存在于 DOM（footer.php 里不再按页面条件渲染），
    // 但老版本可能没有，稳妥起见还是判空。
    if (!btn || !panel) return;

    var list = panel.querySelector('.wf-toc__list');

    // 先重置：断开上一次的观察器、清掉服务端/上一次残留的列表内容、收起面板。
    if (tocState && tocState.observer) tocState.observer.disconnect();
    tocState = null;
    if (list) list.innerHTML = '';
    panel.classList.remove('is-open');
    panel.hidden = true;
    btn.setAttribute('aria-expanded', 'false');

    // 收集当前正文的标题（h2/h3/h4）
    var content = document.querySelector('.wf-post-content');
    var heads = content
      ? content.querySelectorAll('h2[id], h3[id], h4[id]')
      : [];
    var items = [];
    Array.prototype.forEach.call(heads, function (h) {
      if (!h.id) return;
      items.push({ id: h.id, text: (h.textContent || '').trim(), level: parseInt(h.tagName.slice(1), 10) });
    });

    // 少于两个标题不给目录（列表已在上方清空，按钮也一并藏起来）
    if (items.length < 2) {
      btn.hidden = true;
      return;
    }
    btn.hidden = false;

    // 重建列表（覆盖服务端初始内容，保证 PJAX 后也是最新的）
    if (list) {
      items.forEach(function (it) {
        var li = document.createElement('li');
        li.className = 'wf-toc__item wf-toc__item--h' + it.level;
        var a = document.createElement('a');
        a.setAttribute('href', '#' + it.id);
        a.setAttribute('data-toc-target', it.id);
        a.textContent = it.text;
        li.appendChild(a);
        list.appendChild(li);
      });
    }

    // 绑定滚动高亮（PJAX 后重新建立）
    tocState = { panel: panel, items: items, activeId: '', observer: null };

    var headEls = items.map(function (it) { return document.getElementById(it.id); }).filter(Boolean);
    if ('IntersectionObserver' in window && headEls.length) {
      tocState.observer = new IntersectionObserver(function (entries) {
        // 取当前视口内最靠上的标题为「当前章节」
        var visible = entries.filter(function (en) { return en.isIntersecting; });
        if (!visible.length) return;
        visible.sort(function (a, b) {
          return a.target.getBoundingClientRect().top - b.target.getBoundingClientRect().top;
        });
        setActiveToc(visible[0].target.id);
      }, { rootMargin: '-72px 0px -70% 0px', threshold: 0 });
      headEls.forEach(function (h) { tocState.observer.observe(h); });
    }
  }

  function setActiveToc(id) {
    if (!tocState || tocState.activeId === id) return;
    tocState.activeId = id;
    tocState.panel.querySelectorAll('.wf-toc__item').forEach(function (li) {
      var a = li.querySelector('a');
      var on = a && a.getAttribute('data-toc-target') === id;
      li.classList.toggle('is-active', on);
    });
  }

  function bindTocOnce() {
    if (bindTocOnce._done) return;
    bindTocOnce._done = true;

    function closeToc() {
      var panel = document.getElementById('wf-toc');
      var btn = document.querySelector('[data-fab-toc]');
      if (!panel) return;
      panel.classList.remove('is-open');
      if (btn) btn.setAttribute('aria-expanded', 'false');
    }

    document.addEventListener('click', function (e) {
      if (!e.target.closest) return;

      // 收起按钮
      if (e.target.closest('[data-toc-close]')) {
        e.preventDefault();
        closeToc();
        return;
      }

      // 目录按钮：开合面板
      var tocBtn = e.target.closest('[data-fab-toc]');
      if (tocBtn) {
        e.preventDefault();
        var panel = document.getElementById('wf-toc');
        if (!panel) return;
        var open = panel.hidden || !panel.classList.contains('is-open');
        panel.hidden = false;
        if (open) closeFabPanels('toc');   // 互斥：先关其它面板
        panel.classList.toggle('is-open', open);
        tocBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        return;
      }

      // 点击目录项：平滑滚动到对应标题（不触发 PJAX）
      var link = e.target.closest('[data-toc-target]');
      if (link && e.target.closest('#wf-toc')) {
        var id = link.getAttribute('data-toc-target');
        var target = document.getElementById(id);
        if (target) {
          e.preventDefault();
          e.stopPropagation();
          try { history.replaceState(null, '', '#' + id); } catch (err) { /* ignore */ }
          var top = target.getBoundingClientRect().top + (window.pageYOffset || window.scrollY || 0) - 16;
          try { window.scrollTo({ top: top, behavior: 'smooth' }); }
          catch (err2) { window.scrollTo(0, top); }
          if (window.innerWidth < 641) closeToc();
        }
        return;
      }

      // 点面板/按钮以外区域收起
      if (!e.target.closest('#wf-fab')) closeToc();
    }, true);

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeToc();
    });
  }

  /* ============ 音乐播放器（自研，不依赖 APlayer） ============
     - 面板与 <audio> 挂在 #wf-fab（.page 之外）→ PJAX 换页不会重建，播放不中断。
     - initMusic() 幂等：已初始化直接返回，绝不因 runInit 而重建，保证不断歌。
     - 歌单/播放地址/歌词都走本站代理（?weui_music=...，见 functions.php 的
       weuiMusicHandle）：歌单返回的每首歌含 url/cover/lrc（均指向本站代理）。
     - 歌词由本模块自行解析 LRC 并渲染到贴底的 #wf-lrc。 */
  var music = null; // { audio, songs, index, mode, ... }

  function musicCfg() {
    return window.__wfMusicCfg || null;
  }

  // ---- 本地记忆（歌词开关 / 播放模式 / 上次歌曲与进度）----
  var MK = {
    lyric: 'wf_music_lyric',
    mode: 'wf_music_mode',
    last: 'wf_music_last'    // { index, time }
  };
  function mGet(key, def) {
    try { var v = localStorage.getItem(key); return v === null ? def : v; } catch (e) { return def; }
  }
  function mSet(key, val) {
    try { localStorage.setItem(key, String(val)); } catch (e) { /* ignore */ }
  }

  // 构造站内代理地址：?weui_music=<type>&server=&id=&auth=&v=<版本>
  // v 为返回结构版本号：改动返回字段时同步 +1，可让浏览器 HTTP 缓存立即失效（URL 变化即回源）。
  var MUSIC_API_V = 2;
  function proxyMusicUrl(item, type, id) {
    var cfg = musicCfg();
    var base = (cfg && cfg.endpoint) ? cfg.endpoint : (location.origin + '/?weui_music=');
    var server = item.server || 'netease';
    var qid = id || item.id;
    return base + encodeURIComponent(type) +
      '&server=' + encodeURIComponent(server) +
      '&id=' + encodeURIComponent(qid) +
      '&auth=' + encodeURIComponent(item.auth || '') +
      '&v=' + MUSIC_API_V;
  }

  function musicJson(url) {
    return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .catch(function () { return null; });
  }

  function fmtTime(sec) {
    sec = Math.max(0, Math.floor(sec || 0));
    var m = Math.floor(sec / 60), s = sec % 60;
    return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
  }

  /* ---------- LRC 解析 ----------
     返回 [{t: 秒, text: '行', type: 'm'|'g'|'t'|'', dur: 秒}]，按时间升序；无时间轴的行忽略。
     配色沿用明月浩空的那套：男→m（蓝）女→g（红）合→t（品红）。

     演唱者识别（这是「只有第一句对」的修复重点）：
       LRC 的常规写法是**只在一段的第一句写标记**，后面同一个人唱的行不带标记：
         [00:24.71]男：花落翩翩 岁月荏苒
         [00:29.40]雨点依稀视线          ← 没标记，应该继续算「男」
         [00:34.77]一句诺言 厮守不变      ← 同上
         [01:06.43]女：落叶两片三片        ← 换人
       所以解析出的 type 必须**按段接力**：没标记的行继承上一句的演唱者，
       遇到新标记才切换。老实现每行只看自己，没标记就 type='' → 颜色全丢。

     另外还有两种常见写法，也一并支持：
       · 独占一行的标记：[00:38.58]小鱼：  ← 这行本身不是歌词，不显示；
         同时把这个名字登记下来，后面若以「小鱼：歌词」内联出现也能认出来；
       · 括号标记：（男）/［女］/【合】/ (M) / [F]
       · 英文标记：M: / F: / A: / D: / Male: / Female: / Duet: / All: / Both:
       · 词：曲：编曲：编曲：这类是歌曲信息行，不是演唱者 —— 不参与接力、
         也不着色（否则会被当成「第一个演唱者」而错位）。 */
  var LRC_ACTORS = {
    '男': 'm', '男声': 'm', '男生': 'm', '男合': 'm', 'm': 'm', 'male': 'm', 'man': 'm', 'boy': 'm',
    '女': 'g', '女声': 'g', '女生': 'g', '女合': 'g', 'f': 'g', 'female': 'g', 'woman': 'g', 'girl': 'g',
    '合': 't', '合唱': 't', '合声': 't', '齐唱': 't', 'a': 't', 'd': 't', 'all': 't', 'both': 't',
    'duet': 't', 'chorus': 't', 'together': 't'
  };
  // 歌曲信息行（不是演唱者）
  var LRC_META = ['作词', '作曲', '编曲', '词', '曲', '词曲', '制作人', '原唱', '演唱', '监制', '混音',
    '母带', '吉他', '贝斯', '鼓', '键盘', '和声', '录音', '发行', '出品', '策划', '统筹', '文案',
    '封面', '设计', 'op', 'sp', 'op/sp', 'producer', 'composer', 'lyricist', 'arranger', 'mix'];
  var LRC_PALETTE = ['m', 'g', 't'];

  function lrcIsMeta(word) {
    var k = String(word || '').toLowerCase().replace(/\s+/g, '');
    return !!k && LRC_META.indexOf(k) !== -1;
  }

  // 把「谁在唱」的字面量映射成 m/g/t；自定义人名按首次出现顺序轮流分配三色
  function lrcActorType(word, names) {
    var w = String(word || '').trim();
    if (!w) return '';
    if (LRC_ACTORS[w.toLowerCase()]) return LRC_ACTORS[w.toLowerCase()];
    if (names && Object.prototype.hasOwnProperty.call(names, w)) {
      if (!names[w].type) names[w].type = LRC_PALETTE[names[w].i % LRC_PALETTE.length];
      return names[w].type;
    }
    return '';
  }

  /* 拆出行首的演唱者标记。返回：
       null                      → 这行没有标记，调用方沿用上一句的演唱者
       { meta: true }            → 歌曲信息行（词/曲/…），不着色、不接力
       { type, text, standalone} → 演唱者标记；standalone=true 表示整行只有标记，不显示 */
  function lrcSplitActor(txt, names) {
    // 括号标记：（男）/［女］/【合】/ (M) / [F]；前后多包一层括号也容错（如 [【女】]）
    var m = /^[（(\[【]+\s*([^:：\s]{1,12}?)\s*[）)\]】]+\s*[:：]?\s*([\s\S]*)$/.exec(txt);
    if (m) {
      var t1 = lrcActorType(m[1], names);
      if (t1) {
        var rest1 = String(m[2] || '').trim();
        return { type: t1, text: rest1, standalone: rest1 === '' };
      }
    }
    m = /^([^\s:：]{1,12})\s*[:：]\s*([\s\S]*)$/.exec(txt);
    if (m) {
      var word = m[1];
      if (lrcIsMeta(word)) return { meta: true };
      var t2 = lrcActorType(word, names);
      if (t2) {
        var rest2 = String(m[2] || '').trim();
        return { type: t2, text: rest2, standalone: rest2 === '' };
      }
    }
    return null;
  }

  function parseLrc(text) {
    var out = [];
    if (!text) return out;
    var raw = String(text).split(/\r?\n/);
    var re = /\[(\d{1,2}):(\d{1,2})(?:[.:](\d{1,3}))?\]/g;

    // 第一遍：登记「独占一行的演唱者名」（如 小鱼： / 宝二：），
    // 这样它们后面若以内联形式出现（小鱼：歌词）也能认出来
    var names = {};
    var order = 0;
    for (var a = 0; a < raw.length; a++) {
      var body = raw[a].replace(re, '').trim();
      var nm = /^([^\s:：]{1,12})\s*[:：]\s*$/.exec(body);
      if (nm && !lrcIsMeta(nm[1]) && !LRC_ACTORS[nm[1].toLowerCase()] &&
          !Object.prototype.hasOwnProperty.call(names, nm[1])) {
        names[nm[1]] = { i: order++, type: '' };
      }
    }

    var cur = '';   // 当前演唱者（接力用）
    for (var i = 0; i < raw.length; i++) {
      var line = raw[i];
      var times = [];
      var m;
      re.lastIndex = 0;
      while ((m = re.exec(line))) {
        var min = parseInt(m[1], 10), sec = parseInt(m[2], 10);
        var frac = m[3] ? parseFloat('0.' + m[3]) : 0;
        times.push(min * 60 + sec + frac);
      }
      var txt = line.replace(re, '').trim();
      if (!times.length || !txt) continue;

      var type = cur;
      var actor = lrcSplitActor(txt, names);
      if (actor) {
        if (actor.meta) {
          type = '';                 // 词/曲/…：不着色，也不改变接力状态
        } else {
          cur = actor.type;          // 换人（或第一次出现）→ 后面没标记的行继续用它
          type = actor.type;
          txt = actor.text;
          if (actor.standalone || !txt) continue;   // 纯标记行本身不是歌词，不显示
        }
      }

      for (var j = 0; j < times.length; j++) out.push({ t: times[j], text: txt, type: type });
    }
    out.sort(function (a, b) { return a.t - b.t; });
    // 每行时长 = 下一行开始时间 - 本行开始时间；最后一行兜底 4s（供逐字扫过使用）
    for (var k = 0; k < out.length; k++) {
      var nx = out[k + 1];
      var d = (nx ? nx.t : out[k].t + 4) - out[k].t;
      out[k].dur = d > 0 ? d : 4;
    }
    return out;
  }

  /* ---------- 侧边 FAB 面板互斥 ----------
     侧边按钮打开的栏目（音乐 / 设置 / 目录）同一时刻只允许一个展开：
     打开任意一个前，先把其它面板关掉。 */
  function closeFabPanels(except) {
    if (except !== 'music') {
      var mBox = document.getElementById('wf-music');
      if (mBox && mBox.classList.contains('is-open')) {
        mBox.classList.remove('is-open');
        var mBtn = document.querySelector('[data-fab-music]');
        if (mBtn) mBtn.setAttribute('aria-expanded', 'false');
      }
    }
    if (except !== 'theme') {
      var tPanel = document.getElementById('wf-fab-panel');
      if (tPanel && tPanel.classList.contains('is-open')) {
        tPanel.classList.remove('is-open');
        var tBtn = document.querySelector('[data-fab-theme]');
        if (tBtn) tBtn.setAttribute('aria-expanded', 'false');
      }
    }
    if (except !== 'toc') {
      var toc = document.getElementById('wf-toc');
      if (toc && toc.classList.contains('is-open')) {
        toc.classList.remove('is-open');
        var tocBtn = document.querySelector('[data-fab-toc]');
        if (tocBtn) tocBtn.setAttribute('aria-expanded', 'false');
      }
    }
  }

  // 面板开关（幂等，绑一次）
  function bindMusicFabOnce() {
    if (bindMusicFabOnce._done) return;
    bindMusicFabOnce._done = true;

    function setOpen(open) {
      var box = document.getElementById('wf-music');
      var btn = document.querySelector('[data-fab-music]');
      if (!box) return;
      box.hidden = false;
      box.classList.toggle('is-open', !!open);
      if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      // 打开面板且有歌曲时：自动定位到当前播放条目（如有「上次播放记录」也能滚到对应条目）。
      // 面板刚显示时列表高度才有值，故延后一帧再定位。
      if (open) {
        // 首次打开面板时才渲染歌单（若之前因面板未开而延后）
        if (music && music.listPending) {
          music.listPending = false;
          renderMusicList();
        }
        requestAnimationFrame(function () {
          if (music && music.songs && music.songs.length) locateCurrentItem(true);
        });
      }
    }

    document.addEventListener('click', function (e) {
      if (!e.target.closest) return;
      if (e.target.closest('[data-fab-music]')) {
        e.preventDefault();
        var box = document.getElementById('wf-music');
        if (!box) return;
        var willOpen = !box.classList.contains('is-open');
        if (willOpen) closeFabPanels('music');   // 互斥：先关其它面板
        setOpen(willOpen);
        return;
      }
      if (e.target.closest('[data-fab-music-close]')) { e.preventDefault(); setOpen(false); return; }
      if (e.target.closest('#wf-music') || e.target.closest('#wf-fab')) return;
      setOpen(false);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') setOpen(false);
    });
  }

  function renderMusicList() {
    var list = document.getElementById('wf-music-list');
    if (!list || !music) return;
    list.innerHTML = '';
    list.removeAttribute('data-loading');
    music.songs.forEach(function (s, i) {
      var li = document.createElement('li');
      li.className = 'wf-music__item';
      li.setAttribute('data-index', i);

      // 封面小图：走自研懒加载（占位图 + 进视口才换真图），与全站图片一致
      var thumb = document.createElement('span');
      thumb.className = 'wf-music__item-thumb';
      if (s.cover) {
        var im = document.createElement('img');
        im.className = 'wf-music__item-img wf-img';
        im.setAttribute('loading', 'lazy');
        im.setAttribute('decoding', 'async');
        im.setAttribute('alt', '');
        im.setAttribute('src', WF_IMG_PLACEHOLDER);
        im.setAttribute('data-wf-src', s.cover);
        thumb.appendChild(im);
      }
      li.appendChild(thumb);

      var box = document.createElement('span');
      box.className = 'wf-music__item-box';
      var name = document.createElement('span');
      name.className = 'wf-music__item-name';
      name.textContent = s.name || ('曲目 ' + (i + 1));
      var meta = document.createElement('span');
      meta.className = 'wf-music__item-meta';
      var parts = [];
      if (s.album) parts.push(s.album);
      if (s.artist) parts.push(s.artist);
      meta.textContent = parts.join(' · ');
      box.appendChild(name);
      box.appendChild(meta);
      li.appendChild(box);

      list.appendChild(li);
    });
    highlightMusicItem();
    // 新渲染的封面带 .wf-img（占位图 + data-wf-src），交给懒加载观察器
    if (typeof initLazy === 'function') initLazy();
    // 若面板已打开（例如先点开面板、歌单稍后才加载完），渲染完静默定位到当前条目
    var box = document.getElementById('wf-music');
    if (box && box.classList.contains('is-open')) {
      requestAnimationFrame(function () { locateCurrentItem(true); });
    }
  }

  function highlightMusicItem() {
    var list = document.getElementById('wf-music-list');
    if (!list || !music) return;
    Array.prototype.forEach.call(list.querySelectorAll('.wf-music__item'), function (li) {
      var on = Number(li.getAttribute('data-index')) === music.index;
      li.classList.toggle('is-active', on);
    });
  }

  // 定位到当前播放条目：把它滚动到列表可视区，并短暂高亮提示。
  // silent=true 时用即时滚动且不闪高亮（用于「打开面板自动定位」，避免开面板就闪一下/平滑滚动卡顿）。
  function locateCurrentItem(silent) {
    var list = document.getElementById('wf-music-list');
    if (!list || !music) return;
    var cur = list.querySelector('.wf-music__item[data-index="' + music.index + '"]');
    if (!cur) return;
    // 让当前项尽量居中显示
    var top = cur.offsetTop - (list.clientHeight - cur.offsetHeight) / 2;
    if (top < 0) top = 0;
    if (silent) {
      list.scrollTop = top;
      return;
    }
    try { list.scrollTo({ top: top, behavior: 'smooth' }); }
    catch (e) { list.scrollTop = top; }
    cur.classList.add('is-locating');
    setTimeout(function () { cur.classList.remove('is-locating'); }, 900);
  }

  function updateNow(song) {
    var cover = document.getElementById('wf-music-cover');
    var name = document.getElementById('wf-music-name');
    var artist = document.getElementById('wf-music-artist');
    if (!song) return;
    if (name) name.textContent = song.name || '';
    if (artist) {
      // 显示「专辑 · 作者」；缺专辑时只显示作者
      var parts = [];
      if (song.album) parts.push(song.album);
      if (song.artist) parts.push(song.artist);
      artist.textContent = parts.join(' · ');
    }
    if (cover) cover.style.backgroundImage = song.cover ? ('url("' + song.cover + '")') : '';
  }

  /* SVG 元素的 hidden 是「无效属性」：对 <svg> 设 el.hidden=true 不会反射到 hidden 特性，
     所以 svg[hidden]{display:none} 永远不匹配（HTML 元素才会反射）。统一用特性读写显隐。 */
  function hideEl(el, hidden) {
    if (!el) return;
    if (hidden) el.setAttribute('hidden', '');
    else el.removeAttribute('hidden');
  }

  function setPlayIcon(playing) {
    var btn = document.querySelector('[data-music-toggle]');
    if (!btn) return;
    hideEl(btn.querySelector('.wf-music__icon-play'), !!playing);
    hideEl(btn.querySelector('.wf-music__icon-pause'), !playing);
    btn.setAttribute('aria-label', playing ? '暂停' : '播放');
  }

  // 播放模式的图标刷新（list / random / single 三态）——纯图标、无色、无文字
  function syncModeUI() {
    if (!music) return;
    var btn = document.querySelector('[data-music-mode]');
    if (!btn) return;
    btn.setAttribute('data-mode', music.mode);
    ['list', 'random', 'single'].forEach(function (m) {
      hideEl(btn.querySelector('[data-mode-' + m + ']'), m !== music.mode);
    });
    var label = music.mode === 'random' ? '随机播放' : (music.mode === 'single' ? '单曲循环' : '列表循环');
    btn.setAttribute('aria-label', label);
    btn.setAttribute('title', label);
  }

  // 载入第 i 首（不自动播），并拉歌词；startAt 用于「接着上次播」
  function loadSong(i, autoplay, startAt) {
    if (!music || !music.songs.length) return;
    if (i < 0) i = music.songs.length - 1;
    if (i >= music.songs.length) i = 0;
    // 先存「当前这首」的进度，再切换 —— 切完再存就变成把新歌存进去了
    saveLast();
    music.index = i;
    var song = music.songs[i];
    music.audio.src = song.url;
    music._pendingSeek = (typeof startAt === 'number' && startAt > 1) ? startAt : 0;
    music._seekTries = 0;
    updateNow(song);
    highlightMusicItem();
    // 面板开着时，切歌自动把列表滚到当前条（像音乐 App 那样跟随）
    var mBox = document.getElementById('wf-music');
    if (mBox && mBox.classList.contains('is-open')) locateCurrentItem();
    var seek = document.getElementById('wf-music-seek');
    if (seek) { seek.value = 0; seek.style.setProperty('--wf-music-seek', '0%'); }
    var cur = document.getElementById('wf-music-cur');
    if (cur) cur.textContent = '00:00';
    loadLyric(song);
    if (autoplay) playPause(true);
  }

  function playPause(forcePlay) {
    if (!music) return;
    var a = music.audio;
    // 明确按当前「暂停状态」决定动作，避免图标与实际状态不一致
    var wantPlay = (forcePlay === true) || (forcePlay !== false && a.paused);
    if (wantPlay && a.paused) {
      var p = a.play();
      if (p && p.catch) p.catch(function () { setPlayIcon(false); });
    } else if (!wantPlay && !a.paused) {
      a.pause();
    }
    // 立刻同步图标（不等事件，消除按钮不同步的 bug）
    setPlayIcon(!a.paused);
    // 歌词随播放显隐：暂停即隐藏（见 applyLrcPlaying）
    applyLrcPlaying(!a.paused);
  }

  // 下一首 / 上一首：按播放模式决定
  function nextSong(dir) {
    if (!music) return;
    if (music.mode === 'random' && music.songs.length > 1) {
      var n;
      do { n = Math.floor(Math.random() * music.songs.length); } while (n === music.index);
      loadSong(n, true);
      return;
    }
    loadSong(music.index + dir, true);
  }

  // 一首播完：单曲循环重播本曲；否则下一首
  function onEnded() {
    if (!music) return;
    if (music.mode === 'single') {
      music.audio.currentTime = 0;
      playPause(true);
      return;
    }
    nextSong(1);
  }

  /* ---------- 「接着上次播」的进度记忆 ----------
     记录结构（localStorage 的 wf_music_last）：
       { id, index, time,               // 最近一次播放的歌与进度
         map: { "<歌曲key>": 秒 } }      // 每首歌各自的进度，换歌不会互相覆盖
     以前「有时会失效」的两个根因：
       1) loadSong() 是在切到新歌之后才 saveLast()，存下去的是「新歌 + 旧进度」；
          更致命的是页面加载恢复进度时——读出来的进度还没 seek 生效，就先被
          saveLast() 用 0 覆盖掉了，于是「这次看着是恢复了，下次再进就没了」。
       2) 另一个标签页开始播放会把本页 pause，本页若从未播过（currentTime=0），
          pause 里的 saveLast() 就把有效进度写成了 0。
     所以统一改成：只有「真的播过（>2 秒）」才落盘，且不写 0 覆盖已有进度。 */
  function progressMap() {
    var raw = {};
    try { raw = JSON.parse(mGet(MK.last, 'null')) || {}; } catch (e) { raw = {}; }
    if (!raw.map || typeof raw.map !== 'object') raw.map = {};
    return raw;
  }

  function saveLast() {
    if (!music || !music.songs.length) return;
    var song = music.songs[music.index];
    if (!song) return;
    var t = Math.floor(music.audio.currentTime || 0);
    // 切歌、刚载入、被别的标签页暂停等「其实没播起来」的情况：不写，避免冲掉已存进度
    if (t <= 2) return;
    var key = song._key || ('#' + music.index);
    var rec = progressMap();
    rec.map[key] = t;
    mSet(MK.last, JSON.stringify({
      id: song._key || music.index,
      index: music.index,
      time: t,
      map: rec.map
    }));
  }

  /* 「接着上次播」的定位：元数据就绪后 seek 到记下的进度。
     坑：preload="metadata" 时数据还没缓冲够，`currentTime = x` 会被「接受但位置不生效」
     （赋值不报错、实际仍是 0），所以不能赋完就算成功。这里改成：
       · 在 loadedmetadata / canplay / play 上反复尝试（有次数上限，避免死循环）；
       · 每次 timeupdate / seeked 校验一次，真正到位了才清掉 _pendingSeek。 */
  function applyPendingSeek() {
    if (!music || !music._pendingSeek) return;
    var a = music.audio;
    var want = music._pendingSeek;
    // 目标已经超过总时长（歌单换了 / 版本不同）→ 作废，从头播
    if (a.duration && want >= a.duration - 1) { music._pendingSeek = 0; return; }
    music._seekTries = (music._seekTries || 0) + 1;
    try { a.currentTime = want; } catch (e) { return; }
    if (music._seekTries >= 8) { music._pendingSeek = 0; music._seekTries = 0; }
  }

  // 校验是否真的定位到位了（到位才结束「待定位」状态）
  function verifyPendingSeek() {
    if (!music || !music._pendingSeek) return;
    var a = music.audio;
    if (Math.abs(a.currentTime - music._pendingSeek) < 1.5) {
      music._pendingSeek = 0;
      music._seekTries = 0;
    }
  }

  /* ---------- 贴底歌词（单行 + 逐字扫过，仿 myhkw.cn） ----------
     每行渲染成：底层暗字 .wf-lrc__base + 上层亮字 .wf-lrc__hl（overflow:hidden）。
     播放时把 .wf-lrc__hl 的宽度按「本行已播时长 / 本行时长」推进 → 亮字从左扫到右。
     行时长用「下一行开始时间 - 本行开始时间」估算（标准 LRC 只有行开始时间）。 */
  function loadLyric(song) {
    var dock = document.getElementById('wf-lrc');
    music.lrc = [];
    music.lrcIndex = -1;
    if (!dock) return;
    dock.innerHTML = '';
    dock.classList.remove('is-on');
    if (!song || !song.lrc) return;
    song._lrcPromise = fetch(song.lrc, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
      .then(function (r) { return r.text(); })
      .then(function (text) {
        if (!music || music.songs[music.index] !== song) return; // 换歌期间又切走了
        music.lrc = parseLrc(text);
        music.lrcIndex = -1;
        renderLrc();
      })
      .catch(function () { /* 无歌词不影响播放 */ });
  }

  // 歌词是否开启（前台记忆优先，其次后台默认）
  function lyricOn() {
    var v = mGet(MK.lyric, null);
    if (v === '1') return true;
    if (v === '0') return false;
    var cfg = musicCfg();
    return !!(cfg && cfg.lyric);
  }

  // 应用歌词开关到贴底条与开关按钮
  function applyLyricVisibility() {
    var btn = document.querySelector('[data-music-lyric-toggle]');
    var on = lyricOn();
    if (btn) {
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
      btn.classList.toggle('is-off', !on);
    }
    syncLrcDock();
  }

  // 渲染当前行（单行）。index 变化时重建 DOM。
  /* ---------- 贴底歌词（单行，调度逻辑按 myhkw.cn 复刻）----------
     myhkw 真实调度（解包源码核实）：
       · KSC(逐字) 与 LRC(普通) 两套**互斥**，由接口数据 type 决定；逐字扫过只属 KSC。
       · 命中当前行时：整行 opacity 0→1 + scale(1.01)→scale(1)（transition .5s）**与**
         逐字扫过**并发**（不是串行）。
       · 逐字：每个字 <em> 宽度 0→100%，时长 = 该字 dir（≈行时长/字数）；从左到右接力。
       · 行尾 → **停留 500ms** → 淡出退场（setTimeout 500 后移除 myhknow）。
       · letterfx 逐字入场**仅普通 LRC 且 letterfx==1** 时启用，按「字数/每字时长」分三档：
           字数>6 且 每字>500ms → smear(+rewind)；字数>6 且 每字≤500ms → ticker；字数≤6 → wave。
     本实现：单行贴底展示，忠实复刻「并发入场 + 逐字扫过 + 行尾停留 500ms 再淡出」的时间线，
     并按三档规则施加 letterfx 逐字入场。 */
  function chromaForLine(line) {
    if (line && line.type === 'm') return { hl: '#3b82f6', glow: 'rgba(59,130,246,0.9)' };   // 男（蓝，仿 myhkw #00f）
    if (line && line.type === 'g') return { hl: '#ff4d4f', glow: 'rgba(255,77,79,0.9)' };     // 女（红，仿 myhkw red）
    if (line && line.type === 't') return { hl: '#ff5ac8', glow: 'rgba(255,90,200,0.9)' };    // 合（品红，仿 myhkw #f0f）
    // 默认：主题品牌色（与主题一致），稍作立体阴影
    var brand = '#07c160';
    return { hl: brand, glow: 'rgba(7,193,96,0.9)' };
  }

  // 把一行拆成 token：中文/符号按字拆，英文单词/数字整体不拆，空白单独成 token（不参与逐字）。
  function splitChars(text) {
    var raw = String(text).match(/[A-Za-z0-9']+|\s+|[^\s]/g) || [];
    var out = [];
    for (var i = 0; i < raw.length; i++) {
      out.push({ s: raw[i], ch: !/^\s+$/.test(raw[i]) });
    }
    return out;
  }

  function buildLrcLayer(className, tokens) {
    var layer = document.createElement('span');
    layer.className = className;
    var idx = 0;
    for (var i = 0; i < tokens.length; i++) {
      var t = tokens[i];
      if (!t.ch) { layer.appendChild(document.createTextNode(t.s)); continue; }
      var em = document.createElement('em');
      em.className = className + '-ch';
      em.setAttribute('data-idx', idx++);
      em.textContent = t.s;
      layer.appendChild(em);
    }
    return layer;
  }

  // 渲染当前行（单行）。index 变化时重建 DOM，并按 myhkw 时间线触发入场 + 逐字。
  function renderLrc() {
    var dock = document.getElementById('wf-lrc');
    if (!dock || !music) return;
    if (!music.lrc || !music.lrc.length || music.lrcIndex < 0 || !lyricOn() || !music._playing) {
      music._lrcEl = null;
      dock.classList.remove('is-on');
      dock.setAttribute('aria-hidden', 'true');
      setTimeout(function () { if (music && !music._lrcEl) { dock.innerHTML = ''; } }, 500);
      return;
    }
    var line = music.lrc[music.lrcIndex];
    var tokens = splitChars(line.text);
    var unitCount = 0;
    for (var q = 0; q < tokens.length; q++) if (tokens[q].ch) unitCount++;
    var chroma = chromaForLine(line);

    // 旧行：标记 is-out（放大淡出），**停留 500ms** 后移除（myhkw: setTimeout 500 移除 myhknow）。
    // 精确引用要退场的旧行；同时对容器做兜底清理，避免叠加。
    var prev = music._lrcEl && music._lrcEl.lineEl;
    if (prev && prev.parentNode === dock) {
      prev.classList.remove('is-cur');
      prev.classList.add('is-out');
      (function (p) { setTimeout(function () { if (p && p.parentNode) p.parentNode.removeChild(p); }, 500); })(prev);
    }
    var stay = dock.querySelectorAll('.wf-lrc__line');
    // 只保留刚标记 is-out 的上一行，其它残留（更早的）直接清掉
    for (var si2 = 0; si2 < stay.length; si2++) {
      if (stay[si2] !== prev) dock.removeChild(stay[si2]);
    }

    var wrap = document.createElement('span');
    wrap.className = 'wf-lrc__line is-cur lrc-' + (line.type || 'd');
    var base = buildLrcLayer('wf-lrc__base', tokens);
    var hl = buildLrcLayer('wf-lrc__hl', tokens);
    hl.style.color = chroma.hl;
    // 阴影（含右下角立体 + 暗色模式适配）统一交给 CSS 处理，这里不设内联 text-shadow，
    // 以便明暗两种主题用不同阴影（内联样式无法随主题变化）。
    wrap.appendChild(base);
    wrap.appendChild(hl);
    dock.appendChild(wrap);

    // ⑤ letterfx 逐字入场（三档，仿 myhkw；只加在底层暗字上才看得见）：
    //    每字时长 per = 行时长/字数；>6 字分 smear(慢)/ticker(快)，≤6 字 wave。
    var perChar = unitCount > 0 ? (line.dur * 1000) / (unitCount + 1) : 400;
    var baseEms = base.querySelectorAll('.wf-lrc__base-ch');
    var fx = unitCount > 6 ? (perChar > 500 ? 'wf-lrc-fx-smear' : 'wf-lrc-fx-ticker') : 'wf-lrc-fx-wave';
    var fxDur = unitCount > 6 ? (perChar > 500 ? 0.5 : 0.28) : 0.15;
    var step = Math.max(12, Math.min(90, perChar * 0.5));
    for (var i2 = 0; i2 < baseEms.length; i2++) {
      baseEms[i2].style.animation = fx + ' ' + fxDur + 's ease both';
      baseEms[i2].style.animationDelay = (i2 * step) + 'ms';
    }

    music._lrcEl = { hl: hl, base: base, line: line, unitCount: unitCount, lineEl: wrap };
    dock.classList.add('is-on');
    dock.setAttribute('aria-hidden', 'false');
    updateLrcSweep();
  }

  // ① 逐字扫过：每个字按「该字时长 = 行时长/字数」从左到右揭示。
  //    等价 myhkw 的逐字 $em.animate(width:0→100%, dir) 接力；此处用逐帧按真实进度计算更稳。
  //    另外：整行超长时，随逐字推进把两层整体向左平移，让当前字滚入可视区（前面的滚出去）。
  function updateLrcSweep() {
    if (!music || !music._lrcEl) return;
    var el = music._lrcEl;
    var line = el.line;
    var t = music.audio.currentTime || 0;
    var dur = line.dur || 4;
    var elapsed = t - line.t;
    if (elapsed < 0) elapsed = 0;
    if (elapsed > dur) elapsed = dur;                 // 行尾后保持全亮（等待 500ms 退场）
    var units = el.unitCount || 1;
    var per = dur / units;                            // 每字时长（秒）
    var pos = per > 0 ? (elapsed / per) : units;      // 已扫过的「逐字单元数」（含小数）
    var ems = el.hl.querySelectorAll('.wf-lrc__hl-ch');
    for (var k = 0; k < ems.length; k++) {
      var p = Math.max(0, Math.min(1, pos - k));      // 第 k 字已揭示比例
      var cut = (1 - p) * 100;
      ems[k].style.clipPath = 'inset(0 ' + cut.toFixed(2) + '% 0 0)';
      ems[k].style.webkitClipPath = 'inset(0 ' + cut.toFixed(2) + '% 0 0)';
    }
    updateLrcScroll(el, pos);
  }

  // 超长行横向跟随：**逐字**渐进滚动（每唱过一个字就滚动一次，靠 CSS transition 平滑）。
  // 目标：让「当前唱到的字」始终完整可见 —— 只滚到该字右缘贴住可视区右缘为止（不过度）。
  // 因此唱到最后一个字时，最后一个字也会被滚出来（末尾不会"没滚完就切行"）。
  // 注意：中英混排时各字宽度不等（英文单词整体算一个字），所以必须用**每个字自己的**
  // 左偏移与宽度，不能用一个平均字宽（否则会出现滚太早/滚不动）。
  function updateLrcScroll(el, pos) {
    var lineEl = el.lineEl;
    if (!lineEl || !el.base) return;
    // 只在首帧测量一次（布局稳定后）：可视宽度、内容宽度、每个字的左偏移与宽度
    if (el._measW !== lineEl.clientWidth || typeof el.contentW !== 'number') {
      el._measW = lineEl.clientWidth;
      el.wrapW = lineEl.clientWidth;
      el.contentW = el.base.getBoundingClientRect().width;   // inline-block 收缩到内容宽度
      var bs = el.base.querySelectorAll('.wf-lrc__base-ch');
      var xs = [], ws = [];
      for (var i = 0; i < bs.length; i++) { xs.push(bs[i].offsetLeft); ws.push(bs[i].offsetWidth); }
      el.charX = xs;
      el.charW = ws;
    }
    var wrapW = el.wrapW || 0;
    var contentW = el.contentW || 0;
    var setScroll = function (v) {
      if (el.curScroll != null && Math.abs(v - el.curScroll) < 0.5) return;
      el.curScroll = v;
      el.base.style.setProperty('--wf-lrc-scroll', v.toFixed(1) + 'px');
      el.hl.style.setProperty('--wf-lrc-scroll', v.toFixed(1) + 'px');
    };
    if (contentW <= wrapW + 1) {           // 短行：居中（不动）
      setScroll((wrapW - contentW) / 2);
      return;
    }
    var n = el.charX ? el.charX.length : 0;
    if (!n) return;
    // 逐字取整：唱到第 k 个字（k 最大到最后一个字，保证末尾也能滚出来）
    var k = Math.floor(pos);
    if (k > n - 1) k = n - 1;
    if (k < 0) k = 0;
    // 超长行：从 0 开始（开头左对齐、不被裁掉），随逐字向左滚动，
    // 只滚到「当前字刚好完整露在可视区右缘」为止（不过度；留 6px 余量）。
    var charRight = el.charX[k] + (el.charW ? el.charW[k] : 0);
    var target = (wrapW - 6) - charRight;
    var min = wrapW - contentW;            // 最左：内容右端贴住可视区右端
    if (target < min) target = min;
    if (target > 0) target = 0;            // 最右：开头左对齐，不右移
    setScroll(target);
  }

  // 播放时按当前时间刷新歌词行；行没变就只推进逐字宽度
  function syncLrc() {
    if (!music || !music.lrc || !music.lrc.length) return;
    var t = music.audio.currentTime || 0;
    var idx = -1;
    for (var i = 0; i < music.lrc.length; i++) {
      if (music.lrc[i].t <= t) idx = i; else break;
    }
    if (idx !== music.lrcIndex) {
      music.lrcIndex = idx;
      renderLrc();
    } else {
      updateLrcSweep();
    }
  }

  // 用 requestAnimationFrame 平滑推进逐字（timeupdate 只有 ~4Hz，会卡顿）
  function tickLrc() {
    if (music && music.audio && !music.audio.paused) updateLrcSweep();
    requestAnimationFrame(tickLrc);
  }

  function initMusic() {
    var cfg = musicCfg();
    if (!cfg || !cfg.items || !cfg.items.length) return;
    bindMusicFabOnce();

    // 幂等：已建好实例，只补一次面板交互，不重建（PJAX 换页不断歌）
    if (music) return;

    var audio = document.getElementById('wf-music-audio');
    if (!audio) return;
    music = { audio: audio, songs: [], index: 0, mode: 'list', lrc: [], lrcIndex: -1, failCount: 0 };

    // 初始化：播放模式 / 歌词开关（本地记忆优先，其次后台默认）
    var cfg0 = musicCfg();
    var savedMode = mGet(MK.mode, null);
    music.mode = (savedMode === 'random' || savedMode === 'single' || savedMode === 'list')
      ? savedMode : ((cfg0 && cfg0.mode) || 'list');
    syncModeUI();
    applyLyricVisibility();

    // 音频事件 → UI
    audio.addEventListener('play', function () {
      setPlayIcon(true);
      applyLrcPlaying(true);
      broadcastPlay();
    });
    audio.addEventListener('pause', function () { setPlayIcon(false); applyLrcPlaying(false); saveLast(); });
    audio.addEventListener('timeupdate', function () {
      verifyPendingSeek();
      var seek = document.getElementById('wf-music-seek');
      var cur = document.getElementById('wf-music-cur');
      if (seek && audio.duration) {
        var pct = audio.currentTime / audio.duration * 100;
        seek.value = Math.round(pct * 10);
        seek.style.setProperty('--wf-music-seek', pct + '%');   // 已播放部分着品牌色
      }
      if (cur) cur.textContent = fmtTime(audio.currentTime);
      syncLrc();
    });
    audio.addEventListener('loadedmetadata', function () {
      music.failCount = 0; // 成功拿到元数据 → 认为这首歌可用，重置失败计数
      var dur = document.getElementById('wf-music-dur');
      if (dur) dur.textContent = fmtTime(audio.duration);
      // 「接着上次播」：元数据就绪后再定位到记住的进度
      applyPendingSeek();
    });
    // 元数据阶段 seek 不生效时的兜底（见 applyPendingSeek）：
    // 这几个时机都再试一次，真正到位后由 verifyPendingSeek() 收尾
    audio.addEventListener('canplay', applyPendingSeek);
    audio.addEventListener('play', function () { applyPendingSeek(); });
    audio.addEventListener('seeked', verifyPendingSeek);
    audio.addEventListener('ended', function () { saveLast(); onEnded(); });
    // 加载/解码失败：标记该曲失败并自动跳下一首（仿 APlayer；避免死循环）
    audio.addEventListener('error', function () { handleAudioError(); });
    // 定期记忆进度（暂停/换歌时也会存一次）
    if (!initMusic._saveTimer) {
      initMusic._saveTimer = setInterval(function () { if (music && music.audio && !music.audio.paused) saveLast(); }, 5000);
    }
    // 关页 / 切后台时立刻落盘：定时器 5 秒一次，直接关页面会丢掉最后几秒
    if (!initMusic._flushBound) {
      initMusic._flushBound = true;
      var flushLast = function () { try { saveLast(); } catch (e) { /* ignore */ } };
      window.addEventListener('pagehide', flushLast);
      window.addEventListener('beforeunload', flushLast);
      document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') flushLast();
      });
    }

    // 平滑推进歌词扫字
    if (!initMusic._raf) {
      initMusic._raf = true;
      requestAnimationFrame(tickLrc);
    }

    // 跨标签页互斥：其它标签页开始播放时，暂停本页
    setupCrossTab();

    // 控制按钮（委托，绑一次）
    if (!initMusic._ctrlBound) {
      initMusic._ctrlBound = true;
      document.addEventListener('click', function (e) {
        if (!e.target.closest || !music) return;
        if (e.target.closest('[data-music-toggle]')) { e.preventDefault(); playPause(); return; }
        if (e.target.closest('[data-music-prev]')) { e.preventDefault(); nextSong(-1); return; }
        if (e.target.closest('[data-music-next]')) { e.preventDefault(); nextSong(1); return; }
        if (e.target.closest('[data-music-locate]')) { e.preventDefault(); locateCurrentItem(); return; }
        if (e.target.closest('[data-music-mode]')) {
          e.preventDefault();
          // 三态循环：列表循环 → 随机 → 单曲 → 列表…
          music.mode = (music.mode === 'list') ? 'random' : (music.mode === 'random' ? 'single' : 'list');
          mSet(MK.mode, music.mode);
          syncModeUI();
          return;
        }
        if (e.target.closest('[data-music-lyric-toggle]')) {
          e.preventDefault();
          var on = !lyricOn();
          mSet(MK.lyric, on ? '1' : '0');
          applyLyricVisibility();
          if (on && music.lrc && music.lrc.length) renderLrc();
          return;
        }
        var item = e.target.closest('#wf-music-list .wf-music__item');
        if (item) {
          e.preventDefault();
          if (item.classList.contains('is-failed')) {
            item.classList.remove('is-failed'); // 再点一次给失败曲重试的机会
          }
          loadSong(Number(item.getAttribute('data-index')), true);
        }
      });
      // 进度条拖动（input 实时、change 兜底，防个别浏览器不派发 input）
      var onRange = function (e) {
        if (!music || !e.target) return;
        if (e.target.id === 'wf-music-seek' && music.audio.duration) {
          var pct = e.target.value / 1000 * 100;
          e.target.style.setProperty('--wf-music-seek', pct + '%');   // 拖动时填充即时跟进
          // 用户手动定位 → 放弃「接着上次播」的待定位状态，别跟用户抢
          music._pendingSeek = 0;
          music._seekTries = 0;
          music.audio.currentTime = e.target.value / 1000 * music.audio.duration;
        }
      };
      document.addEventListener('input', onRange);
      document.addEventListener('change', onRange);
    }

    // 拉歌单
    var jobs = cfg.items.map(function (item) {
      var type = item.type || 'playlist';
      return musicJson(proxyMusicUrl(item, type, item.id)).then(function (data) {
        if (!data) return [];
        return (Object.prototype.toString.call(data) === '[object Array]') ? data : [data];
      });
    });

    Promise.all(jobs).then(function (groups) {
      var songs = [];
      groups.forEach(function (g) {
        g.forEach(function (s) {
          if (s && s.url) songs.push({
            name: s.name || '',
            artist: (Object.prototype.toString.call(s.artist) === '[object Array]') ? s.artist.join(' / ') : (s.artist || ''),
            album: (Object.prototype.toString.call(s.album) === '[object Array]') ? s.album.join(' / ') : (s.album || ''),
            url: s.url,
            cover: s.cover || s.pic || '',
            lrc: s.lrc || '',
            _key: (s.name || '') + '|' + (s.artist || '')
          });
        });
      });
      if (!songs.length) {
        var list = document.getElementById('wf-music-list');
        if (list) { list.removeAttribute('data-loading'); list.innerHTML = '<li class="wf-music__tip">暂无歌曲（请检查后台 Cookie / 歌单）</li>'; }
        return;
      }
      music.songs = songs;
      // 列表延后到「首次打开面板」再渲染（1082 首的封面 DOM 很重，页面加载时
      // 全部渲染会造成滚动掉帧）。面板未打开时只标记待渲染。
      var mlist = document.getElementById('wf-music');
      if (mlist && mlist.classList.contains('is-open')) renderMusicList();
      else music.listPending = true;

      // 「接着上次播」：优先按上次的歌曲 key 找回来，其次按下标；并恢复进度
      var resumeIdx = 0, resumeTime = 0;
      try {
        var last = JSON.parse(mGet(MK.last, 'null')) || {};
        var byKey = -1;
        if (last.id) {
          for (var k = 0; k < songs.length; k++) { if (songs[k]._key === last.id) { byKey = k; break; } }
        }
        resumeIdx = byKey >= 0 ? byKey : (typeof last.index === 'number' && last.index < songs.length ? last.index : 0);
        // 优先取这首歌自己记住的进度（map），兼容旧格式的顶层 time
        var map = last.map || {};
        var resumeKey = songs[resumeIdx] && songs[resumeIdx]._key;
        resumeTime = (resumeKey && typeof map[resumeKey] === 'number') ? map[resumeKey] : (last.time || 0);
      } catch (e) { /* ignore */ }
      loadSong(resumeIdx, !!cfg.autoplay, resumeTime);
    });
  }

  /* ---------- 歌词随播放显隐 ----------
     显示条件：歌词开关为开 **且** 正在播放（暂停即隐藏）。 */
  function applyLrcPlaying(playing) {
    if (!music) return;
    music._playing = !!playing;
    var dock = document.getElementById('wf-lrc');
    if (!dock) return;
    var show = lyricOn() && !!playing;
    dock.classList.toggle('is-on', show);
    dock.setAttribute('aria-hidden', show ? 'false' : 'true');
    if (show) {
      if (music.lrc && music.lrc.length && music.lrcIndex >= 0) renderLrc();
    } else {
      music._lrcEl = null;
    }
  }

  // 依据「歌词开关 && 正在播放」刷新贴底歌词显隐（开关切换时调用）
  function syncLrcDock() {
    applyLrcPlaying(music ? !!music._playing : false);
  }

  /* ---------- 加载失败兼容（仿 APlayer）----------
     音频 error 时：标记该曲在列表里为「加载失败」；若还有别的歌可播，
     延时 2 秒自动跳下一首。连续失败累计到「全部曲目都失败」就停手，避免无限循环。 */
  function handleAudioError() {
    if (!music || !music.songs.length) return;
    var song = music.songs[music.index];
    if (song) {
      song._failed = true;
      var list = document.getElementById('wf-music-list');
      var li = list && list.querySelector('.wf-music__item[data-index="' + music.index + '"]');
      if (li) li.classList.add('is-failed');
    }
    music.failCount = (music.failCount || 0) + 1;
    // 全部曲目都试过失败 → 停止自动跳，给个提示
    if (music.failCount >= music.songs.length) {
      var host = document.getElementById('wf-music-name');
      if (host) host.textContent = '歌曲加载失败';
      return;
    }
    // 有下一首可播 → 2 秒后自动跳（参照 APlayer 的 skip 行为）
    if (music.songs.length > 1) {
      clearTimeout(music._skipTimer);
      music._skipTimer = setTimeout(function () {
        if (!music) return;
        // 找下一首没失败过的；若都失败过则按顺序取下一首（给重试机会）
        nextSong(1);
        playPause(true);
      }, 2000);
    }
  }

  /* ---------- 跨标签页互斥：同一时刻只有一个标签页出声 ----------
     APlayer 不做这件事（只有页内 mutex）。这里用 BroadcastChannel 广播「某页开始播放」，
     其它页收到后立即暂停；不支持 BroadcastChannel 时退回 localStorage 的 storage 事件。 */
  var crossTab = { name: 'wf-music-play', id: Math.random().toString(36).slice(2) };

  function broadcastPlay() {
    if (!music || music.audio.paused) return;
    var payload = { id: crossTab.id, t: Date.now() };
    try {
      if (window.BroadcastChannel) {
        var ch = crossTab.ch || (crossTab.ch = new BroadcastChannel(crossTab.name));
        ch.postMessage(payload);
        return;
      }
    } catch (e) { /* ignore */ }
    try {
      localStorage.setItem(crossTab.name, JSON.stringify(payload));
    } catch (e) { /* ignore */ }
  }

  function setupCrossTab() {
    if (setupCrossTab._done) return;
    setupCrossTab._done = true;

    function onOtherPlay(payload) {
      if (!payload || payload.id === crossTab.id) return;
      // 其它标签页开始播放 → 本页暂停
      if (music && music.audio && !music.audio.paused) {
        try { music.audio.pause(); } catch (e) { /* ignore */ }
      }
    }

    try {
      if (window.BroadcastChannel) {
        var ch = crossTab.ch || (crossTab.ch = new BroadcastChannel(crossTab.name));
        ch.onmessage = function (ev) { onOtherPlay(ev.data); };
        return;
      }
    } catch (e) { /* ignore */ }

    // 退回 localStorage storage 事件（同一 key 变化会通知其它标签页）
    window.addEventListener('storage', function (ev) {
      if (ev.key !== crossTab.name || !ev.newValue) return;
      try { onOtherPlay(JSON.parse(ev.newValue)); } catch (e) { /* ignore */ }
    });
  }

  function initFab() {
    if (!document.getElementById('wf-fab')) return;
    initTheme();
    bindFabDocOnce();
    bindTocOnce();
    initToc();

    // 返回顶部：滚动后出现
    if (!initFab._bound) {
      initFab._bound = true;
      var topBtn = document.querySelector('[data-fab-top]');

      var onScroll = function () {
        if (!topBtn) return;
        topBtn.classList.toggle('is-on', (window.pageYOffset || window.scrollY || 0) > 300);
      };
      window.addEventListener('scroll', onScroll, { passive: true });
      onScroll();

      if (topBtn) {
        topBtn.addEventListener('click', function (e) {
          e.preventDefault();
          try { window.scrollTo({ top: 0, behavior: 'smooth' }); }
          catch (err) { window.scrollTo(0, 0); }
        });
      }
    }
  }

  /* 每次页面（或 PJAX 换页后）初始化 */
  function runInit() {
    initLazy();
    initCommentKeys();
    initFancybox();
    initLoadMore();
    initCommentProfile();
    initCommentLoadMore();
    initCommentEditor();
    initCommentEmoji();
    initHighlight();
    initVote();
    initReward();
    initShare();
    initSiteSearch();
    initTime();
    initFab();
    initMusic();
    externalizeLinks(document);
  }

  /* ============ 密码保护文章：输入密码无刷新解锁 ============
     Typecho 输出的 .protected 表单提交后会设置解锁 cookie 并返回文章页，
     这里拦截提交、用 fetch 拿回页面并就地替换 .page，全程不整页刷新。 */
  function initPasswordForm() {
    if (initPasswordForm._done) return;
    initPasswordForm._done = true;

    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (!form || !form.classList || !form.classList.contains('protected')) return;
      if (typeof form.action !== 'string' || !form.action) return;
      e.preventDefault();

      var pwd = form.querySelector('input[name=protectPassword]');
      var val = pwd ? String(pwd.value || '') : '';
      if (!val) {
        wfToptips('请输入访问密码', true);
        if (pwd) try { pwd.focus(); } catch (err) {}
        return;
      }

      var btn = form.querySelector('input[type=submit]');
      if (btn) { btn.disabled = true; }

      fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        redirect: 'follow'
      })
        .then(function (r) {
          return r.text().then(function (html) { return { ok: r.ok, status: r.status, html: html }; });
        })
        .then(function (res) {
          var doc = new DOMParser().parseFromString(res.html, 'text/html');
          var newPage = doc.querySelector(PJAX_SELECTOR);
          // 还带着密码表单 / 没有正常页面 => 密码不对
          var locked = !newPage || !!doc.querySelector('form.protected');
          if (locked) {
            wfToptips('密码错误，请重试', true);
            if (pwd) { try { pwd.select(); } catch (err) {} }
            if (btn) { btn.disabled = false; }
            return;
          }

          var newTitle = doc.querySelector('title');
          if (newTitle) document.title = newTitle.textContent;

          var page = document.querySelector(PJAX_SELECTOR);
          if (page) {
            page.replaceWith(document.importNode(newPage, true));
          } else {
            document.body.appendChild(document.importNode(newPage, true));
          }
          // 瞬时回顶：不用动画，避免与图片懒加载撑高内容产生竞态而回不到顶部
          try { window.scrollTo({ top: 0, left: 0, behavior: 'instant' }); }
          catch (e) { window.scrollTo(0, 0); }
          wfTrackPageview(location.href);
          runInit();
        })
        .catch(function () {
          wfToptips('网络异常，请检查连接后重试', true);
          if (btn) { btn.disabled = false; }
        });
    });
  }

  function boot() {
    initPjax();
    initCommentAjax();
    initCommentReply();
    initPasswordForm();
    runInit();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
