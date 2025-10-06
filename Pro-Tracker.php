<?php
/**
 * Plugin Name: Pro Tracker
 * Plugin URI: https://github.com/ftepic/woocommerce-order-tracking
 * Description: سیستم رهگیری سفارشات پیشرفته برای ووکامرس با قابلیت ایجاد متدهای مختلف و ورود دسته‌ای اطلاعات از طریق متن.
 * Version: 2.1.0
 * Author: Alireza Fatemi
 * Author URI: https://github.com/deveguru
 * Text Domain: wc-order-tracking
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

class WC_Order_Tracking_System {
 
    private $version = '2.5.0';
    private $plugin_name = 'wc-order-tracking';
    private $upload_dir;
 
    public function __construct() {
        $this->upload_dir = wp_upload_dir()['basedir'] . '/pro-tracker';
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
 
    public function init() {
        $this->create_tables();
        $this->create_upload_directory();
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_shortcode('order_tracking_panel', array($this, 'display_tracking_panel'));
        add_shortcode('tracking_preview', array($this, 'display_tracking_preview'));
        add_action('wp_ajax_save_tracking_method', array($this, 'save_tracking_method'));
        add_action('wp_ajax_delete_tracking_method', array($this, 'delete_tracking_method'));
        add_action('wp_ajax_save_user_tracking_data', array($this, 'save_user_tracking_data'));        
        add_action('wp_ajax_get_user_tracking_data', array($this, 'get_user_tracking_data'));
        add_action('wp_ajax_get_all_users_select2', array($this, 'get_all_users_select2'));
        add_action('wp_ajax_get_user_orders_select2', array($this, 'get_user_orders_select2'));
        add_action('wp_ajax_handle_data_import', array($this, 'handle_data_import'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_filter('woocommerce_account_menu_items', array($this, 'add_tracking_menu_item'));
        add_action('woocommerce_account_order-tracking_endpoint', array($this, 'tracking_endpoint_content'));
        add_action('init', array($this, 'add_tracking_endpoint'));
    }
 
    public function activate() {
        $this->create_tables();
        $this->create_upload_directory();
        $this->get_or_create_method_id('رهگیری پستی', 'code');
        $this->get_or_create_method_id('رهگیری ماهکس', 'code');
        flush_rewrite_rules();
    }
 
    public function deactivate() {
        flush_rewrite_rules();
    }
 
    private function create_upload_directory() {
        if (!file_exists($this->upload_dir)) {
            wp_mkdir_p($this->upload_dir);            
            $htaccess_content = "Options -Indexes\n<Files *.php>\ndeny from all\n</Files>\n";
            file_put_contents($this->upload_dir . '/.htaccess', $htaccess_content);
            file_put_contents($this->upload_dir . '/index.php', '<?php // Silence is golden');
        }
    } 
 
    private function create_tables() {
        global $wpdb;        
        $charset_collate = $wpdb->get_charset_collate();
        $table_methods = $wpdb->prefix . 'wc_tracking_methods';
        $sql_methods = "CREATE TABLE $table_methods ( id int(11) NOT NULL AUTO_INCREMENT, method_name varchar(255) NOT NULL, method_type enum('code','file','image','html','shortcode') NOT NULL, created_at datetime DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id) ) $charset_collate;";
        $table_data = $wpdb->prefix . 'wc_tracking_data';
        $sql_data = "CREATE TABLE $table_data ( id int(11) NOT NULL AUTO_INCREMENT, user_id int(11) NOT NULL, order_id int(11) NOT NULL, method_id int(11) NOT NULL, tracking_value text, file_url varchar(500), file_path varchar(500), created_at datetime DEFAULT CURRENT_TIMESTAMP, updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY unique_tracking (user_id, order_id, method_id), KEY user_id (user_id), KEY order_id (order_id), KEY method_id (method_id) ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_methods);
        dbDelta($sql_data);
    }

    public function register_settings() {
        register_setting('pro_tracker_settings', 'pro_tracker_options', array($this, 'sanitize_settings'));
    }

    public function sanitize_settings($input) {
        $new_input = array();
        if(isset($input['panel_url'])) {
            $new_input['panel_url'] = esc_url_raw(trim($input['panel_url']));
        }
        return $new_input;
    }
 
    public function add_admin_menu() {
        add_menu_page('Pro Tracker', 'Pro Tracker', 'manage_options', 'wc-order-tracking', array($this, 'admin_page'), 'dashicons-location-alt', 56);
    }
 
    public function admin_page() {
        $options = get_option('pro_tracker_options');
        $panel_url = isset($options['panel_url']) ? $options['panel_url'] : '';
        ?>
        <div class="wrap pro-tracker-admin">
            <div class="pro-tracker-header">
                <div class="header-content">
                    <div class="header-icon"><svg width="60" height="60" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></div>
                    <div class="header-text"><h1>Pro Tracker</h1><p>سیستم رهگیری سفارشات پیشرفته</p></div>
                </div>
            </div>
            
            <div class="pro-tracker-tabs">
                <nav class="nav-tab-wrapper">
                    <a href="#methods" class="nav-tab nav-tab-active" data-tab="methods"><svg width="20" height="20" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>مدیریت متدها</a>
                    <a href="#users" class="nav-tab" data-tab="users"><svg width="20" height="20" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>مدیریت کاربران</a>
                    <a href="#import" class="nav-tab" data-tab="import"><svg width="20" height="20" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>ورود اطلاعات</a>
                    <a href="#settings" class="nav-tab" data-tab="settings"><svg width="20" height="20" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V12a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>تنظیمات</a>
                </nav>

                <div id="methods" class="tab-content active"><div class="pro-tracker-card"><div class="card-header"><div class="card-header-content"><svg width="24" height="24" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg><h2>افزودن متد جدید</h2></div></div><div class="card-body"><form id="add-method-form" class="pro-form"><div class="form-grid"><div class="form-group"><label for="method_name">نام متد</label><input type="text" id="method_name" name="method_name" class="form-control" required></div><div class="form-group"><label for="method_type">نوع متد</label><select id="method_type" name="method_type" class="form-control" required><option value="">انتخاب کنید</option><option value="code">کد رهگیری</option><option value="file">فایل</option><option value="image">تصویر</option><option value="html">کد HTML</option><option value="shortcode">شورتکد</option></select></div></div><button type="submit" class="btn btn-primary"><svg width="20" height="20" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg> افزودن متد</button></form></div></div><div class="pro-tracker-card"><div class="card-header"><div class="card-header-content"><svg width="24" height="24" viewBox="0 0 24 24"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg><h2>متدهای موجود</h2></div></div><div class="card-body"><div id="methods-table"><?php $this->display_methods_table(); ?></div></div></div></div>
                <div id="users" class="tab-content"><div class="pro-tracker-card"><div class="card-header"><div class="card-header-content"><svg width="24" height="24" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><h2>مدیریت اطلاعات کاربران</h2></div></div><div class="card-body"><div class="user-selection"><div class="form-group"><label for="user_dropdown">۱. کاربر را انتخاب یا جستجو کنید</label><select id="user_dropdown" class="form-control user-select" style="width:100%"></select></div><div id="user-orders-selection" class="form-group" style="display:none;"><label for="order_dropdown">۲. سفارش کاربر را انتخاب یا جستجو کنید</label><select id="order_dropdown" class="form-control" style="width:100%"></select></div></div><div id="user-tracking-form" class="user-form-container" style="display:none;"><div class="user-info-card"><div id="user-info"></div></div><form id="user-data-form" class="pro-form"><div id="tracking-fields" class="tracking-fields-grid"></div><button type="submit" class="btn btn-success"><svg width="20" height="20" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> ذخیره اطلاعات</button></form></div></div></div></div>
                <div id="import" class="tab-content"><div id="import-status" class="import-status-container" style="display: none;"></div><div class="import-grid"><div class="pro-tracker-card"><div class="card-header"><div class="card-header-content"><svg width="24" height="24" viewBox="0 0 24 24"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg><h2>ورود اطلاعات رهگیری پستی</h2></div></div><div class="card-body"><form class="import-form" data-import-type="post"><div class="form-group"><label for="post_import_text">ورود اطلاعات متنی</label><textarea id="post_import_text" name="import_text" class="form-control" rows="10" placeholder="..."></textarea></div><button type="submit" class="btn btn-primary"><svg width="20" height="20" viewBox="0 0 24 24"><path d="M3 17v3a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-3"/><polyline points="8 12 12 16 16 12"/><line x1="12" y1="2" x2="12" y2="16"/></svg> پردازش</button><div class="spinner-container" style="display:none;"><span class="spinner is-active"></span></div></form></div></div><div class="pro-tracker-card"><div class="card-header"><div class="card-header-content"><svg width="24" height="24" viewBox="0 0 24 24"><path d="M8.5 10.5l7 4-7 4v-8z"/><path d="M12 2a10 10 0 1 0 10 10"/></svg><h2>ورود اطلاعات رهگیری ماهکس</h2></div></div><div class="card-body"><form class="import-form" data-import-type="mahex"><div class="form-group"><label for="mahex_import_text">ورود اطلاعات متنی</label><textarea id="mahex_import_text" name="import_text" class="form-control" rows="10" placeholder="..."></textarea></div><button type="submit" class="btn btn-primary"><svg width="20" height="20" viewBox="0 0 24 24"><path d="M3 17v3a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-3"/><polyline points="8 12 12 16 16 12"/><line x1="12" y1="2" x2="12" y2="16"/></svg> پردازش</button><div class="spinner-container" style="display:none;"><span class="spinner is-active"></span></div></form></div></div></div></div>
                <div id="settings" class="tab-content"><div class="pro-tracker-card"><div class="card-header"><div class="card-header-content"><svg width="24" height="24" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V12a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg><h2>تنظیمات و راهنما</h2></div></div><div class="card-body"><form method="post" action="options.php"><?php settings_fields('pro_tracker_settings'); ?><div class="form-group"><label for="panel_url">آدرس صفحه پنل رهگیری</label><input type="url" id="panel_url" name="pro_tracker_options[panel_url]" value="<?php echo esc_attr($panel_url); ?>" class="form-control ltr" placeholder="https://..."><p class="description">آدرس صفحه‌ای که شورتکد [order_tracking_panel] در آن قرار دارد.</p></div><?php submit_button('ذخیره', 'btn btn-success'); ?></form><hr style="margin:30px 0;border:1px solid #EEEEEE"><div class="settings-grid"><div class="setting-item"><div class="setting-icon"><svg width="32" height="32" viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg></div><h3>شورتکدها</h3><p><strong>پنل کامل:</strong></p><div class="code-block"><code>[order_tracking_panel]</code><button class="copy-btn" data-copy="[order_tracking_panel]">کپی</button></div><p style="margin-top:15px;"><strong>پیش‌نمایش:</strong></p><div class="code-block"><code>[tracking_preview]</code><button class="copy-btn" data-copy="[tracking_preview]">کپی</button></div></div><div class="setting-item"><div class="setting-icon"><svg width="32" height="32" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg></div><h3>راهنمای استفاده</h3><div class="guide-steps"><div class="step"><span class="step-number">1</span><div class="step-content"><h4>ایجاد متدها</h4><p>متدهای مورد نیاز خود را ایجاد کنید.</p></div></div><div class="step"><span class="step-number">2</span><div class="step-content"><h4>تنظیم کاربران</h4><p>برای هر کاربر و سفارش اطلاعات را وارد کنید.</p></div></div><div class="step"><span class="step-number">3</span><div class="step-content"><h4>ورود دسته‌ای</h4><p>از ورود دسته‌ای برای ثبت سریع کدها استفاده کنید.</p></div></div><div class="step"><span class="step-number">4</span><div class="step-content"><h4>نمایش اطلاعات</h4><p>کاربران اطلاعات را در پنل کاربری مشاهده خواهند کرد.</p></div></div></div></div><div class="setting-item"><div class="setting-icon"><svg width="32" height="32" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></div><h3>انواع متدها</h3><div class="method-types"><div class="method-type"><strong>کد رهگیری</strong></div><div class="method-type"><strong>فایل</strong></div><div class="method-type"><strong>تصویر</strong></div><div class="method-type"><strong>کد HTML</strong></div><div class="method-type"><strong>شورتکد</strong></div></div></div></div></div></div></div>
            </div>
        </div>
        
        <style>@import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap');svg{stroke:"currentColor";stroke-width:2;stroke-linecap:"round";stroke-linejoin:"round";fill:"none"}.pro-tracker-admin{font-family:'Vazirmatn',sans-serif !important;background:#EEEEEE;margin:0 -20px 0 -2px;padding:20px;direction:rtl;min-height:100vh}.pro-tracker-admin *{box-sizing:border-box;font-family:'Vazirmatn',sans-serif !important}.pro-tracker-header{background:#000000;color:#FFFFFF;padding:30px;border-radius:15px;margin-bottom:25px;box-shadow:0 10px 30px rgba(0,0,0,0.2)}.header-content{display:flex;align-items:center;gap:20px}.header-text h1{margin:0 0 5px 0;font-size:2.2em;font-weight:700;color:#FFFFFF}.header-text p{margin:0;opacity:0.9;font-size:1em}.nav-tab-wrapper{background:white;border-radius:15px;padding:10px;margin-bottom:25px;box-shadow:0 5px 25px rgba(0,0,0,0.08);border:none;display:flex;gap:8px}.nav-tab{background:transparent !important;border:none !important;padding:15px 25px !important;margin:0 !important;border-radius:12px !important;transition:all 0.3s ease !important;font-weight:500 !important;font-size:14px !important;color:#606060 !important;display:flex !important;align-items:center !important;gap:10px !important;text-decoration:none !important;flex:1;justify-content:center}.nav-tab:hover:not(.nav-tab-active){background:#EEEEEE !important;color:#00CED1 !important}.nav-tab.nav-tab-active{background:#00CED1 !important;color:white !important;box-shadow:0 5px 20px rgba(0,206,209,0.4)}.tab-content{display:none;animation:fadeIn 0.3s ease-in-out}.tab-content.active{display:block}@keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}.pro-tracker-card{background:white;border-radius:15px;box-shadow:0 5px 25px rgba(0,0,0,0.08);margin-bottom:25px;overflow:hidden;border:1px solid #60606040}.card-header{background:#00CED1;color:white;padding:25px}.card-header h2{color:#FFFFFF;margin:0;font-weight:600;font-size:1.3em}.card-header-content{display:flex;align-items:center;gap:12px}.card-body{padding:30px}.form-control{width:100%;padding:12px 16px;border:1px solid #606060;border-radius:10px;font-size:14px;background:#EEEEEE}.form-control:focus{outline:none;border-color:#00CED1;background:white;box-shadow:0 0 0 3px rgba(0,206,209,0.2)}.btn{padding:12px 24px;border:none;border-radius:10px;font-weight:600;display:inline-flex;align-items:center;gap:8px;cursor:pointer;transition:all 0.3s ease}.btn-primary{background:#000000;color:#EEEEEE}.btn-secondary{background:#606060;color:white}.btn-success{background:#00CED1;color:white}.btn-danger{background:#d9534f;color:white}.method-card{background:#EEEEEE;border:1px solid #606060;border-radius:15px;padding:20px}.method-name{font-weight:600;color:#000000}.method-type{background:#00CED1;color:white;padding:4px 12px;border-radius:20px;font-size:12px}.user-selection{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:25px}.user-info-card{background:#000000;color:#EEEEEE;padding:20px;border-radius:15px;margin-bottom:20px}.user-info-card *{color:#FFFFFF !important}.tracking-field{background:#EEEEEE;border:1px solid #606060;border-radius:15px;padding:20px}.tracking-field h5{color:#000000}.setting-item{background:#EEEEEE;padding:25px;border-radius:15px;border:1px solid #606060;text-align:center}.setting-icon{background:#00CED1;color:white;width:60px;height:60px;border-radius:15px;display:flex;align-items:center;justify-content:center;margin:0 auto 20px}.setting-item h3{color:#000000}.code-block{background:#000000;color:#EEEEEE;padding:15px;border-radius:10px;display:flex;justify-content:space-between;align-items:center;margin-top:15px}.copy-btn{background:#606060;color:white}.step-number{background:#00CED1;color:white}.method-types .method-type{color:#000000;padding:12px;background:white;border-radius:8px;border-right:4px solid #00CED1}.method-types .method-type strong{color:#000000}.import-grid{display:grid;grid-template-columns:1fr 1fr;gap:25px}.ltr{direction:ltr;text-align:left}.select2-container .select2-selection--single{height:45px;border:1px solid #606060 !important;border-radius:10px !important;background:#EEEEEE !important}.select2-container--default .select2-selection--single .select2-selection__rendered{line-height:43px !important;padding-right:12px !important}.select2-container--default .select2-selection--single .select2-selection__arrow{height:43px !important}.select2-dropdown{border:1px solid #00CED1 !important;border-radius:10px !important;background:#EEEEEE !important}.select2-container--default .select2-search--dropdown .select2-search__field{border:1px solid #606060 !important;border-radius:8px !important;background:#fff !important}.select2-container--default .select2-results__option--highlighted[aria-selected]{background-color:#00CED1 !important}@media (max-width:992px){.import-grid{grid-template-columns:1fr}}@media (max-width:768px){.user-selection,.settings-grid,.methods-grid{grid-template-columns:1fr}}</style>
 
        <script>
        jQuery(document).ready(function($){
            function initSelect2(){
                $('#user_dropdown').select2({ ajax: { url: ajaxurl, dataType: 'json', delay: 250, data: (params) => ({ action: 'get_all_users_select2', q: params.term }), processResults: (data) => ({ results: data.data.items }), cache: true }, placeholder: 'کاربر را انتخاب یا جستجو کنید...', allowClear: true, minimumInputLength: 0 });
                $('#order_dropdown').select2({ ajax: { url: ajaxurl, dataType: 'json', delay: 250, data: (params) => ({ action: 'get_user_orders_select2', q: params.term, user_id: $('#user_dropdown').val() }), processResults: (data) => ({ results: data.data.items }), cache: true }, placeholder: 'سفارش را انتخاب یا جستجو کنید...', allowClear: true, disabled: true, minimumInputLength: 0 });
            }
            initSelect2();
            $('.nav-tab').on('click',function(e){ e.preventDefault(); if($(this).hasClass('nav-tab-active')) return; var target = $(this).attr('href'); $('.nav-tab').removeClass('nav-tab-active'); $(this).addClass('nav-tab-active'); $('.tab-content').removeClass('active'); $(target).addClass('active'); });
            $('#add-method-form').on('submit',function(e){e.preventDefault();$.post(ajaxurl,{action:'save_tracking_method',method_name:$('#method_name').val(),method_type:$('#method_type').val(),nonce:'<?php echo wp_create_nonce('tracking_nonce'); ?>'},function(r){if(r.success)location.reload();else alert('خطا')})});
            $('.import-form').on('submit',function(e){e.preventDefault();var f=$(this),b=f.find('button'),s=f.find('.spinner-container'),d=$('#import-status'),fd=new FormData(this);fd.append('action','handle_data_import');fd.append('nonce','<?php echo wp_create_nonce('import_nonce'); ?>');fd.append('import_type',f.data('import-type'));b.prop('disabled',true);s.show();d.hide();$.ajax({url:ajaxurl,type:'POST',data:fd,processData:false,contentType:false,success:function(r){if(r.success)d.removeClass('error').addClass('success').html(r.data.message).show();else d.removeClass('success').addClass('error').html(r.data.message).show()},error:function(){d.removeClass('success').addClass('error').html('خطای سرور.').show()},complete:function(){b.prop('disabled',false);s.hide();f.trigger('reset')}})});
            $('#user_dropdown').on('change',function(){var userId=$(this).val();$('#order_dropdown').val(null).trigger('change');if(userId){$('#order_dropdown').prop('disabled',false);$('#user-orders-selection').show()}else{$('#order_dropdown').prop('disabled',true);$('#user-orders-selection').hide()}$('#user-tracking-form').hide()});
            $('#order_dropdown').on('change',function(){var orderId=$(this).val();var userId=$('#user_dropdown').val();if(orderId&&userId)loadUserTrackingForm(userId,orderId);else $('#user-tracking-form').hide()});
            $('#user-data-form').on('submit',function(e){e.preventDefault();var fd=$(this).serialize();fd+='&action=save_user_tracking_data&nonce=<?php echo wp_create_nonce('tracking_nonce'); ?>';$.post(ajaxurl,fd,function(r){if(r.success)alert('اطلاعات ذخیره شد');else alert('خطا')})});
            $(document).on('click','.delete-method',function(){if(confirm('آیا از حذف اطمینان دارید؟')){var id=$(this).data('id');$.post(ajaxurl,{action:'delete_tracking_method',method_id:id,nonce:'<?php echo wp_create_nonce('tracking_nonce'); ?>'},function(r){if(r.success)location.reload()})}});
            $(document).on('click','.copy-btn',function(){var t=$(this).data('copy');navigator.clipboard.writeText(t).then(()=>alert('کپی شد!'))});
            var mediaUploader;$(document).on('click','.upload-file',function(e){e.preventDefault();var b=$(this),i=b.siblings('input');if(mediaUploader){mediaUploader.open();return}mediaUploader=wp.media({title:'انتخاب فایل',button:{text:'انتخاب'},multiple:false});mediaUploader.on('select',()=>i.val(mediaUploader.state().get('selection').first().toJSON().url));mediaUploader.open()});
            function loadUserTrackingForm(userId,orderId){$.post(ajaxurl,{action:'get_user_tracking_data',user_id:userId,order_id:orderId,nonce:'<?php echo wp_create_nonce('tracking_nonce'); ?>'},function(r){if(r.success){$('#user-info').html(r.data.user_info);$('#tracking-fields').html(r.data.fields_html);$('#user-tracking-form').show()}})}
        });
        </script>
        <?php
    }     
    
    private function display_methods_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'wc_tracking_methods';
        $methods = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
        if($methods) {
            echo '<div class="methods-grid">';
            foreach($methods as $method) {
                $type_labels = ['code'=>'کد رهگیری','file'=>'فایل','image'=>'تصویر','html'=>'HTML','shortcode'=>'شورتکد'];
                echo '<div class="method-card"><div class="method-header"><div class="method-name">' . esc_html($method->method_name) . '</div><div class="method-type">' . ($type_labels[$method->method_type] ?? $method->method_type) . '</div></div><div class="method-date">ایجاد: ' . date_i18n('Y/m/d H:i', strtotime($method->created_at)) . '</div><button class="btn btn-danger btn-sm delete-method" data-id="' . $method->id . '">حذف</button></div>';
            }
            echo '</div>';
        } else { echo '<p>هیچ متدی تعریف نشده است.</p>'; }    
    }

    public function get_all_users_select2() {
        if(!current_user_can('manage_options')) { wp_send_json_error(); }
        $search = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
        $args = [ 'search' => '*' . esc_attr($search) . '*', 'search_columns' => ['user_login', 'user_email', 'display_name'], 'number' => 20 ];
        $user_query = new WP_User_Query($args);
        $users = $user_query->get_results();
        $results = [];
        foreach($users as $user){ $results[] = [ 'id' => $user->ID, 'text' => $user->display_name . ' (' . $user->user_email . ')' ]; }
        wp_send_json_success(['items' => $results]);
    }

    public function get_user_orders_select2() {
        if(!current_user_can('manage_options')) { wp_send_json_error(); }
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        if(!$user_id) { wp_send_json_success(['items' => []]); }
        $search = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
        $args = ['customer_id' => $user_id, 'limit' => 20, 'orderby' => 'date', 'order' => 'DESC'];
        if (!empty($search)) { $args['s'] = $search; }
        $orders = wc_get_orders($args);
        $results = [];
        foreach($orders as $order) {
            $products_text = implode('، ', array_map(fn($item) => $item->get_name(), $order->get_items()));
            $results[] = [ 'id' => $order->get_id(), 'text' => '#' . $order->get_order_number() . ' - ' . $products_text . ' (' . wc_format_datetime($order->get_date_created(), 'Y/m/d') . ')' ];
        }
        wp_send_json_success(['items' => $results]);
    }
    
    public function save_tracking_method() {
        check_ajax_referer('tracking_nonce', 'nonce'); if(!current_user_can('manage_options')) wp_die();
        global $wpdb; $wpdb->insert($wpdb->prefix . 'wc_tracking_methods', ['method_name' => sanitize_text_field($_POST['method_name']), 'method_type' => sanitize_text_field($_POST['method_type'])]) ? wp_send_json_success() : wp_send_json_error();
    }

    public function delete_tracking_method() {
        check_ajax_referer('tracking_nonce', 'nonce'); if(!current_user_can('manage_options')) wp_die();
        global $wpdb; $wpdb->delete($wpdb->prefix . 'wc_tracking_methods', ['id' => intval($_POST['method_id'])]) ? wp_send_json_success() : wp_send_json_error();
    }

    public function get_user_tracking_data() {
        check_ajax_referer('tracking_nonce', 'nonce'); if(!current_user_can('manage_options')) wp_die();
        $user_id = intval($_POST['user_id']); $order_id = intval($_POST['order_id']);
        $user = get_user_by('ID', $user_id); $order = wc_get_order($order_id);
        if(!$user || !$order) wp_send_json_error();
        global $wpdb;
        $methods = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_tracking_methods ORDER BY method_name");
        $products = implode('، ', array_map(fn($item) => $item->get_name(), $order->get_items()));
        $user_info = '<div>' . get_avatar($user->ID, 60) . '<div><h3>' . $user->display_name . ' (' . $user->user_email . ')</h3><p><strong>سفارش #' . $order->get_order_number() . ':</strong> ' . $products . '</p></div></div>';
        $fields_html = '';
        foreach($methods as $method) {
            $d = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wc_tracking_data WHERE user_id = %d AND order_id = %d AND method_id = %d", $user_id, $order_id, $method->id));
            $fields_html .= '<div class="tracking-field"><h5>' . esc_html($method->method_name) . '</h5>';
            switch($method->method_type) {
                case 'code': $fields_html .= '<input type="text" name="tracking_data[' . $method->id . ']" value="' . esc_attr($d->tracking_value ?? '') . '" class="form-control">'; break;
                case 'html': $fields_html .= '<textarea name="tracking_data[' . $method->id . ']" class="form-control" rows="5">' . esc_textarea($d->tracking_value ?? '') . '</textarea>'; break;
                case 'shortcode': $fields_html .= '<input type="text" name="tracking_data[' . $method->id . ']" value="' . esc_attr($d->tracking_value ?? '') . '" class="form-control">'; break;
                case 'file': case 'image':
                    $url = $d->file_url ?? '';
                    $fields_html .= '<div><input type="url" name="tracking_files[' . $method->id . ']" value="' . esc_url($url) . '" class="form-control"><button type="button" class="btn btn-secondary upload-file">انتخاب</button></div>';
                    if($url) $fields_html .= '<div><a href="' . esc_url($url) . '" target="_blank">مشاهده فایل</a></div>';
                    break;
            }
            $fields_html .= '</div>';
        }
        wp_send_json_success(['user_info' => $user_info, 'fields_html' => $fields_html]);
    }

    public function save_user_tracking_data() {
        check_ajax_referer('tracking_nonce', 'nonce'); if(!current_user_can('manage_options')) wp_die();
        $user_id = intval($_POST['user_id']); $order_id = intval($_POST['order_id']);
        $data = $_POST['tracking_data'] ?? []; $files = $_POST['tracking_files'] ?? [];
        global $wpdb; $table = $wpdb->prefix . 'wc_tracking_data';
        foreach($data as $id => $val) { $wpdb->query($wpdb->prepare("INSERT INTO $table (user_id,order_id,method_id,tracking_value) VALUES (%d,%d,%d,%s) ON DUPLICATE KEY UPDATE tracking_value=%s", $user_id, $order_id, $id, wp_kses_post($val), wp_kses_post($val))); }
        foreach($files as $id => $url) { if(!empty($url)) { $wpdb->query($wpdb->prepare("INSERT INTO $table (user_id,order_id,method_id,file_url) VALUES (%d,%d,%d,%s) ON DUPLICATE KEY UPDATE file_url=%s", $user_id, $order_id, $id, esc_url_raw($url), esc_url_raw($url))); } }
        wp_send_json_success();
    }
    
    public function handle_data_import() {
        check_ajax_referer('import_nonce', 'nonce'); if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'دسترسی غیرمجاز.']);
        $type = sanitize_key($_POST['import_type']); $text = stripslashes($_POST['import_text'] ?? '');
        if (empty($text)) wp_send_json_error(['message' => 'اطلاعاتی یافت نشد.']);
        $lines = explode("\n", trim($text)); $rows = [];
        foreach ($lines as $line) { $rows[] = str_getcsv(trim($line), "\t"); }
        if (empty($rows)) wp_send_json_error(['message' => 'فرمت نامعتبر است.']);
        wp_send_json_success(['message' => $this->process_imported_data($rows, $type)]);
    }

    private function process_imported_data($rows, $type) {        
        $header = array_map('trim', array_shift($rows)); $code_idx = -1; $name_idx = -1;
        $code_aliases = ($type === 'post') ? ['بارکد', 'کد رهگیری'] : ['شماره مرسوله'];        
        $name_aliases = ($type === 'post') ? ['نام گ', 'نام گیرنده'] : ['نام گیرنده'];
        foreach ($header as $i => $col) {
            foreach($code_aliases as $a) if (mb_strpos($col, $a) !== false) { $code_idx = $i; break; }
            foreach($name_aliases as $a) if (mb_strpos($col, $a) !== false) { $name_idx = $i; break; }
        }
        if ($code_idx === -1 || $name_idx === -1) return 'خطا: ستون‌های مورد نیاز یافت نشد.';
        $method_id = $this->get_or_create_method_id(($type === 'post') ? 'رهگیری پستی' : 'رهگیری ماهکس', 'code');
        if (!$method_id) return 'خطا: ایجاد متد رهگیری ممکن نیست.';
        $proc = 0; $match = 0; $unmatch = [];
        global $wpdb; $table = $wpdb->prefix . 'wc_tracking_data';
        foreach ($rows as $row) {
            if (count($row) <= max($code_idx, $name_idx)) continue;
            $code = trim($row[$code_idx]); $name = trim($row[$name_idx]);
            if (empty($code) || empty($name)) continue;
            $proc++; $user = $this->find_user_by_full_name($name);            
            if ($user) {
                $orders = wc_get_orders(['customer_id' => $user->ID, 'limit' => 1, 'orderby' => 'date', 'order' => 'DESC']);
                if (!empty($orders)) {
                    $order_id = reset($orders)->get_id();
                    $wpdb->query($wpdb->prepare("INSERT INTO $table (user_id,order_id,method_id,tracking_value) VALUES (%d,%d,%d,%s) ON DUPLICATE KEY UPDATE tracking_value=%s", $user->ID, $order_id, $method_id, $code, $code));
                    $match++;
                } else { $unmatch[] = $name . ' (بدون سفارش)'; }
            } else { $unmatch[] = $name; }
        }
        $msg = "پردازش: $proc. موفق: $match.";
        if (!empty($unmatch)) { $msg .= '<br><br><b>ناموفق:</b><br>' . implode('<br>', array_unique($unmatch)); }
        return $msg;
    }

    private function find_user_by_full_name($name) {
        $users = get_users(['search' => $name, 'search_columns' => ['display_name', 'user_login', 'user_email'], 'number' => 1]);
        if (!empty($users)) return $users[0];
        $parts = explode(' ', $name, 2); $first = $parts[0]; $last = $parts[1] ?? '';
        $q = new WP_User_Query([ 'number' => 1, 'meta_query' => ['relation' => 'OR', ['key' => 'first_name', 'value' => $first], ['key' => 'last_name', 'value' => $last], ['key' => 'billing_first_name', 'value' => $first], ['key' => 'billing_last_name', 'value' => $last]]]);
        return !empty($q->get_results()) ? $q->get_results()[0] : null;
    }

    private function get_or_create_method_id($name, $type) {
        global $wpdb; $table = $wpdb->prefix . 'wc_tracking_methods';
        $method = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE method_name = %s", $name));
        if ($method) return $method->id;
        $wpdb->insert($table, ['method_name' => $name, 'method_type' => $type]); return $wpdb->insert_id;
    }

    public function display_tracking_preview($atts) {
        if (!is_user_logged_in()) return '';
        global $wpdb;
        $latest = $wpdb->get_row($wpdb->prepare("SELECT DISTINCT order_id FROM {$wpdb->prefix}wc_tracking_data WHERE user_id = %d ORDER BY order_id DESC LIMIT 1", get_current_user_id()));
        if (!$latest || !($order = wc_get_order($latest->order_id))) return '';
        $opts = get_option('pro_tracker_options');
        $url = !empty($opts['panel_url']) ? esc_url($opts['panel_url']) : wc_get_account_endpoint_url('order-tracking');
        ob_start();
        ?><div class="pro-tracker-preview"><div class="preview-header"><div>📦 <strong>سفارش #<?php echo $order->get_order_number(); ?></strong> <span>وضعیت: <?php echo wc_get_order_status_name($order->get_status()); ?></span></div><div>🔔 <strong>اطلاعات رهگیری جدید</strong> <span>برای مشاهده جزئیات کلیک کنید</span></div><span class="preview-arrow">▼</span></div><div class="preview-content"><?php echo $this->render_tracking_details_for_order($order); ?><div class="preview-footer"><a href="<?php echo $url; ?>" class="preview-full-panel-btn">مشاهده پنل رهگیری</a></div></div></div>
        <style>.pro-tracker-preview{font-family:"Vazirmatn",sans-serif;direction:rtl;margin:20px 0;border:1px solid #606060;border-radius:8px;overflow:hidden;background:#EEE}.preview-header{display:flex;justify-content:space-between;align-items:center;padding:15px;cursor:pointer;background:#fff}.preview-header>div{display:flex;align-items:center;gap:10px}.preview-header strong{color:#000}.preview-header span{color:#606060}.preview-arrow{transition:transform .3s}.open .preview-arrow{transform:rotate(180deg)}.preview-content{display:none;padding:15px;border-top:1px solid #606060}.preview-footer{text-align:center;margin-top:15px}.preview-full-panel-btn{display:inline-block;padding:10px 20px;background:#00CED1;color:white;text-decoration:none;border-radius:5px}</style>
        <script>jQuery($=>{$('.pro-tracker-preview .preview-header').on('click',function(){const p=$(this).closest('.pro-tracker-preview');p.toggleClass('open').find('.preview-content').slideToggle(300)})})</script><?php
        return ob_get_clean();
    }
    
    private function render_tracking_details_for_order($order) {
        global $wpdb;
        $data = $wpdb->get_results($wpdb->prepare("SELECT td.*,tm.method_name,tm.method_type FROM {$wpdb->prefix}wc_tracking_data td JOIN {$wpdb->prefix}wc_tracking_methods tm ON td.method_id=tm.id WHERE td.user_id=%d AND td.order_id=%d", $order->get_customer_id(), $order->get_id()));
        if(!$data) return '<p>اطلاعاتی یافت نشد.</p>';
        $output = '<div style="display:grid;gap:15px;">';
        foreach($data as $t) {
            if(empty($t->tracking_value) && empty($t->file_url)) continue;
            $output .= '<div style="background:#fff;border-radius:8px;padding:15px;border:1px solid #606060;"><h4>' . esc_html($t->method_name) . '</h4><div>';
            if (in_array($t->method_name, ['رهگیری پستی', 'رهگیری ماهکس'])) {
                $url = ($t->method_name === 'رهگیری پستی') ? 'https://tracking.post.ir/' : 'https://mahex.com/tracking/';
                $output .= '<div style="display:flex;justify-content:space-between;align-items:center;"><span>' . esc_html($t->tracking_value) . '</span><a href="' . esc_url($url) . '" target="_blank" style="color:#00CED1">رهگیری</a></div>';
            } else {
                switch($t->method_type) {
                    case 'code': $output .= esc_html($t->tracking_value); break;
                    case 'html': $output .= wp_kses_post($t->tracking_value); break;
                    case 'shortcode': $output .= do_shortcode($t->tracking_value); break;
                    case 'file': $output .= '<a href="' . esc_url($t->file_url) . '" target="_blank" style="color:#00CED1;">دانلود فایل</a>'; break;
                    case 'image': $output .= '<img src="' . esc_url($t->file_url) . '" style="max-width:100px;border-radius:4px;">'; break;
                }
            }
            $output .= '</div></div>';
        }
        return $output . '</div>';
    }

    public function display_tracking_panel($atts) {
        if(!is_user_logged_in()) { return '<div class="wc-tracking-login-notice"><h3>ورود به حساب کاربری</h3><p>برای مشاهده اطلاعات رهگیری، لطفاً وارد شوید.</p><a href="' . wp_login_url(get_permalink()) . '" class="login-btn">ورود</a></div>'; }
        global $wpdb;
        $orders_with_tracking = $wpdb->get_results($wpdb->prepare("SELECT DISTINCT order_id FROM {$wpdb->prefix}wc_tracking_data WHERE user_id = %d ORDER BY order_id DESC", get_current_user_id()));
        if(!$orders_with_tracking) { return '<div class="wc-tracking-empty"><h3>اطلاعاتی یافت نشد</h3><p>در حال حاضر هیچ اطلاعات رهگیری‌ای برای شما ثبت نشده است.</p></div>'; }
        $output = '<div class="wc-tracking-panel"><div class="tracking-header"><h2>رهگیری سفارشات</h2></div>';
        foreach($orders_with_tracking as $order_data) {
            $order = wc_get_order($order_data->order_id);
            if(!$order) continue;            
            $tracking_data = $wpdb->get_results($wpdb->prepare("SELECT td.*, tm.method_name, tm.method_type FROM {$wpdb->prefix}wc_tracking_data td JOIN {$wpdb->prefix}wc_tracking_methods tm ON td.method_id = tm.id WHERE td.user_id = %d AND td.order_id = %d", get_current_user_id(), $order->get_id()));
            if(!$tracking_data) continue;
            $products_text = implode('، ', array_map(fn($i) => $i->get_name(), $order->get_items()));
            $output .= '<div class="order-tracking-card"><div class="order-header"><div><h3>سفارش #' . $order->get_order_number() . '</h3><div class="order-products">' . $products_text . '</div></div><div class="order-status"><span class="status-badge">' . wc_get_order_status_name($order->get_status()) . '</span></div></div><div class="tracking-methods">';
            foreach($tracking_data as $t) {
                if(empty($t->tracking_value) && empty($t->file_url)) continue;
                $output .= '<div class="tracking-method"><h4>' . esc_html($t->method_name) . '</h4><div>';
                if (in_array($t->method_name, ['رهگیری پستی', 'رهگیری ماهکس'])) {
                    $url = ($t->method_name === 'رهگیری پستی') ? 'https://tracking.post.ir/' : 'https://mahex.com/tracking/';
                    $output .= '<div class="tracking-code-special"><span class="code-value">' . esc_html($t->tracking_value) . '</span><div><button class="copy-code-btn" data-copy="' . esc_attr($t->tracking_value) . '">کپی</button><a href="' . esc_url($url) . '" target="_blank" class="track-shipment-btn">رهگیری</a></div></div>';
                } else {
                    switch($t->method_type) {
                        case 'code': $output .= '<div class="tracking-code">' . esc_html($t->tracking_value) . '</div>'; break;
                        case 'html': $output .= wp_kses_post($t->tracking_value); break;
                        case 'shortcode': $output .= do_shortcode($t->tracking_value); break;
                        case 'file': $output .= '<a href="' . esc_url($t->file_url) . '" target="_blank" class="file-btn">دانلود فایل</a>'; break;
                        case 'image': $output .= '<img src="' . esc_url($t->file_url) . '" class="tracking-img">'; break;
                    }
                }
                $output .= '</div></div>';
            }
            $output .= '</div></div>';
        }
        $output .= '</div>';
        $output .= '<style>@import url("https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600&display=swap");.wc-tracking-panel,.wc-tracking-login-notice,.wc-tracking-empty{font-family:"Vazirmatn",sans-serif;max-width:900px;margin:20px auto;padding:0 15px;direction:rtl}.tracking-header{text-align:center;margin-bottom:30px;padding:25px;background:#000;color:#EEE;border-radius:15px}.order-tracking-card{background:#EEE;border-radius:15px;margin-bottom:25px;border:1px solid #606060}.order-header{background:#00CED1;color:white;padding:20px;display:flex;justify-content:space-between;align-items:center;border-radius:15px 15px 0 0}.status-badge{padding:6px 12px;border-radius:20px;background:rgba(0,0,0,0.2)}.tracking-methods{padding:20px;display:grid;gap:15px}.tracking-method{background:white;border-radius:10px;padding:15px;border:1px solid #606060}.tracking-code-special{display:flex;justify-content:space-between;align-items:center;gap:15px}.code-value{font-weight:600;color:#000}.copy-code-btn,.track-shipment-btn,.file-btn{display:inline-flex;padding:8px 14px;border-radius:20px;text-decoration:none;border:none;cursor:pointer}.copy-code-btn{background:#606060;color:white}.track-shipment-btn,.file-btn{background:#00CED1;color:white}.tracking-img{max-width:200px;height:auto;border-radius:8px}.wc-tracking-login-notice,.wc-tracking-empty{text-align:center;padding:40px;background:#EEE;border-radius:15px;border:1px solid #606060}.login-btn{display:inline-block;background:#00CED1;color:white;padding:12px 25px;border-radius:25px;text-decoration:none}</style>';
        $output .= '<script>jQuery(document).ready(function($){$(document).on("click",".copy-code-btn",function(e){e.preventDefault();const t=$(this).data("copy");navigator.clipboard.writeText(t).then(()=>alert("کد کپی شد: "+t))})});</script>';
        return $output;
    }

    public function add_tracking_endpoint() { add_rewrite_endpoint('order-tracking', EP_ROOT | EP_PAGES); }
    public function add_tracking_menu_item($items) { $items['order-tracking'] = 'رهگیری سفارشات'; return $items; }
    public function tracking_endpoint_content() { echo $this->display_tracking_panel(array()); }
    public function enqueue_frontend_scripts() { wp_enqueue_script('jquery'); }

    public function enqueue_admin_scripts($hook) {
        if(strpos($hook, 'wc-order-tracking') !== false) {
            wp_enqueue_script('jquery'); wp_enqueue_media();
            wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
            wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], null, true);
        }
    }
}

new WC_Order_Tracking_System();
