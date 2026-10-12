# WeUI —— Typecho 主题（基础版）

一个使用腾讯 [WeUI](https://weui.io) 构建的 Typecho 主题，样式与官方 [文章页示例](https://weui.io/#article) 保持一致。

这是一个**基础版本**，只实现博客最核心的页面：文章列表、文章页、独立页面、评论与页脚。
按照“**只引入用到的样式**”的原则，`assets/weui.css` 是从 WeUI 官方 dist 中**逐条摘录**出的最小子集，
未使用的组件（按钮、表单、弹窗等）一律没有引入，需要时再按下方步骤加回来。

## 目录结构

```
theme/
├── assets/
│   ├── weui.css          # WeUI 样式子集（官方 dist 逐条摘录，未改动规则）
│   └── theme.js          # 主题交互：搜索栏、相册懒加载、灯箱
├── tools/
│   ├── build-weui-subset.py   # WeUI 子集提取脚本（Python 3，仅用标准库）
│   └── sync-live.py           # 一键同步到线上服务器（需要 paramiko）
├── index.php             # 文章列表（首页）+ 归档页
├── archive.php           # 通用归档模板（标签 / 作者 / 日期等）
├── category.php          # 分类归档
├── search.php            # 搜索结果页
├── post.php              # 文章详情页（相册文章自动切到相册布局）
├── page.php              # 独立页面
├── archives.php          # 自定义页面模板：归档（时间线）
├── categories.php        # 自定义页面模板：分类
├── gallery.php           # 自定义页面模板：相册（列出相册文章）
├── gallery_album.php     # 相册详情（正方形网格 + Fancybox 灯箱）
├── links.php             # 自定义页面模板：友链
├── about.php             # 自定义页面模板：关于（通用空白页）
├── header.php  footer.php  comments.php
├── functions.php         # 主题函数与设置
├── style.css             # 主题自有“胶水”样式
└── screenshot.png        # 主题预览图
```

## 安装

1. 把整个 `theme` 目录放进 Typecho 的 `usr/themes/` 下（建议目录名改为 `WeUI`）。
2. 后台 → 控制台 → 外观，启用「WeUI 主题（基础版）」。
3. Typecho 从 `index.php` 头部的注释读取主题名、作者、版本（**本项目不需要 `info.json`**）。

## 正文排版组件

WeUI 只提供了文章容器的基础样式，博客常用组件（引用、表格、代码块等）在 `style.css` 里补齐，
全部使用 CSS 变量，**明暗两种模式自动适配**（正文与评论内容共用同一套规则）：

- 引用 `blockquote`、有序/无序列表、行内 `code`、`kbd`、`mark`、`del`、`hr`
- 表格 `table`（含边框与表头底色）
- 代码块 `pre > code`：**highlight.js 自动高亮**（自动识别语言，无需给代码块指定语言）
- 图片说明 `figure / figcaption`、折叠 `details / summary`、自适应 `iframe / video`

### 代码高亮

- 高亮脚本：`assets/highlight.min.js`（highlight.js 11.9，本地内置，不依赖外网 CDN）
- 配色：`assets/highlight.css`，用 `--wf-hl-*` 变量跟随深浅色切换
- 每个代码块右上角自动出现「复制」按钮（`assets/theme.js` 注入）

### 链接新窗口

**文章正文**（`.wf-post-content`）、评论内容、评论作者名（访客网站）里的链接会在新标签页打开
（`assets/theme.js` 的 `externalizeLinks`）；站外链接也会自动补 `target="_blank"` 与 `rel="noopener"`。

## 无刷新（PJAX）

主题内置站点级 PJAX（`assets/theme.js`）：点击站内链接时用 `fetch` 取回页面、
替换 `.page` 容器并同步标题与 history，无整页刷新。换页后会重新执行
懒加载、灯箱、代码高亮、加载更多等初始化。

- 正文/评论里的链接、`target="_blank"`、外链、附件下载不参与 PJAX，走正常跳转；
- 头部**搜索表单**的提交也会被拦截，用 `?s=关键词` 走同一套无刷新跳转；
- 想对某个链接禁用 PJAX，给它加 `data-no-pjax` 属性；
- 返回/前进（popstate）同样走 PJAX。

### 统计代码与无刷新

后台「统计代码」里粘贴的脚本**只在首次整页加载时执行一次**，避免每次换页都重新注入脚本导致重复计数。
PJAX 换页后由 `assets/theme.js` 的 `wfTrackPageview()` 调用各统计平台的**标准接口**补发一次浏览：

- 百度统计 `window._hmt.push(['_trackPageview', url])`
- Google Analytics 4 `window.gtag('event', 'page_view', …)`
- 通用 analytics.js `window.ga('send', 'pageview', url)`
- Matomo / Piwik `window._paq.push(['setCustomUrl', url], ['trackPageView'])`
- 其它平台（友盟 / CNZZ 等）：自定义一个全局函数 `window.wfOnPjaxPageview(url)`，主题会在换页后调用它。

因此**整页加载记 1 次、每次无刷新换页再各记 1 次**，既不会因为无刷新而漏掉浏览，
也不会因为脚本被重复注入而重复计数。统计脚本运行失败不影响页面（全部包在 `try/catch` 里）。

## 设计说明

- **对齐官方实现**：文章页直接使用官方的 `article.weui-article` 结构（`h1` + 嵌套 `section`），
  列表页使用官方 `weui-panel` + `weui-media-box weui-media-box_text` 组合，页脚使用官方 `weui-footer`。
  这些组件的 CSS 全部原样取自 WeUI，没有对组件内部样式做任何覆写。
- **只用到的样式才引入**：`assets/weui.css` 只包含以下组件及其引用到的主题 CSS 变量：
  `weui-article`、`weui-panel`、`weui-media-box`、`weui-cells`/`weui-cell`、
  `weui-footer`、`weui-link`，
  外加 WeUI 的 base reset。主题变量只保留被引用到的那些。
- **深浅色**：WeUI 的 `@media (prefers-color-scheme: dark)` 已包含在子集中；
  也可以在 `<body>` 上用 `data-weui-theme="light|dark"` 强制指定（官方同款机制）。
- **`style.css` 只写胶水样式**：页面容器、站点头部、文章元信息、评论、分页等 WeUI 未提供的部分。
  没有修改任何 WeUI 组件内部样式。

## 主题设置

后台 → 控制台 → 外观 → 设置外观：

| 设置项 | 说明 |
| --- | --- |
| 网站头部位置 | 「跟随页面滚动」或「固定在页面顶部」（`position: sticky`）。 |
| 首页显示副标题 | 是否在首页头部主标题后显示站点副标题。 |
| 头部搜索 | 开启后导航栏右侧出现放大镜，点击在头部展开 WeUI 搜索框，再点收起（可关闭）。 |
| 显示明暗模式切换按钮 | 关闭后前台不再显示「外观设置」齿轮，访客一律按「默认配色」显示。 |
| 默认配色 | 跟随系统 / 按时间自动 / 亮色 / 暗色。 |
| 亮色起始小时 / 结束小时 | 「按时间自动」的区间，0-23 的整点；支持跨夜（如 20 与 6）。 |
| Gravatar 头像加速地址 | 留空用官方；可填镜像地址，如 `https://cn.cravatar.com` 或 `https://cdn.v2ex.com/gravatar`。主题会自动在末尾拼 `/avatar/<hash>`（已带 `/avatar` 的写法也不会重复拼）。见下方「头像与 CDN」。 |
| 页脚自定义内容 | 支持 HTML，显示在版权信息**上方**，可放备案号、友链等。 |
| 统计代码 | 粘贴第三方统计代码（支持 HTML/`script`）。只在首次加载时执行一次，无刷新时由主题补发浏览，**不漏统计也不重复统计**（见下）。 |
| 列表翻页方式 | 「分页」或「瀑布流加载更多」。 |
| 评论翻页方式 | 「分页」或「加载更多」（需先在「设置 → 评论」开启评论分页）。 |
| 文章点赞 | 是否在文章页显示点赞。 |
| 评论点赞 | 是否在每条评论上显示点赞。 |
| 微信打赏二维码 | 收款码图片地址；留空则不显示微信打赏。**两个都留空时整块打赏都不显示**。 |
| 支付宝打赏二维码 | 收款码图片地址；与微信都填时在弹窗里**同一行并排显示**。 |
| 打赏说明文字 | 显示在打赏弹窗二维码上方，可留空。 |
| 建站年份 | 页脚版权显示 `Copyright © 建站年份-当前年份 站名 All Rights Reserved.`；留空则只显示当前年份。 |

## 头部搜索

后台开启「头部搜索」后，导航栏右侧会出现一个放大镜按钮：

- 点击 → 在头部下方**展开**一个 WeUI 搜索栏（`.weui-search-bar` 官方组件）；
- **再点一次 → 收起**（按 Esc 也能收起）；
- 展开时输入框自动聚焦，可以立即打字；输入内容时右侧出现「清空」按钮；
- 提交后**走无刷新（PJAX）**跳到搜索结果页 **`/search/<关键词>/`**（Typecho 原生搜索路由），
  换页后依然能用搜索栏继续搜；输入为空时不跳转、只聚焦输入框。

表单 `action` 取站点 index（`$this->options->index`）。JS 会**先去掉结尾斜杠、再丢掉结尾的
`index.php`、再去掉剩余斜杠**，最后统一补一个斜杠拼 `search/<kw>/`——因此 action 写成
`https://site`、`https://site/`、`https://site/index.php` 或子目录都没问题
（早期版本缺少「去结尾斜杠」这一步，action 不带斜杠时会拼成 `https://sitesearch/` 这种错误地址）；
无 JS 时浏览器按 `?s=` 提交，Typecho 同样会跳转到 `/search/<kw>/`。

实现要点：交互用**文档级事件委托**（和投票、时间一致），不依赖初始化瞬间元素是否已存在，
PJAX 换页后照常可用。样式取自 WeUI 官方搜索栏，只额外补了图标/清空按钮等几处胶水样式。

## 相对时间、阅读次数与元信息

- **相对时间**：文章列表、文章页、归档页的时间都是「刚刚 / N分钟前 / N小时前 / N天前 /
  N个月前」；超过一年直接给完整日期（避免「1 年前」没信息量）。
  由 `weuiRelativeTime()` / `weuiTime()` 输出，**数字与中文之间没有空格**。
- **点击切换完整时间**：**列表卡片和正文**的时间都可点，点一下切换成 `2026-01-02 11:11:11`。
- **阅读次数**：文章页与相册页每访问一次按后台设置计数。
  计数存在 Typecho **原生的自定义字段**里（`typecho_fields` 表，字段名 `views`，存十进制字符串），
  **不新增任何数据表、也不改任何原有表结构**。
  - **后台「阅读计数方式」**（`viewsUnique`）：
    - **按访客去重**（默认）：用 Cookie（`weui_viewed`，存一串已读 cid）记录，同一访客反复
      打开同一篇只计一次；一年有效，最多记 200 篇（`WEUI_VIEWED_MAX`）。
    - **每次访问都计数**：不加 Cookie 判断，刷新、重复打开都会 +1。
- **元信息字段（列表与正文一致）**：`作者 · 时间 · 分类 · 阅读次数`，**每个字段前都有一个小图标**
  （由 `weuiIcon()` 输出内联 SVG，跟随 `currentColor` 自动适配明暗）。
  作者与分类都是**可点击链接**（进入对应归档），但**颜色与其它字段完全一致**（`.wf-meta-link`
  用 `color: inherit`，不用链接色、不加下划线）。
  - 列表卡片：`weuiListInfo()` 输出 `.weui-media-box__info`，四段用 flex + `gap` 排列；
  - 正文（文章 / 相册）：`weuiArticleMeta()` 输出 `.article-meta`，四段用淡色的 `|` 分隔。
  两者都基于同一个组装函数 `weuiMetaFields()`，保证字段与图标完全一致。

## 页脚版权

`Copyright © 建站年份-当前年份 网站名 All Rights Reserved.`——**年份是普通文字，只有网站名是超链接**（链到本站）。
建站年份在后台设置，当前年份由代码自动读取；留空年份时只显示当前年份。
下方是 `由 <Typecho logo> 强力驱动`。Typecho logo 用的是官方 SVG（内联在 `footer.php` 里），
原始图形是纯黑，这里改成 `currentColor`，颜色由 `--weui-FG-1` 控制，
所以**亮色 / 暗色模式都能自动适配**。

## 配色与侧边悬浮按钮

页面右侧有一个悬浮控件（`footer.php` 输出，**在 `.page` 之外，PJAX 换页不会被替换**）：

- **齿轮按钮**：点击弹出配色设置面板，四种模式：
  - **跟随系统**（默认）：读取 `prefers-color-scheme`，系统切换时实时跟随；
  - **按时间自动**：亮色区间由后台的「亮色起始/结束小时」决定（默认 7–19 点），每分钟检查一次；
  - **亮色 / 暗色**：手动锁定。
  选择结果写入 `localStorage`（键 `wf_theme_mode`），下次访问自动恢复；
  **后台关闭「明暗模式切换按钮」后齿轮隐藏、也不再读本地选择**。
- **返回顶部按钮**：默认隐藏，向下滚动超过 300px 后淡入，点击平滑回顶。

**防闪烁**：`header.php` 在 `</head>` 前先输出后台配置（`window.__wfThemeCfg`），再用一段内联脚本
把配色解析成 `light|dark` 写到 `<html data-wf-theme>`（同时给 `<html>` 上底色），
`<body>` 开头再同步一份 `data-weui-theme` 给 WeUI 变量，避免刷新时闪一下另一种底色。

**响应式外观**：移动端贴紧屏幕右缘的半圆片（`border-radius` 只保留左侧）；
PC 端（≥641px）变成离边缘 24px 的悬浮圆球，带阴影与 hover 效果；**暗色下额外加一圈细边框**，
避免深色背景上按钮轮廓不明显。

## 点赞 / 打赏 / 分享

**文章页 / 相册页**底部有一行操作（`post.php` / `gallery_album.php` 里的 `.article-actions`），
位置在**正文之后、标签之前**：**点赞 + 打赏 + 分享**。它们放在
`weuiVoteHtml($type, $id, $middle)` 同一个 `.wf-vote` 容器里，用
`justify-content: center` 居中并保持较小的固定间距（`gap`），
所以是**独立一行、挨在一起居中**。`$middle` 由 `weuiRewardHtml()` 与 `weuiShareHtml()`
依次拼成，各自按后台开关决定是否输出。
点赞按钮**像打赏按钮一样带一圈外框**（圆角胶囊、`--weui-FG-3` 描边）；
**点赞后只有图标与数字变主题色，外框保持灰色**（不整圈变绿）。
**不管有没有打赏/分享按钮，点赞都整行居中**——`.article-actions .wf-vote` 强制
`width: 100%` + `justify-content: center`（关闭打赏与分享时只有点赞，同样居中）。
打赏按钮只在该文章有配置二维码时出现。
**评论区不再显示打赏**，每条评论头部右侧是 **点赞 + 回复** 两枚控件（点赞可在后台关闭）。

**打赏**（`weuiRewardHtml()`）：

- 后台配置**微信**和**支付宝**两个收款码地址；
- **只配一个就只显示一个**（居中），**两个都配则在弹窗里同一行并排**（`.wf-reward__codes.is-both`）；
- **两个都留空时整块打赏（按钮 + 弹窗）都不输出**；
- 点「打赏」按钮弹出 WeUI 弹窗显示二维码，点遮罩、点「关闭」或按 Esc 都可以关掉。

**分享**（`weuiShareHtml()`，逻辑参考 [kx.gy](https://kx.gy/archives/wechat-card.html) 的分享面板）：

- 后台「文章分享」开关控制显示（默认开）；分享地址、标题与海报素材（封面 / 摘要 / 站点名 / 日期）
  都由**服务端**渲染在按钮的 `data-share-*` 上；
- 自定义面板（`.wf-share__grid` 三列）提供 6 项：
  **微博 / QQ 空间 / Telegram / 微信扫码 / 复制链接 / 生成海报**；
  - 三个社交项用标准分享地址（`service.weibo.com/share/share.php`、`sns.qzone.qq.com/...`、
    `t.me/share/url`），打开新窗口；
  - **微信扫码**：点一下在面板下方生成二维码，再点一下收起；
  - **复制链接**：优先 `navigator.clipboard`，失败回退 `execCommand('copy')`，成功后按钮文字短暂变「已复制」；
  - **生成海报**：见下；
- **系统分享（`navigator.share`）只避开 Windows 桌面**，判断依据是平台能力字段
  （`navigator.userAgentData.platform` 优先，退回 `navigator.platform`），**不是浏览器品牌字符串**；
  - Windows 的 Chromium 会甩出系统分享面板，但系统里常常**没有注册任何共享目标**，
    一点分享就弹出「我们无法为你显示所有共享方法」——访客什么也分享不出去（这就是最初要修的报错）。
    所以 Windows 桌面一律直接用自带面板（含用 Windows 的 Safari / Chrome / Edge）；
  - 其余平台（**iOS / iPadOS 的 Safari**、macOS、Android、Linux）的系统分享都可用，按平台惯例照常调用
    ——**包括 macOS 的 Safari**，不会因为「不是触摸设备」就失去系统分享；
  - **失败或用户取消都会打开自带面板**（只有分享成功才什么都不做）。这一点很关键：
    早期写法在移动端「调了系统分享就 return」，取消后面板永远出不来，
    **「生成海报」在手机上根本没有入口**；现在取消即弹出面板，海报在任何平台都够得着；
- 二维码用 **qrcodejs**（`assets/qrcode.min.js`，MIT，约 20KB）。它和 `highlight.min.js` 一样
  **整站加载**：footer 的 `<script>` 在 `.page` 之外，PJAX 无刷新跳转时不会重新执行，
  若按页面条件加载会取不到 `QRCode`；
- 面板的点击用**事件委托**（挂在 `document` 上），所以 PJAX 换页后重新渲染的按钮无需重新绑定；
  点遮罩、点「关闭」或按 Esc 关闭面板。

**生成海报**（`data-share="poster"` → `#wf-poster-dialog`）：

- **纯前端 canvas 绘制，没有任何服务端参与**（服务器上也没有中文字体，走服务端画字会缺字）；
  画布固定 750×1000（2 倍图，便于高清），内容顺序为：站点名 → 标题（最多 2 行，超出加省略号）
  → 封面（等比裁剪铺满圆角矩形）→ 摘要卡片（行数按剩余高度自适应，最多 4 行）
  → 底部「扫码阅读全文」+ 站点·日期 + 二维码；
- 文字折行是按 `measureText` 逐字测量的自研实现，中英文都适用；
- 二维码复用 **qrcodejs**：先在离屏容器里画好，再把它的 `<canvas>` 贴到海报上；
- 封面用 `crossOrigin='anonymous'` 加载，**加载失败就整张不画封面**——宁可少一张封面，
  也不要画布被「污染」导致画布导出抛错、整张海报都存不下来。所以封面最好放在**同源**目录
  （或返回 `Access-Control-Allow-Origin` 的 CDN）；
- **展示出来的是 `<img>` 而不是 `<canvas>`**：Safari（iOS / macOS）只对**图片**提供
  「存储图像 / 存储到照片」的长按菜单，对 `<canvas>` 长按**没有任何保存项**。所以画好后
  用 `toDataURL` 转成图片再放进 `<img>`（data URL 的「存储图像」支持面比 `blob:` 更广）；
- 「保存图片」按能力依次尝试，最后一定能保存，不会「点了没反应」：
  1. `navigator.canShare({files})` 成立时走系统分享面板（**iOS / iPadOS** 的面板里有「存储图像」）；
     ⚠️ 这条同样要排除 Windows——Windows 的 `canShare({files})` 也返回 true，但面板是坏的；
  2. 否则走 `<a download>` + 图片地址（桌面 Chrome / Edge / Firefox、Android）；
  3. 都不行就提示**长按海报保存**（海报是 `<img>`，Safari 有原生菜单；
     `.wf-poster__canvas` 上显式设了 `-webkit-touch-callout: default`，避免长按菜单被关掉）。


## 归档时间线

归档独立页面（`archives.php`）用 `weuiArchiveTimeline()` 输出一条时间线
（参考 [ihewro 的归档](https://www.ihewro.com/archives.html) 的分组思路，但用主题自有样式实现，
不引入任何额外组件库）：

- 按**年月分组**，月份是一个主题色胶囊（`.wf-timeline__month`），胶囊 `z-index` 高于竖线，
  压在线上形成分组分隔；
- 每条是「左日期（`21日`）+ 中间圆点 + 右标题链接」三栏；
- **竖线整条只画一根**（`.wf-timeline::before`，放在 `<ol>` 上，`left: 63px` =
  日期列 46px + 圆点列一半 17px），逐条绘制会在间距 / 月份分组处出现断线；
- 圆点是空心圆（`box-shadow` 描边主题色），竖线与圆点颜色走 `--weui-FG-3` / `--weui-BRAND`，
  明暗模式自动适配；
- 相册文章（`album=1`）不出现在归档里；超过 1000 篇可自行调 `pageSize`。
- 时间线放在 `.weui-article` 内时，需覆盖官方 `.weui-article ol` 的 `list-style: decimal`
  与 `margin-left`，否则会冒出「1. 2.」序号（已在 `style.css` 里处理）。

## 密码保护文章

后台把「内容密码」填上后，Typecho 会用核心输出的 `.protected` 表单替换正文（标题变成
「此内容被密码保护」）。核心的表单样式很简陋，主题在 `style.css` 里把
`.wf-post-content .protected` 美化成一张**居中的圆角卡片**：

- 顶部一个小锁图标（`mask` + 当前主题色，明暗自动适配），
- 「请输入密码访问」提示、密码输入框（聚焦时边框变主题色）、
- 整宽的主色「提交」按钮（`.submit`，圆角、点击微微变淡）。

不需要改任何模板或核心文件，只靠 CSS 覆盖核心输出的 `.protected / .word / .text / .submit`
四个类名即可，所以**文章页、独立页面都一样生效**。

## 同步到线上

`tools/sync-live.py` 用来把主题同步到线上服务器（只上传主题必需文件、并清理开发残留）：

```bash
python tools/sync-live.py            # 上传 + 清理 + 修正属主
python tools/sync-live.py --dry-run  # 只打印动作，不改动线上
```

它只写入线上主题目录（`/www/wwwroot/blog/usr/themes/weui`），**不触碰 Typecho 核心、
数据库或其它目录**；上传后会删除 `searchbar.php`、开发用的 `_*` 脚本/示例页等遗留文件，
最后 `chown -R www:www` 修正属主。需要 `pip install paramiko`。

## 头像与 CDN

**地址规则**：后台「Gravatar 头像加速地址」按你填的写法归一化，然后拼上 `/<md5>`：

| 后台填写 | 实际请求 |
| --- | --- |
| 留空 | `https://www.gravatar.com/avatar/<hash>?s=…&d=mp` |
| `https://cn.cravatar.com` | `https://cn.cravatar.com/avatar/<hash>?s=…&d=mp` |
| `https://cdn.v2ex.com/gravatar` | `https://cdn.v2ex.com/gravatar/avatar/<hash>?s=…&d=mp` |
| `https://cn.cravatar.com/avatar` | `https://cn.cravatar.com/avatar/<hash>?s=…&d=mp` |

即：结尾没有 `/avatar` 就补一个，已有就不重复补（`weuiGravatarBase()` 归一化，`weuiGravatarDir()` 补目录）。

**前台**（评论列表、游客身份行、邮箱输入后的实时头像）全部经由 `weuiGravatarUrl()`，**严格跟随后台设置**；
同时主题把该地址写进常量 `__TYPECHO_GRAVATAR_PREFIX__`，因此前台里**任何仍走 Typecho 核心的 Gravatar**
（插件、侧栏最近评论等）也会跟着用同一个 CDN。

**后台（Typecho 控制台）的头像**：Typecho 只在**前台**加载主题的 `functions.php`，
后台（评论管理、个人资料等）头像由核心直接渲染、**读不到主题设置**。想让后台也走同一个 CDN，
在站点根目录的 `config.inc.php` **末尾**加一段（会自动读取主题设置里的地址）：

```php
/* 让 Typecho 后台的头像也走主题设置里的 Gravatar 加速地址 */
if (!defined('__TYPECHO_GRAVATAR_PREFIX__')) {
    try {
        $wfDb    = \Typecho\Db::get();
        $wfTheme = $wfDb->fetchRow($wfDb->select('value')->from('table.options')
            ->where('name = ?', 'theme')->limit(1));
        $wfTheme = $wfTheme ? (string) $wfTheme['value'] : '';
        $wfRow   = $wfTheme !== '' ? $wfDb->fetchRow($wfDb->select('value')->from('table.options')
            ->where('name = ?', 'theme:' . $wfTheme)->limit(1)) : null;
        $wfRaw   = $wfRow ? (string) $wfRow['value'] : '';
        // Typecho 1.2 起主题设置以 JSON 存储（旧版为 PHP serialize），两种都兼容
        $wfConf  = json_decode($wfRaw, true);
        if (!is_array($wfConf)) { $wfConf = @unserialize($wfRaw); }
        $wfCdn   = (is_array($wfConf) && !empty($wfConf['gravatarCdn'])) ? trim($wfConf['gravatarCdn']) : '';
        $wfDir   = $wfCdn !== '' ? rtrim($wfCdn, '/') : 'https://www.gravatar.com';
        if (!preg_match('~/avatar$~', $wfDir)) { $wfDir .= '/avatar'; }
        define('__TYPECHO_GRAVATAR_PREFIX__', $wfDir . '/');
    } catch (\Throwable $e) {
        /* 读不到就沿用官方，绝不影响站点 */
    }
}
```

> `__TYPECHO_GRAVATAR_PREFIX__` 是 Typecho 官方提供的覆盖点，它被当作**完整前缀**
> （后面直接接 md5 值），所以必须以 `/avatar/` 结尾。
> 如果不想动态读主题设置，也可以直接写死一行：
> `define('__TYPECHO_GRAVATAR_PREFIX__', 'https://cn.cravatar.com/avatar/');`

**投票（只有点赞，没有点踩）**：

- 计数同样存在 Typecho **原生的自定义字段**里（`typecho_fields` 表），**不新增数据表**：
  - 文章点赞：`likes`（十进制字符串，存在该文章上）；
  - 评论点赞：`comment_likes`（JSON，形如 `{"<coid>": 赞数}`，存在该评论所属的文章上）。
- 前端向**当前页面地址** POST（字段 `weui_vote` / `weui_id` / `weui_dir=like`），
  `functions.php` 顶部识别到该字段就接管请求、输出 JSON 并结束——**不需要新增路由或插件**。
- 互动规则（`weuiVotedCookieName` 记录的 cookie 为 `weui_voted_p<cid>` / `weui_voted_c<coid>`）：
  - **点一次 = 点赞，再点一次 = 取消**；
  - 接口返回本次操作后的 `state`（`''` / `like`）与最新的 `likes`，前端据此刷新按钮高亮与数字；
  - 计数不会出现负数。
- 后台可分别开关文章、评论的点赞按钮。

> ⚠️ **计数器必须在 `themeFields()` 里注册**（`views` / `likes` / `comment_likes` 三个 `Hidden` 字段）。
> 原因：Typecho 保存文章时会**删除「库里存在、但本次表单没提交」的自定义字段**
> （`EditTrait::applyFields()`）。若这几个字段没注册进后台表单，编辑一次文章就会把计数清零。
> 注册成隐藏字段后，值会随表单原样回写，计数因此不会丢。

> 弹窗（评论信息 / 打赏）的显隐用 **`display` + CSS keyframes**，而不是 WeUI 的
> `.weui-transition` 过渡类。原因是 `visibility` 的过渡在部分浏览器里关闭时不会真正变回
> `hidden`，会出现「点关闭没反应」；`display` 切换是确定性的。

## 独立页面模板

主题内置 5 个自定义页面模板，新建独立页面时在「自定义模板」里选择对应项即可：

| 模板文件 | 名称 | 说明 |
| --- | --- | --- |
| `archives.php` | 归档 | **时间线**：按年月分组（`2026年10月` 胶囊），每条「左日期 + 竖线圆点 + 右标题」。 |
| `categories.php` | 分类 | 列出所有分类及各分类文章数，点击进入分类归档。 |
| `gallery.php` | 相册 | 列出**相册文章**（`album=1`）；点进去是该文章的相册（见下方「相册」）。 |
| `links.php` | 友链 | 正文里按行填写 `网站名\|网址\|图片\|介绍`，以无边框九宫格展示，全部新窗口打开，支持评论。 |
| `about.php` | 关于 | 通用空白页，直接渲染页面正文，可开启评论。 |

### 友链页格式

在友链页面的正文里按行填写，每行一条，用竖线分隔，图片与介绍可留空：

```
Typecho|https://typecho.org|https://typecho.org/logo.png|一个轻量高效的博客程序
WeUI|https://weui.io
某个朋友的博客|https://example.com||记录生活与代码
```

- 链接都会以 `target="_blank"` 新窗口打开，并带 `rel="noopener nofollow"`。
- 一行里没写网址时该行会被忽略；没写图片则只显示文字。
- 无插件的纯解析实现；若你装了友链插件，可自行替换 `links.php` 的解析逻辑。

### 相册

相册用**自定义字段** `album` 标记：

- 编辑一篇文章，在「自定义字段」里把 **相册** 下拉选成 **「是」**（默认是「否」），这篇就是相册文章。
  该字段存为 `1` / `0`，历史数据里手工填的 `1` 照旧识别；
- 相册文章**不会出现在首页、分类、归档和搜索**里 —— 这一点是在 **SQL 查询层**排除的
  （`functions.php` 里挂 `Widget\Archive::query` 钩子，给查询加 `cid NOT IN (相册 cid)` 并同步修正
  `countSql`），而不是只在输出时跳过。否则数据库查询和分页仍会算上相册文章，
  导致「每页条数变少、总页数偏多」；
- 「相册」页面（`gallery.php`）只列出**有内容的相册文章**（有缩略图，或正文里有图片/视频），
  且页面结构**与首页完全相同**
  （直接在 `.page` 下输出 `weui-panel` + 卡片，不套 `article-wrap` / `weui-article`）；
  相册列表**只呈现图文卡片**，即每张都是 `weui-media-box_appmsg`（没有内容的相册不渲染）；
- 点进相册文章后用 `gallery_album.php` 展示：
  - **正方形网格**展示该文章正文里的全部媒体，按正文出现顺序**依次排列**
    （CSS Grid，移动端 3 列、≥641px 4 列，不再瀑布流）；每张缩略图用
    `padding-top:100%` 撑成正方形 + `object-fit:cover` **居中裁剪**，
    所以不同比例的图片也不会变形、不会错落；
  - **图片 + 视频**：正文里的 `<img>` 作为图片，`<video>` 作为视频；
  - **懒加载**：先占位块（图片浅灰、视频深灰），进入视口（提前 200px）后换真图并淡入（`.wf-img` `opacity:0 → 1`）；
  - **视频缩略图**：优先用 `<video poster="…">` 作封面，没有封面则显示深色块 + 居中播放按钮（`.wf-play`）；
  - **灯箱统一用 [Fancybox v5](https://fancyapps.com/fancybox/)**（`assets/fancybox.umd.js` + `assets/fancybox.css`）：
    - 相册格子带 `data-fancybox="wf-album"` 归为一组，可左右切换；
    - 图片走图片灯箱，视频（`data-type="html5video"`）用 Fancybox 生成的 `<video controls>` 播放；
    - 自带缩略图条、工具栏、键盘 ←/→、移动端滑动、Esc 关闭、点击空白关闭；
    - 说明文字走 `data-caption`（图片取 `alt`，视频依次取 `title` → `alt` → `data-caption`）；
  - **正文里的图片 / 视频同样接入同一个灯箱**：`weuiPrepareContent()` 会把正文中的
    `<img>` / `<video>` 包成 `data-fancybox="wf-content"` 触发器（视频带 `data-poster`），
    普通文章页也能点击放大 / 播放，不必是相册文章；
  - 相册文章**可以评论**（评论区在网格下方，与普通文章页一致）。

**视频写法**：直接在正文里插入 raw HTML，Typecho 的 Markdown 会原样保留（已实测）：

```html
<video src="https://…/a.mp4" poster="https://…/cover.jpg" controls title="说明文字"></video>
```

也可以用 `<video controls><source src="…"></video>`（没有 `src` 时主题会去读 `<source>`）。
`poster` 是相册里的封面图，建议填；`title` 会作为灯箱底部的说明。

图片来源：正文里的 `<img>`。普通文章页也会自动为正文图片加上懒加载（灰色占位 + 淡入）。

### 评论区

评论列表在 `functions.php` 里通过覆盖 Typecho 的 `threadedComments()` 主题函数重写（不改核心）：

- 每条评论一行头部：**小头像 + 昵称 + 时间 + 操作区（点赞、回复）**，操作区靠右
  （与默认主题的「回复」同位置）；内容在下方、与昵称左对齐；
- **所有评论平级输出在同一个 `<ol class="comment-list">` 里**：`threadedComments()` 输出完当前
  `<li>` 后直接递归输出子评论作为**兄弟节点**，不再包 `<div class="comment-children">`，
  所以**整页只有一个 `<ol>`、DOM 不嵌套**。这是「上下评论间距对不齐」的根因修复——
  旧实现真的按树递归包容器，只靠 CSS 把深层 margin 归零来「假装只有一层」，
  结果各层 padding 不同（顶层 `10px 16px`、子层 `8px 0`）叠加，间距就乱了。
  注意：核心的 `threadedComments()` 会在**每一层子评论组**前后各 echo 一次
  `listComments()` 的 `before/after`，因此 `comments.php` 里手动包一层，并把
  `before`/`after` 传成空串，避免递归再产出嵌套 `<ol>`；
- **缩进只缩一层**：所有 `.comment-child`（深度 ≥ 1）统一 `margin-left: 36px`，
  第二层及更深**不再继续缩进**（视觉上与第一层子回复平级），
  改为在内容开头显示 **`@某某`**（`weuiCommentParentAuthor()` 取被回复评论的作者）。
  `@某某` **和正文在同一行**（`weuiCommentBody()` 把它插进正文第一个 `<p>` 内部），
  且**只在评论输出时显示，不会写进输入框**；
- **尽量少用线条**：评论条目之间没有分隔线，也不给嵌套画竖线，层级靠缩进与 `@某某` 区分；
  表单区与列表之间也只留间距，不画线；
- **主评论与子回复的间距一致**：所有条目统一 `padding: 10px 16px`，只有 `margin-left` 区分层级，
  视觉节奏统一（已验证：相邻条目的间隙恒为 0，仅由相同的 padding 分隔）；
- 头像由 `weuiCommentAvatar()` 输出，**全站统一走后台的 CDN 设置**（`weuiGravatarUrl()`）；
  请求尺寸 56px（显示 28px）。**不使用** Typecho 自带的 `$comments->gravatar()`——
  它固定走 `Common::gravatarUrl()`，会绕过后台的加速地址设置；
- 每条评论头部右侧是 **点赞 + 回复**（点赞可在后台关闭）。

**回复按钮**：**不用** Typecho 自带的 `$comments->reply()`。它内部有 `isTopLevel` 判断
（`levels > commentsMaxNestingLevels - 2` 就不输出），于是**嵌套最深的那一层评论会没有回复按钮**
——这就是线上「有几条不能回复」的原因。`functions.php` 的 `weuiCommentReplyLink()` 自己输出，
链接格式与官方一致（`?replyTo=coid#respondId`），只要允许评论就始终显示。

**回复框位置**：Typecho 自带的 `TypechoComment.reply()` 会把表单插到「回复按钮所在的直接子元素」
之后，而回复按钮在头部行里，于是回复框会插到头像行和正文之间。主题在
`assets/theme.js` 里用捕获阶段的点击监听**拦掉**它的跳转，自己把表单插到被回复评论的
**正文之后**（即该 `<li>` 的末尾）——由于子评论现在是平级的兄弟节点、排在 `<li>` 之后，
表单位置在文档顺序上仍然是「正文之后、子回复之前」，并写入 `parent` 隐藏字段；
「取消回复」把表单放回原位**并清空已写的内容**（避免下次评论还残留着回复别人的文字）。

> ⚠️ **提交前必须先还原表单**：回复时表单被移进了 `.comment-list-wrap` 里面，
> 而无刷新提交后要用返回页面的列表去替换 `.comment-list-wrap`——若不先把表单还原，
> 整个表单会被一起替换掉，表现就是**评论完之后评论框没了**。代码里在替换列表前调用了
> `doCancelReply()`，并额外做了一层兜底：万一表单仍不在文档里，就从返回页面里把它补回来。

**被回复目标的取值**：从回复按钮所在 `<li>` 的 `id`（`comment-123`）里取，而不是解析内联
`onclick`。因为无刷新提交后新插入的评论节点**不含 Typecho 注入的 `onclick`**，靠解析 `onclick`
会取不到 coid 导致「新评论点回复没反应，刷新后才正常」。
另外若当前正停在别的评论里回复，会先把表单还原再插到新目标，避免错位。

**评论支持 Markdown**：`functions.php` 在**前台渲染时**把 `commentsMarkdown` 打开，
并把 `commentsHTMLTagAllowed` 合并进表格 / 列表 / 引用 / 代码块等标签（Typecho 默认只保留
`<p><br>`，会把 Markdown 生成的表格、列表、代码块剥掉，这是「评论不支持表格」的根因）。
这两项都是**内存级**修改，不动数据库；因此**无论后台「设置 → 评论」里是否勾选
「在评论中使用 Markdown 语法」，前台评论都按 Markdown 渲染**。

**评论表单**（富文本输入 + 表情/贴纸，游客信息走弹窗）：

- 输入框是 **contenteditable 富文本**（无 JS 时回退为原生 textarea，依然能提交）；
- 底部工具栏：左侧「表情」按钮、右侧「发送」（发送按钮只有文字，无图标）；
- 点「表情」在工具行上方**向上展开**面板（覆盖住输入框），分 **贴纸 / 表情** 两个标签；
  点任一表情即**所见即所得**地插入图片并**自动收起面板**，不会出现 `:smile:` 这类代码；
- 游客身份行显示一个 **Gravatar 小头像** +「游客评论」+ **小铅笔图标**：点铅笔用 **WeUI 弹窗**
  （`.weui-dialog`，参考 ngx-weui dialog 示例）填写 **昵称 / 邮箱 / 网站**；
  在弹窗里输入邮箱时头像会**实时跟着变**（前端内置一份 md5 算 Gravatar 地址，地址前缀用后端渲染在
  `data-avatar-base` 上的 CDN 前缀，与后台设置完全一致）；信息写入浏览器
  `localStorage`，以后自动回填；填过后身份行显示昵称，再点铅笔即可修改；
- **点「发送」时若内容为空、或游客还没填必填信息，会先提示/弹出信息弹窗**，不发起提交；
- **登录用户不显示铅笔与弹窗**：已登录时无需填写昵称邮箱。

**表情 / 贴纸是怎么存的**：素材放在 `assets/emoticons/`（贴纸 `stickers/*.png`、表情
`emoji/*.svg`），名单由 `assets/emoticons/manifest.json` 提供。编辑器插入时在 DOM 里放
`<img data-shortcode="[sticker:名字]">` 这类图片（预览用），提交前把它序列化成
**短代码文本**（`[sticker:熬夜]` / `[emoji:101]`）存进数据库；输出时 `functions.php`
的 `weuiEmoticonsInHtml()` 再把短代码换回主题内置图片（名字必须命中名单，因此无注入风险）。
图片地址用 `Common::url()` 基于 `$options->themeUrl` 属性拼接（注意不能在全局函数里调用
`$options->themeUrl('...')`——那是输出方法，会 echo 并返回 null）。
素材可用 `python tools/fetch-emoticons.py` 重新拉取。

**表情大小**：`.wf-biaoqing` / `.wf-sticker` 都设为 `1.5em`（约字号的 1.5 倍），
候选项 `.comment-emoji__item img` 也是 `1.5em` 且固定 `font-size: 15px`，
所以**待选、编辑器预览、评论输出三处大小完全一致**。

**无刷新提交与反垃圾令牌**：`assets/theme.js` 拦截评论表单提交，用 `fetch` POST 提交后，
**只把返回页面里的「评论列表」换进来，不替换整个 `#comments`**（即不替换表单本身）。
这是参考同目录下 handsome 主题的做法：表单留在原地，它那份有效的 `_` 令牌就永远不会被换掉，
也就不存在「第二次提交令牌对不上」的问题。

评论文本存的就是普通文本，表情/贴纸在服务端渲染成图片，所以提交内容本身没有特殊之处。
反垃圾令牌 `_` 有两处坑：

1. **它会丢**。Typecho 默认是前端 JS 在 scroll / mousemove / keyup / touchstart 之后才把隐藏域
   `_` 插进表单；PJAX 换页或评论无刷新替换评论区后这个隐藏域就没了，提交时
   `Security::protect()` 校验不过。→ 主题改为在服务端用 `weuiAntiSpamToken()`（`functions.php`）
   直接渲染进表单，不再依赖滚动事件。
2. **它的「基准地址」会变**。令牌 = `md5(secret & 当前请求地址)`，校验时用的是 **Referer**；
   提交成功后 Typecho 会 302 到 `/文章/comment-page-N`，返回 HTML 里的 `_` 是按那个地址算的，
   与浏览器的 Referer（文章页）对不上，于是下一次提交会被 `goBack()` 退回。
   → 不替换表单（上面的做法）后，本地这份始终是「按文章页算」的正确令牌，问题自然消失。

**结果判定**：Typecho 的失败可能返回 500/403，也可能被 `goBack()` 退回并返回 **200 的文章页**，
光看状态码分不出来。主题同时用四个信号判断是否真的写入（任一成立即算成功）：

1. 最终地址里带 `#comment-COID` 锚点（提交成功的 302 目标）；
2. 返回页面「已有 N 条评论」的总数 +1（该总数含嵌套回复，不受评论分页影响）；
3. 返回页面里能看到刚提交的那段文字（**审核通过**才会渲染出来）；
4. `__typecho_unapproved_comment` cookie 出现了新的值（**待审核**时才会写）。

判为成功后再区分两种情况：当前页能看到新评论就滚动过去；看不到就提示「已提交，等待审核通过后显示」。
若四个信号都不成立，才报「提交失败，请重试」，并**保留本地表单和已写的内容**。
服务端返回错误页时，把页面上的错误文字（如「必须填写评论内容」「您的发言过于频繁」）直接显示出来。

> 为什么要四个信号：`待审核` 的评论**不会**出现在返回 HTML 里（信号 2、3 失效），
> 只有 cookie（信号 4）能证明它写进去了；而锚点（信号 1）在部分环境下会被下划线替换。

另外，游客漏填昵称/邮箱时弹窗确认后**会自动继续提交**，不用再点一次「发送」。

**提交中的反馈**：按钮本身进入加载态——**文案变「提交中」+ 按钮内显示一圈转动的加载图 + 禁用**，
提交结束（无论成功失败）自动恢复成「发送」。不再在按钮下方另外显示「提交中…」文字
（那个提示只用于显示错误原因，例如「您的发言过于频繁」）。加载图用的是 WeUI 官方的
`.weui-loading`，为了在绿色按钮上看得清，另配了一份白色版的内联 SVG（`.comment-submit__spin`）。

**评论翻页**：后台「设置外观 → 评论翻页方式」可选「分页」或「加载更多」。
需要先在 Typecho「设置 → 评论」里开启评论分页；「加载更多」会抓取下一页并追加到列表底部。

**Ctrl / Cmd + Enter** 也可直接提交评论。

对应样式都在 `style.css` 的 `.comment-*` / `.comment-form` / `.comment-toolbar` / `.comment-emoji*` / `#comment-profile-*` 等规则里。

### 自定义字段

`functions.php` 里的 `themeFields()` 预置了字段，编辑文章时自动出现，无需手动添加：

| 字段 | 名称 | 说明 |
| --- | --- | --- |
| `album` | 相册 | **下拉选择「否 / 是」**，默认「否」；选「是」即为相册文章（见上）。 |
| `thumb` | 缩略图 | 列表缩略图地址；留空时自动取正文第一张图片。 |

> 计数器字段（`views` / `likes` / `comment_likes`）也会注册进这里，但渲染为隐藏项，
> 目的是让它们能随表单原样回写（原因见「投票」与「阅读次数」两节）。

### 搜索结果

主题提供 `search.php`（Typecho 保留模板名），用于 `/search/<关键词>/` 搜索页：
标题「包含关键字 %s 的文章」+ 结果列表 + 分页；无结果时显示「没有找到相关内容 / 换一个关键词试试」。
**搜索页里不再有额外的搜索输入框**——页面顶部只有 header 里那个按需展开的搜索栏（默认隐藏）。
搜索模板与 `index.php` 的结构一致（相册文章同样跳过），并已纳入 `weuiPagination()` 的列表类型判断。

### 归档类型模板

- `category.php` 是**分类归档页**的模板，输出分类名与该分类下的文章；
- `archive.php` 是**通用归档模板**（标签 / 作者 / 日期等），渲染结构与首页列表一致；
- `archives.php` 才是后台可选的「归档」**独立页面模板**（按月分组）。

> ⚠️ **不要在归档模板里用 `Recent::alloc()` 之类的组件重查**
> `archive.php` 是所有归档类型的兜底模板。早期版本曾在这个位置做「按月归档」，
> 并在里面调用了 `Recent::alloc()`——它会**重置 Archive 组件自身的查询状态**，
> 导致随后渲染列表时分页参数为 null 而报错。归档列表应直接使用模板里现成的
> `$this->have()` / `$this->next()` 游标。
>
> 另外，`weuiPagination()` 现在只对 `index` / `archive` 类型渲染，其它页面调用会直接返回，
> 避免独立页面（最终也会回退到 `index.php`）误触发分页而读不到参数。

> ⚠️ **命名警告**：`archive.php`、`single.php`、`category.php`、`tag.php`、`author.php`、
> `date.php`、`search.php`、`post.php`、`page.php`、`index.php`、`404.php` 都是 **Typecho 保留模板名**，
> 会被自动用于对应的路由。自定义页面模板**不要**占用这些名字——例如归档页用
> `archives.php`（复数）而不是 `archive.php`。
>
> 「自定义模板」下拉里显示的是**文件名**（Typecho 用 `Plugin::parseInfo()` 解析
> 主题目录下所有 `*.php` 的 docblock，`@package custom` 的会进入下拉）。
> 因此本主题的模板 docblock 里**只保留标题、不写说明文字**，下拉更清爽；
> `archive.php` 刻意不加 `@package custom`，所以不会出现在下拉里。

说明：

- Typecho 1.3 核心已不含友链组件，所以友链页采用「页面正文解析」的方式，无需插件；
  若你安装了友链插件，可自行替换 `links.php` 里的解析逻辑。
- 归档用文章时间戳分组（`$posts->created`），而不是 `$posts->date()`——后者是直接
  `echo` 输出且无返回值，不能用于赋值。

## 首页图文列表

首页使用官方的 `weui-media-box_appmsg`（左图右文）展示文章：

- 缩略图自动取**正文中的第一张图片**；如需手动指定，在文章编辑页的「自定义字段」里
  添加名为 `thumb` 的字段，填入图片 URL 即可（优先级高于正文首图）。
- 没有图片的文章会自动退化为官方的 `weui-media-box_text`（纯文字）列表项。
- 缩略图尺寸沿用官方 `__hd` 结构，主题内仅把它从 60×60 调为 80×80 并裁切，属于视觉微调。
- **图片 + 文字一行、元信息独占整行**：卡片结构是
  `.weui-media-box.wf-card > (.wf-card__main > 图 + 文字) + .weui-media-box__info`。
  官方 `.weui-media-box_appmsg` 本身是 flex，会把元信息挤在图片右侧，所以主题用
  `.weui-media-box_appmsg.wf-card { display: block }` 把盒子的 flex 去掉，改由内层
  `.wf-card__main` 承担「图 + 文」的横排，元信息作为盒子的直接子元素铺满整行。
- 列表项本身是 `<div>`（不再是整条可点的 `<a>`），**只有标题是链接**（`.wf-card-title`，
  点标题才进入文章）。这样元信息里的时间才能独立响应点击（切换完整时间），不与「整条跳转」冲突。
- 元信息固定为四个字段（见「相对时间、阅读次数与元信息」）：作者 · 时间 · 分类 · 阅读次数，
  **各带一个小图标**，其中作者 / 分类可点击但颜色与其它字段一致。
- 无图列表项显式设 `display: block`，让 `weui-media-box` 的 16px 内边距正常生效。

## 页面分隔

站点头部与内容演示保持一致的节奏：首页列表（`weui-panel`）自带 10px 上边距；
文章页的正文区包在 `.article-wrap` 里，同样留出 10px 灰色间隙，再显示白色内容块。

## 新增 WeUI 组件

以后需要按钮、表单、搜索框等组件时：

1. 下载官方 dist：<https://raw.githubusercontent.com/Tencent/weui/master/dist/style/weui.css>
2. 编辑 `tools/build-weui-subset.py`，把对应组件选择器加进 `WIDGET_TOKENS`（如 `.weui-btn`）。
3. 运行：

   ```bash
   python tools/build-weui-subset.py weui.css --out assets/weui.css
   ```

脚本会重新生成最小子集：只保留新增组件所需的规则，以及它们引用的主题变量。

> 说明：本项目基于 WeUI v2.6.26 生成。子集中的样式版权归腾讯所有（MIT License），
> 详见 [WeUI](https://github.com/weui/weui)。
