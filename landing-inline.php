<?php
/**
 * Plugin Name: Landing Inline (Standalone) - Any URL (Safe)
 * Description: Лендинги с произвольным URL + AI API + Design System + Per-Landing Templates
 * Version: 3.9.2
 */

if (!defined('ABSPATH')) exit;

class Landing_Inline_Any_URL_Safe {

    /* ================= CORE ================= */

    private string $post_type = 'li_landing';

    private string $meta_head   = '_li_head_code';
    private string $meta_body   = '_li_body_code';
    private string $meta_footer = '_li_footer_code';
    private string $meta_css    = '_li_css_code';
    private string $meta_tpl    = '_li_tpl_id'; // NEW
    private string $meta_header_tpl = '_li_header_tpl_id';
    private string $meta_footer_tpl = '_li_footer_tpl_id';

    /* ================= OPTIONS ================= */

    private string $opt_ai_enabled = 'li_ai_enabled';
    private string $opt_ai_token   = 'li_ai_token';

    private string $opt_tpl_enabled = 'li_tpl_enabled';
    private string $opt_tpl_active  = 'li_tpl_active';
    private string $opt_tpl_store   = 'li_css_templates';
    private string $opt_elem_enabled = 'li_element_tpl_enabled';
    private string $opt_header_store = 'li_header_templates';
    private string $opt_footer_store = 'li_footer_templates';
    private string $opt_header_active = 'li_header_active';
    private string $opt_footer_active = 'li_footer_active';

    public function __construct() {

        add_action('init', [$this, 'register_post_type']);
        add_action('template_redirect', [$this, 'handle_routing']);

        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save_meta_boxes']);

        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_post_li_save_ai', [$this, 'save_ai_settings']);
        add_action('admin_post_li_save_templates', [$this, 'save_templates']);

        add_action('rest_api_init', [$this, 'register_ai_routes']);
        add_filter('_wp_post_revision_meta_keys', [$this, 'revision_meta_keys']);
    }

    /* ================= POST TYPE ================= */

    public function register_post_type() {
        register_post_type($this->post_type, [
            'label'        => 'Лендинги',
            'public'       => false,
            'show_ui'      => true,
            'menu_icon'    => 'dashicons-welcome-view-site',
            'supports'     => ['title', 'revisions'],
            'rewrite'      => false,
        ]);
    }

    /* ================= ROUTING ================= */

    public function handle_routing() {

        if (!is_404()) return;

        $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        if (!$path) return;

        $posts = get_posts([
            'post_type'   => $this->post_type,
            'meta_query'  => [[
                'key'   => '_li_url',
                'value' => $path
            ]],
            'post_status' => 'publish',
            'numberposts' => 1
        ]);

        if (!$posts) return;

        status_header(200);
        nocache_headers();

        $post = $posts[0];

        /* ===== CSS TEMPLATE RESOLUTION ===== */

        if (get_option($this->opt_tpl_enabled) === '1') {

            $tpls = get_option($this->opt_tpl_store, []);
            $global_tpl = get_option($this->opt_tpl_active);
            $landing_tpl = get_post_meta($post->ID, $this->meta_tpl, true);

            $tpl_id = ($landing_tpl && $landing_tpl !== 'inherit')
                ? $landing_tpl
                : $global_tpl;

            if (!empty($tpls[$tpl_id]['css'])) {
                echo "<style id='li-css-template'>{$tpls[$tpl_id]['css']}</style>";
            }
        }

        /* ===== HEADER/FOOTER TEMPLATE RESOLUTION ===== */

        $header_tpl_html = '';
        $footer_tpl_html = '';
        $header_tpl_css = '';
        $footer_tpl_css = '';

        if (get_option($this->opt_elem_enabled) === '1') {
            $headers = get_option($this->opt_header_store, []);
            $footers = get_option($this->opt_footer_store, []);

            $global_header = get_option($this->opt_header_active);
            $global_footer = get_option($this->opt_footer_active);

            $landing_header = get_post_meta($post->ID, $this->meta_header_tpl, true);
            $landing_footer = get_post_meta($post->ID, $this->meta_footer_tpl, true);

            $header_id = '';
            if ($landing_header === 'none') {
                $header_id = '';
            } elseif ($landing_header && $landing_header !== 'inherit') {
                $header_id = $landing_header;
            } else {
                $header_id = $global_header;
            }

            $footer_id = '';
            if ($landing_footer === 'none') {
                $footer_id = '';
            } elseif ($landing_footer && $landing_footer !== 'inherit') {
                $footer_id = $landing_footer;
            } else {
                $footer_id = $global_footer;
            }

            if (!empty($headers[$header_id]['html'])) {
                $header_tpl_html = $headers[$header_id]['html'];
            }
            if (!empty($headers[$header_id]['css'])) {
                $header_tpl_css = $headers[$header_id]['css'];
            }

            if (!empty($footers[$footer_id]['html'])) {
                $footer_tpl_html = $footers[$footer_id]['html'];
            }
            if (!empty($footers[$footer_id]['css'])) {
                $footer_tpl_css = $footers[$footer_id]['css'];
            }
        }

        /* ===== LANDING CSS ===== */

        echo "<style id='li-landing-css'>" .
             get_post_meta($post->ID, $this->meta_css, true) .
             "</style>";

        echo
            (!empty($header_tpl_css) ? "<style id='li-header-template-css'>{$header_tpl_css}</style>" : '') .
            (!empty($footer_tpl_css) ? "<style id='li-footer-template-css'>{$footer_tpl_css}</style>" : '') .
            get_post_meta($post->ID, $this->meta_head, true) .
            $header_tpl_html .
            get_post_meta($post->ID, $this->meta_body, true) .
            $footer_tpl_html .
            get_post_meta($post->ID, $this->meta_footer, true);

        exit;
    }

    /* ================= META BOXES ================= */

    public function add_meta_boxes() {

        add_meta_box('li_url', 'URL', [$this,'box_url'], $this->post_type);
        add_meta_box('li_head', 'HEAD', [$this,'box_head'], $this->post_type);
        add_meta_box('li_body', 'BODY', [$this,'box_body'], $this->post_type);
        add_meta_box('li_footer', 'FOOTER', [$this,'box_footer'], $this->post_type);
        add_meta_box('li_css', 'Landing CSS', [$this,'box_css'], $this->post_type);
        add_meta_box('li_tpl', 'CSS Template', [$this,'box_template'], $this->post_type); // NEW
        add_meta_box('li_header_tpl', 'Header Template', [$this,'box_header_template'], $this->post_type);
        add_meta_box('li_footer_tpl', 'Footer Template', [$this,'box_footer_template'], $this->post_type);
    }

    public function box_url($p) {
        echo '<input style="width:100%" name="li_url" value="' .
             esc_attr(get_post_meta($p->ID, '_li_url', true)) . '">';
    }

    public function box_head($p) {
        echo '<textarea style="width:100%;height:120px" name="li_head">' .
             esc_textarea(get_post_meta($p->ID, $this->meta_head, true)) .
             '</textarea>';
    }

    public function box_body($p) {
        echo '<textarea style="width:100%;height:260px" name="li_body">' .
             esc_textarea(get_post_meta($p->ID, $this->meta_body, true)) .
             '</textarea>';
    }

    public function box_footer($p) {
        echo '<textarea style="width:100%;height:120px" name="li_footer">' .
             esc_textarea(get_post_meta($p->ID, $this->meta_footer, true)) .
             '</textarea>';
    }

    public function box_css($p) {
        echo '<textarea style="width:100%;height:120px" name="li_css">' .
             esc_textarea(get_post_meta($p->ID, $this->meta_css, true)) .
             '</textarea>';
    }

    public function box_template($p) {

        $tpls = get_option($this->opt_tpl_store, []);
        $current = get_post_meta($p->ID, $this->meta_tpl, true) ?: 'inherit';

        echo '<select name="li_tpl">';
        echo '<option value="inherit">— Inherit Global Template —</option>';

        foreach ($tpls as $id => $t) {
            echo '<option value="'.esc_attr($id).'" '.selected($current,$id,false).'>'
                 .esc_html($t['name']).'</option>';
        }
        echo '</select>';
    }

    public function box_header_template($p) {

        $tpls = get_option($this->opt_header_store, []);
        $current = get_post_meta($p->ID, $this->meta_header_tpl, true) ?: 'inherit';

        echo '<select name="li_header_tpl">';
        echo '<option value="inherit">— Inherit Global Header —</option>';
        echo '<option value="none"' . selected($current, 'none', false) . '>— No Header —</option>';

        foreach ($tpls as $id => $t) {
            echo '<option value="'.esc_attr($id).'" '.selected($current,$id,false).'>' .
                esc_html($t['name']).'</option>';
        }
        echo '</select>';
    }

    public function box_footer_template($p) {

        $tpls = get_option($this->opt_footer_store, []);
        $current = get_post_meta($p->ID, $this->meta_footer_tpl, true) ?: 'inherit';

        echo '<select name="li_footer_tpl">';
        echo '<option value="inherit">— Inherit Global Footer —</option>';
        echo '<option value="none"' . selected($current, 'none', false) . '>— No Footer —</option>';

        foreach ($tpls as $id => $t) {
            echo '<option value="'.esc_attr($id).'" '.selected($current,$id,false).'>' .
                esc_html($t['name']).'</option>';
        }
        echo '</select>';
    }

    public function save_meta_boxes($id) {

        foreach (['li_url','li_head','li_body','li_footer','li_css','li_tpl','li_header_tpl','li_footer_tpl'] as $f) {
            if (!isset($_POST[$f])) continue;

            if ($f === 'li_url') {
                update_post_meta($id, '_li_url', $_POST[$f]);
            } elseif ($f === 'li_tpl') {
                update_post_meta($id, $this->meta_tpl, sanitize_key($_POST[$f]));
            } elseif ($f === 'li_header_tpl') {
                update_post_meta($id, $this->meta_header_tpl, sanitize_key($_POST[$f]));
            } elseif ($f === 'li_footer_tpl') {
                update_post_meta($id, $this->meta_footer_tpl, sanitize_key($_POST[$f]));
            } else {
                update_post_meta($id, $this->{'meta_' . str_replace('li_', '', $f)}, $_POST[$f]);
            }
        }
    }

    /* ================= ADMIN MENUS ================= */

    public function admin_menu() {

        add_submenu_page(
            "edit.php?post_type={$this->post_type}",
            'AI Settings','AI Settings',
            'manage_options','li-ai',[$this,'ai_page']
        );

        add_submenu_page(
            "edit.php?post_type={$this->post_type}",
            'Design System','Design System',
            'manage_options','li-design',[$this,'design_page']
        );
    }

    /* ================= AI SETTINGS ================= */

    public function ai_page() { ?>
        <div class="wrap">
            <h1>AI Settings</h1>
            <form method="post" action="<?= admin_url('admin-post.php') ?>">
                <input type="hidden" name="action" value="li_save_ai">
                <?php wp_nonce_field('li_ai'); ?>

                <p>
                    <label>
                        <input type="checkbox" name="enabled" value="1"
                            <?= checked(get_option($this->opt_ai_enabled),'1') ?>>
                        Enable AI API
                    </label>
                </p>

                <p>
                    <input style="width:420px" name="token"
                           value="<?= esc_attr(get_option($this->opt_ai_token)) ?>">
                </p>

                <p><code><?= home_url('/wp-json/landing-inline/v1/create') ?></code></p>

                <button class="button button-primary">Save</button>
            </form>
        </div>
    <?php }

    public function save_ai_settings() {
        check_admin_referer('li_ai');
        update_option($this->opt_ai_enabled, isset($_POST['enabled']) ? '1' : '0');
        update_option($this->opt_ai_token, sanitize_text_field($_POST['token']));
        wp_redirect(wp_get_referer());
        exit;
    }

    /* ================= DESIGN SYSTEM ================= */

    public function design_page() {

        $raw = get_option($this->opt_tpl_store, []);
        $tpls = [];

        foreach ((array)$raw as $id => $tpl) {
            if (!is_array($tpl)) continue;
            $tpls[$id] = [
                'name' => $tpl['name'] ?? ucfirst($id),
                'css'  => $tpl['css']  ?? '',
            ];
        }

        if (empty($tpls)) {
            $tpls['default'] = [
                'name' => 'Default',
                'css'  => 'body{font-family:system-ui;max-width:1200px;margin:0 auto;padding:40px}'
            ];
            update_option($this->opt_tpl_store, $tpls);
        }

        $active = get_option($this->opt_tpl_active, 'default');
        $header_templates = get_option($this->opt_header_store, []);
        $footer_templates = get_option($this->opt_footer_store, []);
        $active_header = get_option($this->opt_header_active, '');
        $active_footer = get_option($this->opt_footer_active, '');
        ?>
        <div class="wrap">
            <h1>Design System</h1>

            <form method="post" action="<?= admin_url('admin-post.php') ?>">
                <input type="hidden" name="action" value="li_save_templates">
                <?php wp_nonce_field('li_tpl'); ?>

                <p>
                    <label>
                        <input type="checkbox" name="enabled" value="1"
                            <?= checked(get_option($this->opt_tpl_enabled),'1') ?>>
                        Enable Templates
                    </label>
                </p>
                <p>
                    <label>
                        <input type="checkbox" name="elements_enabled" value="1"
                            <?= checked(get_option($this->opt_elem_enabled),'1') ?>>
                        Enable Element Templates (Header/Footer)
                    </label>
                </p>

                <h2>Templates</h2>

                <?php foreach ($tpls as $id => $t): ?>
                    <h3><?= esc_html($t['name']) ?> <code>(<?= esc_html($id) ?>)</code></h3>

                    <p>
                        <label>
                            Slug
                            <input name="tpl[<?= esc_attr($id) ?>][slug]"
                                   value="<?= esc_attr($id) ?>">
                        </label>
                    </p>
                    <p>
                        <label>
                            Name
                            <input name="tpl[<?= esc_attr($id) ?>][name]"
                                   value="<?= esc_attr($t['name']) ?>">
                        </label>
                    </p>
                    <p>
                        <label>
                            <input type="checkbox" name="tpl[<?= esc_attr($id) ?>][delete]" value="1">
                            Delete template
                        </label>
                    </p>

                    <textarea name="tpl[<?= esc_attr($id) ?>][css]"
                              style="width:100%;height:140px"><?= esc_textarea($t['css']) ?></textarea>

                    <p>
                        <label>
                            <input type="radio" name="active" value="<?= esc_attr($id) ?>"
                                <?= checked($active, $id) ?>>
                            Active (global)
                        </label>
                    </p>
                    <hr>
                <?php endforeach; ?>

                <h2>Add New Template</h2>

                <p><input name="new_id" placeholder="slug"></p>
                <p><input name="new_name" placeholder="Name"></p>
                <textarea name="new_css" style="width:100%;height:140px"></textarea>

                <h2>Header Templates</h2>

                <?php foreach ((array)$header_templates as $id => $t): ?>
                    <h3><?= esc_html($t['name'] ?? ucfirst($id)) ?> <code>(<?= esc_html($id) ?>)</code></h3>

                    <p>
                        <label>
                            Slug
                            <input name="header_tpl[<?= esc_attr($id) ?>][slug]"
                                   value="<?= esc_attr($id) ?>">
                        </label>
                    </p>
                    <p>
                        <label>
                            Name
                            <input name="header_tpl[<?= esc_attr($id) ?>][name]"
                                   value="<?= esc_attr($t['name'] ?? ucfirst($id)) ?>">
                        </label>
                    </p>
                    <p>
                        <label>
                            <input type="checkbox" name="header_tpl[<?= esc_attr($id) ?>][delete]" value="1">
                            Delete template
                        </label>
                    </p>

                    <textarea name="header_tpl[<?= esc_attr($id) ?>][html]"
                              style="width:100%;height:140px"><?= esc_textarea($t['html'] ?? '') ?></textarea>
                    <textarea name="header_tpl[<?= esc_attr($id) ?>][css]"
                              style="width:100%;height:120px" placeholder="Header CSS"><?= esc_textarea($t['css'] ?? '') ?></textarea>

                    <p>
                        <label>
                            <input type="radio" name="header_active" value="<?= esc_attr($id) ?>"
                                <?= checked($active_header, $id) ?>>
                            Active (global)
                        </label>
                    </p>
                    <hr>
                <?php endforeach; ?>

                <h3>Add New Header Template</h3>
                <p><input name="new_header_id" placeholder="slug"></p>
                <p><input name="new_header_name" placeholder="Name"></p>
                <textarea name="new_header_html" style="width:100%;height:140px"></textarea>
                <textarea name="new_header_css" style="width:100%;height:120px" placeholder="Header CSS"></textarea>

                <h2>Footer Templates</h2>

                <?php foreach ((array)$footer_templates as $id => $t): ?>
                    <h3><?= esc_html($t['name'] ?? ucfirst($id)) ?> <code>(<?= esc_html($id) ?>)</code></h3>

                    <p>
                        <label>
                            Slug
                            <input name="footer_tpl[<?= esc_attr($id) ?>][slug]"
                                   value="<?= esc_attr($id) ?>">
                        </label>
                    </p>
                    <p>
                        <label>
                            Name
                            <input name="footer_tpl[<?= esc_attr($id) ?>][name]"
                                   value="<?= esc_attr($t['name'] ?? ucfirst($id)) ?>">
                        </label>
                    </p>
                    <p>
                        <label>
                            <input type="checkbox" name="footer_tpl[<?= esc_attr($id) ?>][delete]" value="1">
                            Delete template
                        </label>
                    </p>

                    <textarea name="footer_tpl[<?= esc_attr($id) ?>][html]"
                              style="width:100%;height:140px"><?= esc_textarea($t['html'] ?? '') ?></textarea>
                    <textarea name="footer_tpl[<?= esc_attr($id) ?>][css]"
                              style="width:100%;height:120px" placeholder="Footer CSS"><?= esc_textarea($t['css'] ?? '') ?></textarea>

                    <p>
                        <label>
                            <input type="radio" name="footer_active" value="<?= esc_attr($id) ?>"
                                <?= checked($active_footer, $id) ?>>
                            Active (global)
                        </label>
                    </p>
                    <hr>
                <?php endforeach; ?>

                <h3>Add New Footer Template</h3>
                <p><input name="new_footer_id" placeholder="slug"></p>
                <p><input name="new_footer_name" placeholder="Name"></p>
                <textarea name="new_footer_html" style="width:100%;height:140px"></textarea>
                <textarea name="new_footer_css" style="width:100%;height:120px" placeholder="Footer CSS"></textarea>

                <p><button class="button button-primary">Save</button></p>
            </form>
        </div>
    <?php }

    public function save_templates() {

        check_admin_referer('li_tpl');

        update_option($this->opt_tpl_enabled, isset($_POST['enabled']) ? '1' : '0');
        update_option($this->opt_elem_enabled, isset($_POST['elements_enabled']) ? '1' : '0');

        $normalize_template_content = function($value) {
            $value = wp_unslash($value ?? '');
            return str_replace(["\\r\\n", "\\n", "\\r"], "\n", $value);
        };

        $tpls = [];
        $active_tpl = sanitize_key($_POST['active'] ?? '');

        if (!empty($_POST['tpl'])) {
            foreach ($_POST['tpl'] as $id => $t) {
                if (!empty($t['delete'])) {
                    if ($active_tpl === sanitize_key($id)) {
                        $active_tpl = '';
                    }
                    continue;
                }
                $new_id = sanitize_key($t['slug'] ?? $id);
                if ($new_id === '') {
                    $new_id = sanitize_key($id);
                }
                if ($active_tpl === sanitize_key($id) && $new_id !== sanitize_key($id)) {
                    $active_tpl = $new_id;
                }
                $tpls[$new_id] = [
                    'name' => sanitize_text_field($t['name'] ?? ucfirst($new_id)),
                    'css'  => $t['css'] ?? '',
                ];
            }
        }

        if (!empty($_POST['new_id'])) {
            $id = sanitize_key($_POST['new_id']);
            $tpls[$id] = [
                'name' => sanitize_text_field($_POST['new_name'] ?: ucfirst($id)),
                'css'  => $_POST['new_css'] ?? '',
            ];
        }

        update_option($this->opt_tpl_store, $tpls);
        update_option($this->opt_tpl_active, $active_tpl);

        $header_tpls = [];
        $active_header = sanitize_key($_POST['header_active'] ?? '');

        if (!empty($_POST['header_tpl'])) {
            foreach ($_POST['header_tpl'] as $id => $t) {
                if (!empty($t['delete'])) {
                    if ($active_header === sanitize_key($id)) {
                        $active_header = '';
                    }
                    continue;
                }
                $new_id = sanitize_key($t['slug'] ?? $id);
                if ($new_id === '') {
                    $new_id = sanitize_key($id);
                }
                if ($active_header === sanitize_key($id) && $new_id !== sanitize_key($id)) {
                    $active_header = $new_id;
                }
                $header_tpls[$new_id] = [
                    'name' => sanitize_text_field($t['name'] ?? ucfirst($new_id)),
                    'html' => $normalize_template_content($t['html'] ?? ''),
                    'css'  => $normalize_template_content($t['css'] ?? ''),
                ];
            }
        }

        if (!empty($_POST['new_header_id'])) {
            $id = sanitize_key($_POST['new_header_id']);
            $header_tpls[$id] = [
                'name' => sanitize_text_field($_POST['new_header_name'] ?: ucfirst($id)),
                'html' => $normalize_template_content($_POST['new_header_html'] ?? ''),
                'css'  => $normalize_template_content($_POST['new_header_css'] ?? ''),
            ];
        }

        update_option($this->opt_header_store, $header_tpls);
        update_option($this->opt_header_active, $active_header);

        $footer_tpls = [];
        $active_footer = sanitize_key($_POST['footer_active'] ?? '');

        if (!empty($_POST['footer_tpl'])) {
            foreach ($_POST['footer_tpl'] as $id => $t) {
                if (!empty($t['delete'])) {
                    if ($active_footer === sanitize_key($id)) {
                        $active_footer = '';
                    }
                    continue;
                }
                $new_id = sanitize_key($t['slug'] ?? $id);
                if ($new_id === '') {
                    $new_id = sanitize_key($id);
                }
                if ($active_footer === sanitize_key($id) && $new_id !== sanitize_key($id)) {
                    $active_footer = $new_id;
                }
                $footer_tpls[$new_id] = [
                    'name' => sanitize_text_field($t['name'] ?? ucfirst($new_id)),
                    'html' => $normalize_template_content($t['html'] ?? ''),
                    'css'  => $normalize_template_content($t['css'] ?? ''),
                ];
            }
        }

        if (!empty($_POST['new_footer_id'])) {
            $id = sanitize_key($_POST['new_footer_id']);
            $footer_tpls[$id] = [
                'name' => sanitize_text_field($_POST['new_footer_name'] ?: ucfirst($id)),
                'html' => $normalize_template_content($_POST['new_footer_html'] ?? ''),
                'css'  => $normalize_template_content($_POST['new_footer_css'] ?? ''),
            ];
        }

        update_option($this->opt_footer_store, $footer_tpls);
        update_option($this->opt_footer_active, $active_footer);

        wp_redirect(wp_get_referer());
        exit;
    }

    /* ================= AI API ================= */

    public function register_ai_routes() {
        register_rest_route('landing-inline/v1', '/create', [
            'methods' => 'POST',
            'callback' => [$this,'ai_create'],
            'permission_callback' => function($r){
                return get_option($this->opt_ai_enabled)==='1'
                    && hash_equals(
                        get_option($this->opt_ai_token),
                        (string)$r->get_header('X-AI-TOKEN')
                    );
            }
        ]);
    }

    public function revision_meta_keys($keys) {
        $keys[] = '_li_url';
        $keys[] = $this->meta_head;
        $keys[] = $this->meta_body;
        $keys[] = $this->meta_footer;
        $keys[] = $this->meta_css;
        $keys[] = $this->meta_tpl;
        $keys[] = $this->meta_header_tpl;
        $keys[] = $this->meta_footer_tpl;
        return array_values(array_unique($keys));
    }

    public function ai_create($r) {

        $d = $r->get_json_params();
        $url = ltrim($d['url'] ?? '', '/');
        if ($url === '') {
            return new WP_Error('missing_url', 'URL is required', ['status' => 400]);
        }

        $existing = get_posts([
            'post_type'   => $this->post_type,
            'meta_query'  => [[
                'key'   => '_li_url',
                'value' => $url
            ]],
            'post_status' => 'any',
            'numberposts' => 1
        ]);

        $id = 0;
        $post_status = !empty($d['publish']) ? 'publish' : 'draft';

        if ($existing) {
            $id = $existing[0]->ID;
            $post_status = !empty($d['publish']) ? 'publish' : get_post_status($id);
            wp_save_post_revision($id);
            wp_update_post([
                'ID'          => $id,
                'post_title'  => sanitize_text_field($d['title'] ?? $url),
                'post_status' => $post_status,
            ]);
        } else {
            $id = wp_insert_post([
                'post_type'   => $this->post_type,
                'post_title'  => sanitize_text_field($d['title'] ?? $url),
                'post_status' => $post_status,
            ]);
        }

        update_post_meta($id, '_li_url', $url);
        update_post_meta($id, $this->meta_head,   $d['head']   ?? '');
        update_post_meta($id, $this->meta_body,   $d['body']   ?? '');
        update_post_meta($id, $this->meta_footer, $d['footer'] ?? '');
        update_post_meta($id, $this->meta_css,    $d['css']    ?? '');

        if (!empty($d['template'])) {
            update_post_meta($id, $this->meta_tpl, sanitize_key($d['template']));
        }
        if (!empty($d['header_template'])) {
            update_post_meta($id, $this->meta_header_tpl, sanitize_key($d['header_template']));
        }
        if (!empty($d['footer_template'])) {
            update_post_meta($id, $this->meta_footer_tpl, sanitize_key($d['footer_template']));
        }

        return [
            'success'=>true,
            'id'=>$id,
            'url'=>home_url('/'.ltrim($d['url'],'/'))
        ];
    }
}

new Landing_Inline_Any_URL_Safe();
