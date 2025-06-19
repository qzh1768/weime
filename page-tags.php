<?php
/**
 * Template Name: 标签聚合页
 * Template Post Type: page
 */
get_header();

// 带缓存的标签数据获取（按文章更新时间排序）
$tags_cache_key = 'zib_tags_by_update_' . get_queried_object_id();
$tags = get_transient($tags_cache_key);

if (false === $tags) {
    // 获取所有标签
    $all_tags = get_tags(array(
        'number'     => 1000,
        'hide_empty' => false
    ));
    
    // 为每个标签获取最新文章的更新时间
    $tags_with_last_update = array();
    foreach ($all_tags as $tag) {
        $args = array(
            'tag_id' => $tag->term_id,
            'numberposts' => 1,
            'orderby' => 'post_date',
            'order' => 'DESC'
        );
        $recent_posts = get_posts($args);
        
        $last_update = '';
        if (!empty($recent_posts)) {
            $last_update = $recent_posts[0]->post_date;
        }
        
        $tags_with_last_update[] = array(
            'tag' => $tag,
            'last_update' => $last_update
        );
    }
    
    // 按最后更新时间排序
    usort($tags_with_last_update, function($a, $b) {
        return strcmp($b['last_update'], $a['last_update']);
    });
    
    // 提取排序后的标签
    $tags = array_map(function($item) {
        return $item['tag'];
    }, $tags_with_last_update);
    
    set_transient($tags_cache_key, $tags, 6 * HOUR_IN_SECONDS); // 缓存6小时
}
?>

<!-- 基础SEO优化 -->
<title><?php the_title(); ?> - <?php bloginfo('name'); ?></title>
<meta name="description" content="<?php echo esc_attr('本站标签聚合页，按文章更新时间排序展示所有内容标签'); ?>">
<link rel="canonical" href="<?php echo esc_url(get_permalink()); ?>">

<div class="zib-tags-grid-container">
    <div class="container">
        <?php while (have_posts()) : the_post(); ?>
            <h1 class="page-title"><?php the_title(); ?></h1>
            <?php the_content(); ?>
        <?php endwhile; ?>

        <?php if ($tags && !is_wp_error($tags)) : ?>
            <div class="zib-tags-grid">
                <?php foreach ($tags as $tag) : ?>
                    <a href="<?php echo esc_url(get_tag_link($tag)); ?>" 
                       class="zib-tag-card lazy-load"
                       data-bg="var(--theme-card-bg)"
                       title="<?php echo esc_attr($tag->name); ?>">
                        <?php echo esc_html($tag->name); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* 子比主题日夜模式兼容 */
:root {
    --theme-primary: var(--c-33, #333);
    --theme-card-bg: var(--c-fff, #fff);
    --theme-card-bg-hover: var(--c-f7, #f7f7f7);
    --skeleton-color: rgba(0,0,0,0.06);
}

body.night-mode {
    --theme-primary: var(--c-EE, #e0e0e0);
    --theme-card-bg: var(--c-2A, #2a2a2a);
    --theme-card-bg-hover: var(--c-33, #333333);
    --skeleton-color: rgba(255,255,255,0.08);
}

/* 布局样式 */
.zib-tags-grid-container .container {
    max-width: 1800px;
    padding: 0 1rem;
    margin: 0 auto;
}

.page-title {
    color: var(--theme-primary);
    font-size: 1.8rem;
    margin: 1rem 0;
    text-align: center;
}

/* 标签网格布局 */
.zib-tags-grid {
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    padding: 0.5rem 0 2rem;
}

/* 标签卡片设计 */
.zib-tag-card {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    border-radius: 8px;
    background: var(--theme-card-bg);
    color: var(--theme-primary);
    text-align: center;
    transition: all 0.3s ease;
    min-height: 60px;
    font-size: 1.3rem;
    text-decoration: none;
}

.zib-tag-card:hover {
    background: var(--theme-card-bg-hover);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

/* 骨架屏动画 */
@keyframes shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

.zib-tag-card.loading {
    background: linear-gradient(90deg, 
        var(--skeleton-color) 25%, 
        rgba(255,255,255,0.05) 50%, 
        var(--skeleton-color) 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
    color: transparent;
}

/* 响应式设计 */
@media (max-width: 768px) {
    .zib-tags-grid {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    }
    .zib-tag-card {
        padding: 0.8rem;
        font-size: 1.3rem;
        min-height: 50px;
    }
    .page-title {
        font-size: 1.5rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.zib-tag-card');
    
    // 立即显示骨架屏
    cards.forEach(card => card.classList.add('loading'));
    
    // 优化加载逻辑
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const card = entry.target;
                setTimeout(() => {
                    card.classList.remove('loading');
                    card.style.background = getComputedStyle(document.documentElement)
                        .getPropertyValue('--theme-card-bg');
                }, 100); // 添加100ms延迟使动画更自然
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px 100px 0px'
    });

    cards.forEach(card => observer.observe(card));
    
    // 夜间模式切换监听
    const nightModeQuery = window.matchMedia('(prefers-color-scheme: dark)');
    function updateCardColors() {
        cards.forEach(card => {
            if (!card.classList.contains('loading')) {
                card.style.background = getComputedStyle(document.documentElement)
                    .getPropertyValue('--theme-card-bg');
            }
        });
    }
    nightModeQuery.addListener(updateCardColors);
});
</script>

<?php get_footer(); ?>
