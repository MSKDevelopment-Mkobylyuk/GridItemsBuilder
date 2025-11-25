<?php
/*
Plugin Name: Grid Item Builder
Description: Unlimited product grids with repeater items, image selector, up/down ordering, and lightbox. Uses [GridItems id="123456"].
Version: 2.3
Author: MSK Development LLC
*/

/* -----------------------------------------------------
   1. Register Custom Post Type
----------------------------------------------------- */
add_action('init', function() {
    register_post_type('grid_builder', [
        'labels' => [
            'name'          => 'Grid Builders',
            'singular_name' => 'Grid Builder'
        ],
        'public'      => true,
        'show_ui'     => true,
        'menu_icon'   => 'dashicons-screenoptions',
        'supports'    => ['title'],
    ]);
});


/* -----------------------------------------------------
   2. Auto-generate 6-digit Grid ID
----------------------------------------------------- */
add_action('save_post_grid_builder', function($post_id) {
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) return;

    if (!get_post_meta($post_id, '_grid_id', true)) {
        update_post_meta($post_id, '_grid_id', rand(100000, 999999));
    }
});


/* -----------------------------------------------------
   3. Add Meta Box
----------------------------------------------------- */
add_action('add_meta_boxes', function() {
    add_meta_box(
        'grid_builder_meta',
        'Grid Settings & Items',
        'grid_builder_meta_callback',
        'grid_builder',
        'normal',
        'high'
    );
});

function grid_builder_meta_callback($post) {

    $grid_id = get_post_meta($post->ID, '_grid_id', true);
    $columns = get_post_meta($post->ID, '_grid_columns', true);
    $items   = get_post_meta($post->ID, '_grid_items', true);

    if (!is_array($items)) $items = [];

    wp_nonce_field('save_grid_builder_meta', 'grid_builder_nonce');
?>
<style>
    .grid-item-block { border:1px solid #ccc; padding:10px; margin-bottom:10px; background:#f9f9f9; }
    .grid-item-heading { font-weight:bold; margin-bottom:8px; }
    .grid-preview-img { width:80px; margin-top:5px; display:block; }
    .grid-item-controls button { margin-right:5px; }
</style>

<h3>Grid Metadata</h3>

<?php $shortcode = '[GridItems id="'.$grid_id.'"]'; ?>

<p>
    <label><strong>Shortcode for this Grid:</strong></label><br>
    <input type="text" id="grid-shortcode" 
           value="<?php echo esc_attr($shortcode); ?>" 
           style="width:300px;" readonly>
    <button type="button" class="button" id="copy-grid-shortcode">Copy</button>
</p>

<script>
jQuery(function($){
    $('#copy-grid-shortcode').on('click', function(){
        let input = document.getElementById('grid-shortcode');
        input.select();
        navigator.clipboard.writeText(input.value);
        alert("Shortcode copied!");
    });
});
</script>

<p><strong>Grid ID:</strong> <?php echo esc_html($grid_id); ?></p>

<p>
    <label><strong>Columns:</strong></label><br>
    <input type="number" min="1" max="12" style="width:80px;"
           name="grid_columns" value="<?php echo esc_attr($columns); ?>">
</p>

<hr>

<h3>Grid Items</h3>

<div id="grid-items-wrapper">
    <?php foreach ($items as $index => $item): grid_builder_item_block($index, $item); endforeach; ?>
</div>

<button type="button" id="add-grid-item" class="button button-primary">Add Item</button>

<script>
jQuery(function($){

    $('#add-grid-item').on('click', function(){
        let count = $('.grid-item-block').length;
        $.post(ajaxurl, { action:'grid_builder_add_item', index:count }, function(res){
            $('#grid-items-wrapper').append(res);
        });
    });

    $(document).on('click', '.move-up', function(){
        let block = $(this).closest('.grid-item-block');
        block.prev().before(block);
    });

    $(document).on('click', '.move-down', function(){
        let block = $(this).closest('.grid-item-block');
        block.next().after(block);
    });

    $(document).on('click', '.remove-item', function(){
        if (confirm("Remove this item?"))
            $(this).closest('.grid-item-block').remove();
    });

    $(document).on('click', '.select-image', function(e){
        e.preventDefault();

        let button = $(this);
        let field  = button.siblings('.image-url');
        let preview = button.siblings('.grid-preview-img');

        let frame = wp.media({ title:'Select Image', button:{ text:'Use Image' }, multiple:false });

        frame.on('select', function(){
            let att = frame.state().get('selection').first().toJSON();
            field.val(att.url);
            preview.attr('src', att.url).show();
        });

        frame.open();
    });

});
</script>

<?php }


/* -----------------------------------------------------
   4. AJAX: Add Repeater Item
----------------------------------------------------- */
add_action('wp_ajax_grid_builder_add_item', function() {
    grid_builder_item_block(intval($_POST['index']), []);
    wp_die();
});

function grid_builder_item_block($index, $item) { ?>
<div class="grid-item-block">

    <div class="grid-item-heading">Item <?php echo ($index+1); ?></div>

    <p><label>Name:</label><br>
    <input type="text" name="grid_items[<?php echo $index; ?>][name]"
           value="<?php echo esc_attr($item['name'] ?? ''); ?>" style="width:100%"></p>

    <p><label>Dimensions:</label><br>
    <input type="text" name="grid_items[<?php echo $index; ?>][dims]"
           value="<?php echo esc_attr($item['dims'] ?? ''); ?>" style="width:100%"></p>

    <p><label>Starting Price (numbers only):</label><br>
    <input type="text" name="grid_items[<?php echo $index; ?>][price]"
           value="<?php echo esc_attr($item['price'] ?? ''); ?>" style="width:100%"></p>

    <p><label>Description:</label><br>
    <textarea name="grid_items[<?php echo $index; ?>][desc]" style="width:100%;height:80px;"><?php echo esc_textarea($item['desc'] ?? ''); ?></textarea></p>

    <p>
        <label>Image:</label><br>
        <input type="text" class="image-url"
               name="grid_items[<?php echo $index; ?>][img]"
               value="<?php echo esc_attr($item['img'] ?? ''); ?>" style="width:80%">
        <button class="button select-image">Select Image</button>
        <img class="grid-preview-img"
             src="<?php echo esc_url($item['img'] ?? ''); ?>"
             <?php echo empty($item['img']) ? 'style="display:none;"' : ''; ?>>
    </p>

    <div class="grid-item-controls">
        <button type="button" class="button move-up">Move Up</button>
        <button type="button" class="button move-down">Move Down</button>
        <button type="button" class="button remove-item">Remove</button>
    </div>

</div>
<?php }


/* -----------------------------------------------------
   5. Save Handler
----------------------------------------------------- */
add_action('save_post_grid_builder', function($post_id){

    if (!isset($_POST['grid_builder_nonce']) ||
        !wp_verify_nonce($_POST['grid_builder_nonce'], 'save_grid_builder_meta'))
        return;

    update_post_meta($post_id, '_grid_columns', sanitize_text_field($_POST['grid_columns'] ?? ''));

    if (!isset($_POST['grid_items']) || !is_array($_POST['grid_items'])) return;

    $clean = [];

    foreach ($_POST['grid_items'] as $item) {

        // Format price
        $raw = trim($item['price'] ?? '');
        if ($raw !== '') {
            $num = floatval(preg_replace('/[^0-9\.]/', '', $raw));
            $formatted = 'Starting at $' . number_format($num, 2);
        } else {
            $formatted = '';
        }

        $clean[] = [
            'name'  => sanitize_text_field($item['name'] ?? ''),
            'dims'  => sanitize_text_field($item['dims'] ?? ''),
            'price' => $formatted,
            'desc'  => sanitize_textarea_field($item['desc'] ?? ''),
            'img'   => esc_url_raw($item['img'] ?? '')
        ];
    }

    update_post_meta($post_id, '_grid_items', $clean);
});


/* -----------------------------------------------------
   6. Shortcode Handler — *ID Required*
----------------------------------------------------- */
add_shortcode('GridItems', function($atts){

    $atts = shortcode_atts(['id' => ''], $atts);

    if (!$atts['id']) return '<div class="ssamish-warning">⚠ No Grid ID provided.</div>';

    // Find post with matching _grid_id
    $query = new WP_Query([
        'post_type'  => 'grid_builder',
        'meta_key'   => '_grid_id',
        'meta_value' => $atts['id'],
        'posts_per_page' => 1
    ]);

    if (!$query->have_posts()) {
        return '<div class="ssamish-warning">⚠ Grid "<strong>'.$atts['id'].'</strong>" not found.</div>';
    }

    $query->the_post();
    $grid_post_id = get_the_ID();
    wp_reset_postdata();

    return render_grid_items($grid_post_id);
});


/* -----------------------------------------------------
   7. Grid Renderer
----------------------------------------------------- */
function render_grid_items($grid_id) {

    $columns = get_post_meta($grid_id, '_grid_columns', true);
    $items   = get_post_meta($grid_id, '_grid_items', true);

    if (!is_array($items)) return '';

    ob_start(); ?>
<section id="ssamish-grid" class="ssamish-products">
    <div class="ssamish-product-grid"
        style="<?php echo $columns ? 'grid-template-columns:repeat('.intval($columns).',1fr);' : ''; ?>">

        <?php foreach ($items as $item): ?>
        <div class="ssamish-card"
            data-name="<?php echo esc_attr($item['name']); ?>"
            data-dims="<?php echo esc_attr($item['dims']); ?>"
            data-img="<?php echo esc_url($item['img']); ?>"
            data-desc="<?php echo esc_attr($item['desc']); ?>"
            data-price="<?php echo esc_attr($item['price']); ?>">

            <img src="<?php echo esc_url($item['img']); ?>" alt="<?php echo esc_attr($item['name']); ?>">
            <h3><?php echo esc_html($item['name']); ?></h3>
            <p class="ssamish-dims"><?php echo esc_html($item['dims']); ?></p>
            <a href="#" class="ssamish-btn-outline ssamish-lightbox-btn">View Details</a>
        </div>
        <?php endforeach; ?>

    </div>
</section>

<!-- Lightbox -->
<div id="ssamish-lightbox" class="ssamish-lightbox" style="display:none;">
    <div class="ssamish-lightbox-content">
        <span class="ssamish-lightbox-close">&times;</span>
        <img class="ssamish-lightbox-img" src="" alt="">
        <h3 class="ssamish-lightbox-name"></h3>
        <p class="ssamish-lightbox-dims"></p>
        <p class="ssamish-lightbox-material"></p>
        <p class="ssamish-lightbox-price"></p>
        <p class="ssamish-lightbox-desc"></p>
    </div>
</div>

<?php
    return ob_get_clean();
}


/* -----------------------------------------------------
   8. Frontend CSS
----------------------------------------------------- */
add_action('wp_enqueue_scripts', function() {

    wp_register_style('grid-item-builder-style', false);
    wp_enqueue_style('grid-item-builder-style');

    wp_add_inline_style('grid-item-builder-style', '
        :root { --ss-main:#769687; }

        /* Warning box */
        .ssamish-warning {
            padding: 12px 16px;
            background: #fff3cd;
            border: 1px solid #ffeeba;
            border-radius: 6px;
            color: #856404;
            margin-bottom: 20px;
            font-size: 16px;
        }

        .ssamish-products { padding: 4rem 2rem; text-align:center; width:100%; }

        .ssamish-product-grid {
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
            gap:2rem;
            max-width:1200px;
            margin:0 auto;
        }

        .ssamish-card {
            padding:1rem;
            border-radius:16px;
            display:flex;
            flex-direction:column;
            transition:0.3s;
        }

        .ssamish-card:hover { transform:translateY(-5px); }

        .ssamish-card img {
            width:100%;
            aspect-ratio:4/3;
            object-fit:contain;
            border-radius:12px;
            padding:6px;
        }

        .ssamish-btn-outline {
            border:2px solid var(--ss-main);
            color:var(--ss-main);
            padding:0.5rem 1.25rem;
            border-radius:50px;
            margin-top:auto;
            text-decoration:none;
            transition:0.3s;
        }

        .ssamish-btn-outline:hover {
            background:var(--ss-main);
            color:#fff;
        }

        /* Lightbox */
        .ssamish-lightbox {
            display:none;
            position:fixed;
            top:0; left:0;
            width:100%; height:100%;
            background:rgba(0,0,0,0.7);
            z-index:9999;
            justify-content:center;
            align-items:center;
        }

        .ssamish-lightbox-content {
            background:#fff;
            padding:2rem;
            border-radius:12px;
            max-width:500px;
            width:90%;
            position:relative;
            text-align:center;
        }

        .ssamish-lightbox-close {
            position:absolute;
            top:10px; right:15px;
            font-size:2rem;
            cursor:pointer;
        }

        .ssamish-lightbox-img {
            width:100%;
            border-radius:12px;
            margin-bottom:1rem;
        }
    ');
});


/* -----------------------------------------------------
   9. Lightbox JS
----------------------------------------------------- */
add_action('wp_enqueue_scripts', function() {

    wp_register_script('grid-item-builder-lightbox', false);
    wp_enqueue_script('grid-item-builder-lightbox');

    wp_add_inline_script('grid-item-builder-lightbox', "
document.addEventListener('DOMContentLoaded', () => {
    const lightbox = document.getElementById('ssamish-lightbox');
    if (!lightbox) return;

    const lbImg   = lightbox.querySelector('.ssamish-lightbox-img');
    const lbName  = lightbox.querySelector('.ssamish-lightbox-name');
    const lbDims  = lightbox.querySelector('.ssamish-lightbox-dims');
    const lbMat   = lightbox.querySelector('.ssamish-lightbox-material');
    const lbPrice = lightbox.querySelector('.ssamish-lightbox-price');
    const lbDesc  = lightbox.querySelector('.ssamish-lightbox-desc');
    const close   = lightbox.querySelector('.ssamish-lightbox-close');

    const fill = (el,val,{prefix=''}={})=>{
        if(!el)return;
        if(val && String(val).trim()){
            el.textContent = prefix+val;
            el.style.display='';
        } else {
            el.textContent='';
            el.style.display='none';
        }
    };

    document.querySelectorAll('.ssamish-lightbox-btn').forEach(btn=>{
        btn.addEventListener('click',e=>{
            e.preventDefault();
            const card = e.currentTarget.closest('.ssamish-card');
            const d = card.dataset;

            lbImg.src = d.img;
            lbImg.alt = d.name;

            fill(lbName,d.name);
            fill(lbDims,d.dims);
            fill(lbMat,d.material,{prefix:'Material: '});
            fill(lbPrice,d.price);
            fill(lbDesc,d.desc);

            lightbox.style.display='flex';
            document.body.style.overflow='hidden';
        });
    });

    const closeBox=()=>{
        lightbox.style.display='none';
        document.body.style.overflow='';
        lbImg.src='';
    };

    close?.addEventListener('click', closeBox);
    lightbox.addEventListener('click', e=>{ if(e.target===lightbox) closeBox(); });
    document.addEventListener('keydown', e=>{ if(e.key==='Escape' && lightbox.style.display==='flex') closeBox(); });
});
    ");
});

?>
