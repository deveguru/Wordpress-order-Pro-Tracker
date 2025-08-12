<?php
/**
 * Plugin Name: Pro Tracker
 * Plugin URI: https://github.com/ftepic/woocommerce-order-tracking
 * Description: سیستم رهگیری سفارشات پیشرفته برای ووکامرس با قابلیت ایجاد متدهای مختلف نمایش اطلاعات
 * Version: 1.0.0
 * Author: Alireza Fatemi
 * Author URI: https://github.com/ftepic
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
 
 private $version = '1.0.0';
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
 add_shortcode('order_tracking_panel', array($this, 'display_tracking_panel'));
 add_action('wp_ajax_save_tracking_method', array($this, 'save_tracking_method'));
 add_action('wp_ajax_delete_tracking_method', array($this, 'delete_tracking_method'));
 add_action('wp_ajax_save_user_tracking_data', array($this, 'save_user_tracking_data'));
 add_action('wp_ajax_get_user_tracking_data', array($this, 'get_user_tracking_data'));
 add_action('wp_ajax_get_all_users', array($this, 'get_all_users'));
 add_action('wp_ajax_upload_tracking_file', array($this, 'upload_tracking_file'));
 add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
 add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
 add_filter('woocommerce_account_menu_items', array($this, 'add_tracking_menu_item'));
 add_action('woocommerce_account_order-tracking_endpoint', array($this, 'tracking_endpoint_content'));
 add_action('init', array($this, 'add_tracking_endpoint'));
 }
 
 public function activate() {
 $this->create_tables();
 $this->create_upload_directory();
 flush_rewrite_rules();
 }
 
 public function deactivate() {
 flush_rewrite_rules();
 }
 
 private function create_upload_directory() {
 if (!file_exists($this->upload_dir)) {
 wp_mkdir_p($this->upload_dir);
 
 $htaccess_content = "Options -Indexes\n";
 $htaccess_content .= "<Files *.php>\n";
 $htaccess_content .= "deny from all\n";
 $htaccess_content .= "</Files>\n";
 
 file_put_contents($this->upload_dir . '/.htaccess', $htaccess_content);
 file_put_contents($this->upload_dir . '/index.php', '<?php // Silence is golden');
 }
 }
 
 private function create_user_directory($user_id) {
 $user_dir = $this->upload_dir . '/user-' . $user_id;
 if (!file_exists($user_dir)) {
 wp_mkdir_p($user_dir);
 file_put_contents($user_dir . '/index.php', '<?php // Silence is golden');
 }
 return $user_dir;
 }
 
 private function create_tables() {
 global $wpdb;
 
 $charset_collate = $wpdb->get_charset_collate();
 
 $table_methods = $wpdb->prefix . 'wc_tracking_methods';
 $sql_methods = "CREATE TABLE $table_methods (
 id int(11) NOT NULL AUTO_INCREMENT,
 method_name varchar(255) NOT NULL,
 method_type enum('code','file','image','html','shortcode') NOT NULL,
 created_at datetime DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (id)
 ) $charset_collate;";
 
 $table_data = $wpdb->prefix . 'wc_tracking_data';
 $sql_data = "CREATE TABLE $table_data (
 id int(11) NOT NULL AUTO_INCREMENT,
 user_id int(11) NOT NULL,
 method_id int(11) NOT NULL,
 tracking_value text,
 file_url varchar(500),
 file_path varchar(500),
 created_at datetime DEFAULT CURRENT_TIMESTAMP,
 updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (id),
 KEY user_id (user_id),
 KEY method_id (method_id)
 ) $charset_collate;";
 
 require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
 dbDelta($sql_methods);
 dbDelta($sql_data);
 }
 
 public function add_admin_menu() {
 add_menu_page(
 'Pro Tracker',
 'Pro Tracker',
 'manage_options',
 'wc-order-tracking',
 array($this, 'admin_page'),
 'dashicons-search',
 56
 );
 }
 
 public function admin_page() {
 ?>
 <div class="wrap pro-tracker-admin">
 <div class="pro-tracker-header">
 <div class="header-content">
 <div class="header-icon">
 <svg width="60" height="60" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
 <path d="M21 16V8C21 6.89543 20.1046 6 19 6H5C3.89543 6 3 6.89543 3 8V16C3 17.1046 3.89543 18 5 18H19C20.1046 18 21 17.1046 21 16Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 <path d="M3 10H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 <path d="M7 14H17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 </svg>
 </div>
 <div class="header-text">
 <h1>Pro Tracker</h1>
 <p>سیستم رهگیری سفارشات پیشرفته</p>
 </div>
 </div>
 </div>
 
 <div class="pro-tracker-tabs">
 <nav class="nav-tab-wrapper">
 <a href="#methods" class="nav-tab nav-tab-active" data-tab="methods">
 <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
 <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 </svg>
 مدیریت متدها
 </a>
 <a href="#users" class="nav-tab" data-tab="users">
 <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
 <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 </svg>
 مدیریت کاربران
 </a>
 <a href="#settings" class="nav-tab" data-tab="settings">
 <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
 <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 <path d="M19.4 15C19.2669 15.3016 19.2272 15.6362 19.286 15.9606C19.3448 16.285 19.4995 16.5843 19.73 16.82L19.79 16.88C19.976 17.0657 20.1235 17.2863 20.2241 17.5291C20.3248 17.7719 20.3766 18.0322 20.3766 18.295C20.3766 18.5578 20.3248 18.8181 20.2241 19.0609C20.1235 19.3037 19.976 19.5243 19.79 19.71C19.6043 19.896 19.3837 20.0435 19.1409 20.1441C18.8981 20.2448 18.6378 20.2966 18.375 20.2966C18.1122 20.2966 17.8519 20.2448 17.6091 20.1441C17.3663 20.0435 17.1457 19.896 16.96 19.71L16.9 19.65C16.6643 19.4195 16.365 19.2648 16.0406 19.206C15.7162 19.1472 15.3816 19.1869 15.08 19.32C14.7842 19.4468 14.532 19.6572 14.3543 19.9255C14.1766 20.1938 14.0813 20.5082 14.08 20.83V21C14.08 21.5304 13.8693 22.0391 13.4942 22.4142C13.1191 22.7893 12.6104 23 12.08 23C11.5496 23 11.0409 22.7893 10.6658 22.4142C10.2907 22.0391 10.08 21.5304 10.08 21V20.91C10.0723 20.579 9.96512 20.2569 9.77251 19.9859C9.5799 19.7148 9.31074 19.5063 9 19.38C8.69838 19.2469 8.36381 19.2072 8.03941 19.266C7.71502 19.3248 7.41568 19.4795 7.18 19.71L7.12 19.77C6.93425 19.956 6.71368 20.1035 6.47088 20.2041C6.22808 20.3048 5.96783 20.3566 5.705 20.3566C5.44217 20.3566 5.18192 20.3048 4.93912 20.2041C4.69632 20.1035 4.47575 19.956 4.29 19.77C4.10405 19.5843 3.95653 19.3637 3.85588 19.1209C3.75523 18.8781 3.70343 18.6178 3.70343 18.355C3.70343 18.0922 3.75523 17.8319 3.85588 17.5891C3.95653 17.3463 4.10405 17.1257 4.29 16.94L4.35 16.88C4.58054 16.6443 4.73519 16.345 4.794 16.0206C4.85282 15.6962 4.81312 15.3616 4.68 15.06C4.55324 14.7642 4.34276 14.512 4.07447 14.3343C3.80618 14.1566 3.49179 14.0613 3.17 14.06H3C2.46957 14.06 1.96086 13.8493 1.58579 13.4742C1.21071 13.0991 1 12.5904 1 12.06C1 11.5296 1.21071 11.0209 1.58579 10.6458C1.96086 10.2707 2.46957 10.06 3 10.06H3.09C3.42099 10.0523 3.742 9.94512 4.01309 9.75251C4.28417 9.5599 4.49268 9.29074 4.62 8.98C4.75312 8.67838 4.79282 8.34381 4.734 8.01941C4.67519 7.69502 4.52054 7.39568 4.29 7.16L4.23 7.1C4.04405 6.91425 3.89653 6.69368 3.79588 6.45088C3.69523 6.20808 3.64343 5.94783 3.64343 5.685C3.64343 5.42217 3.69523 5.16192 3.79588 4.91912C3.89653 4.67632 4.04405 4.45575 4.23 4.27C4.41575 4.08405 4.63632 3.93653 4.87912 3.83588C5.12192 3.73523 5.38217 3.68343 5.645 3.68343C5.90783 3.68343 6.16808 3.73523 6.41088 3.83588C6.65368 3.93653 6.87425 4.08405 7.06 4.27L7.12 4.33C7.35568 4.56054 7.65502 4.71519 7.97941 4.774C8.30381 4.83282 8.63838 4.79312 8.94 4.66H9C9.29577 4.53324 9.54802 4.32276 9.72569 4.05447C9.90337 3.78618 9.99872 3.47179 10 3.15V3C10 2.46957 10.2107 1.96086 10.5858 1.58579C10.9609 1.21071 11.4696 1 12 1C12.5304 1 13.0391 1.21071 13.4142 1.58579C13.7893 1.96086 14 2.46957 14 3V3.09C14.0013 3.41179 14.0966 3.72618 14.2743 3.99447C14.452 4.26276 14.7042 4.47324 15 4.6C15.3016 4.73312 15.6362 4.77282 15.9606 4.714C16.285 4.65519 16.5843 4.50054 16.82 4.27L16.88 4.21C17.0657 4.02405 17.2863 3.87653 17.5291 3.77588C17.7719 3.67523 18.0322 3.62343 18.295 3.62343C18.5578 3.62343 18.8181 3.67523 19.0609 3.77588C19.3037 3.87653 19.5243 4.02405 19.71 4.21C19.896 4.39575 20.0435 4.61632 20.1441 4.85912C20.2448 5.10192 20.2966 5.36217 20.2966 5.625C20.2966 5.88783 20.2448 6.14808 20.1441 6.39088C20.0435 6.63368 19.896 6.85425 19.71 7.04L19.65 7.1C19.4195 7.33568 19.2648 7.63502 19.206 7.95941C19.1472 8.28381 19.1869 8.61838 19.32 8.92V9C19.4468 9.29577 19.6572 9.54802 19.9255 9.72569C20.1938 9.90337 20.5082 9.99872 20.83 10H21C21.5304 10 22.0391 10.2107 22.4142 10.5858C22.7893 10.9609 23 11.4696 23 12C23 12.5304 22.7893 13.0391 22.4142 13.4142C22.0391 13.7893 21.5304 14 21 14H20.91C20.5882 14.0013 20.2738 14.0966 20.0055 14.2743C19.7372 14.452 19.5268 14.7042 19.4 15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 </svg>
 تنظیمات
 </a>
 </nav>
 
 <div id="methods" class="tab-content active">
 <div class="pro-tracker-card">
 <div class="card-header">
 <div class="card-header-content">
 <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
 <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 <line x1="12" y1="8" x2="12" y2="16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 <line x1="8" y1="12" x2="16" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 </svg>
 <h2>افزودن متد جدید</h2>
 </div>
 </div>
 <div class="card-body">
 <form id="add-method-form" class="pro-form">
 <div class="form-grid">
 <div class="form-group">
 <label for="method_name">نام متد</label>
 <input type="text" id="method_name" name="method_name" class="form-control" required>
 </div>
 <div class="form-group">
 <label for="method_type">نوع متد</label>
 <select id="method_type" name="method_type" class="form-control" required>
 <option value="">انتخاب کنید</option>
 <option value="code">کد رهگیری</option>
 <option value="file">فایل</option>
 <option value="image">تصویر</option>
 <option value="html">کد HTML</option>
 <option value="shortcode">شورتکد</option>
 </select>
 </div>
 </div>
 <button type="submit" class="btn btn-primary">
 <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
 <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 <line x1="12" y1="8" x2="12" y2="16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 <line x1="8" y1="12" x2="16" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 </svg>
 افزودن متد
 </button>
 </form>
 </div>
 </div>
 
 <div class="pro-tracker-card">
 <div class="card-header">
 <div class="card-header-content">
 <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
 <path d="M8 6H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 <path d="M8 12H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 <path d="M8 18H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 <path d="M3 6H3.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 <path d="M3 12H3.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 <path d="M3 18H3.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 </svg>
 <h2>متدهای موجود</h2>
 </div>
 </div>
 <div class="card-body">
 <div id="methods-table">
 <?php $this->display_methods_table(); ?>
 </div>
 </div>
 </div>
 </div>
 
 <div id="users" class="tab-content">
 <div class="pro-tracker-card">
 <div class="card-header">
 <div class="card-header-content">
 <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
 <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 </svg>
 <h2>مدیریت اطلاعات کاربران</h2>
 </div>
 </div>
 <div class="card-body">
 <div class="user-selection">
 <div class="form-group">
 <label for="user_dropdown">انتخاب کاربر</label>
 <select id="user_dropdown" class="form-control user-select">
 <option value="">کاربری را انتخاب کنید...</option>
 </select>
 </div>
 <div class="form-group">
 <label for="user_search">یا جستجو کنید</label>
 <div class="search-box">
 <input type="text" id="user_search" class="form-control" placeholder="نام کاربری یا ایمیل">
 <button type="button" id="search_user" class="btn btn-secondary">
 <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
 <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 <path d="M21 21L16.65 16.65" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 </svg>
 جستجو
 </button>
 </div>
 </div>
 </div>
 
 <div id="user-tracking-form" class="user-form-container" style="display:none;">
 <div class="user-info-card">
 <div id="user-info"></div>
 </div>
 <form id="user-data-form" class="pro-form">
 <input type="hidden" id="selected_user_id" name="user_id">
 <div id="tracking-fields" class="tracking-fields-grid"></div>
 <button type="submit" class="btn btn-success">
 <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
 <polyline points="20,6 9,17 4,12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 </svg>
 ذخیره اطلاعات
 </button>
 </form>
 </div>
 </div>
 </div>
 </div>
 
 <div id="settings" class="tab-content">
 <div class="pro-tracker-card">
 <div class="card-header">
 <div class="card-header-content">
 <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
 <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 <path d="M19.4 15C19.2669 15.3016 19.2272 15.6362 19.286 15.9606C19.3448 16.285 19.4995 16.5843 19.73 16.82L19.79 16.88C19.976 17.0657 20.1235 17.2863 20.2241 17.5291C20.3248 17.7719 20.3766 18.0322 20.3766 18.295C20.3766 18.5578 20.3248 18.8181 20.2241 19.0609C20.1235 19.3037 19.976 19.5243 19.79 19.71C19.6043 19.896 19.3837 20.0435 19.1409 20.1441C18.8981 20.2448 18.6378 20.2966 18.375 20.2966C18.1122 20.2966 17.8519 20.2448 17.6091 20.1441C17.3663 20.0435 17.1457 19.896 16.96 19.71L16.9 19.65C16.6643 19.4195 16.365 19.2648 16.0406 19.206C15.7162 19.1472 15.3816 19.1869 15.08 19.32C14.7842 19.4468 14.532 19.6572 14.3543 19.9255C14.1766 20.1938 14.0813 20.5082 14.08 20.83V21C14.08 21.5304 13.8693 22.0391 13.4942 22.4142C13.1191 22.7893 12.6104 23 12.08 23C11.5496 23 11.0409 22.7893 10.6658 22.4142C10.2907 22.0391 10.08 21.5304 10.08 21V20.91C10.0723 20.579 9.96512 20.2569 9.77251 19.9859C9.5799 19.7148 9.31074 19.5063 9 19.38C8.69838 19.2469 8.36381 19.2072 8.03941 19.266C7.71502 19.3248 7.41568 19.4795 7.18 19.71L7.12 19.77C6.93425 19.956 6.71368 20.1035 6.47088 20.2041C6.22808 20.3048 5.96783 20.3566 5.705 20.3566C5.44217 20.3566 5.18192 20.3048 4.93912 20.2041C4.69632 20.1035 4.47575 19.956 4.29 19.77C4.10405 19.5843 3.95653 19.3637 3.85588 19.1209C3.75523 18.8781 3.70343 18.6178 3.70343 18.355C3.70343 18.0922 3.75523 17.8319 3.85588 17.5891C3.95653 17.3463 4.10405 17.1257 4.29 16.94L4.35 16.88C4.58054 16.6443 4.73519 16.345 4.794 16.0206C4.85282 15.6962 4.81312 15.3616 4.68 15.06C4.55324 14.7642 4.34276 14.512 4.07447 14.3343C3.80618 14.1566 3.49179 14.0613 3.17 14.06H3C2.46957 14.06 1.96086 13.8493 1.58579 13.4742C1.21071 13.0991 1 12.5904 1 12.06C1 11.5296 1.21071 11.0209 1.58579 10.6458C1.96086 10.2707 2.46957 10.06 3 10.06H3.09C3.42099 10.0523 3.742 9.94512 4.01309 9.75251C4.28417 9.5599 4.49268 9.29074 4.62 8.98C4.75312 8.67838 4.79282 8.34381 4.734 8.01941C4.67519 7.69502 4.52054 7.39568 4.29 7.16L4.23 7.1C4.04405 6.91425 3.89653 6.69368 3.79588 6.45088C3.69523 6.20808 3.64343 5.94783 3.64343 5.685C3.64343 5.42217 3.69523 5.16192 3.79588 4.91912C3.89653 4.67632 4.04405 4.45575 4.23 4.27C4.41575 4.08405 4.63632 3.93653 4.87912 3.83588C5.12192 3.73523 5.38217 3.68343 5.645 3.68343C5.90783 3.68343 6.16808 3.73523 6.41088 3.83588C6.65368 3.93653 6.87425 4.08405 7.06 4.27L7.12 4.33C7.35568 4.56054 7.65502 4.71519 7.97941 4.774C8.30381 4.83282 8.63838 4.79312 8.94 4.66H9C9.29577 4.53324 9.54802 4.32276 9.72569 4.05447C9.90337 3.78618 9.99872 3.47179 10 3.15V3C10 2.46957 10.2107 1.96086 10.5858 1.58579C10.9609 1.21071 11.4696 1 12 1C12.5304 1 13.0391 1.21071 13.4142 1.58579C13.7893 1.96086 14 2.46957 14 3V3.09C14.0013 3.41179 14.0966 3.72618 14.2743 3.99447C14.452 4.26276 14.7042 4.47324 15 4.6C15.3016 4.73312 15.6362 4.77282 15.9606 4.714C16.285 4.65519 16.5843 4.50054 16.82 4.27L16.88 4.21C17.0657 4.02405 17.2863 3.87653 17.5291 3.77588C17.7719 3.67523 18.0322 3.62343 18.295 3.62343C18.5578 3.62343 18.8181 3.67523 19.0609 3.77588C19.3037 3.87653 19.5243 4.02405 19.71 4.21C19.896 4.39575 20.0435 4.61632 20.1441 4.85912C20.2448 5.10192 20.2966 5.36217 20.2966 5.625C20.2966 5.88783 20.2448 6.14808 20.1441 6.39088C20.0435 6.63368 19.896 6.85425 19.71 7.04L19.65 7.1C19.4195 7.33568 19.2648 7.63502 19.206 7.95941C19.1472 8.28381 19.1869 8.61838 19.32 8.92V9C19.4468 9.29577 19.6572 9.54802 19.9255 9.72569C20.1938 9.90337 20.5082 9.99872 20.83 10H21C21.5304 10 22.0391 10.2107 22.4142 10.5858C22.7893 10.9609 23 11.4696 23 12C23 12.5304 22.7893 13.0391 22.4142 13.4142C22.0391 13.7893 21.5304 14 21 14H20.91C20.5882 14.0013 20.2738 14.0966 20.0055 14.2743C19.7372 14.452 19.5268 14.7042 19.4 15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 </svg>
 <h2>تنظیمات و راهنما</h2>
 </div>
 </div>
 <div class="card-body">
 <div class="settings-grid">
 <div class="setting-item">
 <div class="setting-icon">
 <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
 <path d="M13 2L3 14H12L11 22L21 10H12L13 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 </svg>
 </div>
 <h3>شورتکد</h3>
 <p>برای نمایش پنل رهگیری در هر صفحه:</p>
 <div class="code-block">
 <code>[order_tracking_panel]</code>
 <button class="copy-btn" data-copy="[order_tracking_panel]">کپی</button>
 </div>
 </div>
 
 <div class="setting-item">
 <div class="setting-icon">
 <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
 <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 <path d="M9.09 9C9.3251 8.33167 9.78915 7.76811 10.4 7.40913C11.0108 7.05016 11.7289 6.91894 12.4272 7.03871C13.1255 7.15849 13.7588 7.52152 14.2151 8.06353C14.6713 8.60553 14.9211 9.29152 14.92 10C14.92 12 11.92 13 11.92 13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 <path d="M12 17H12.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 </svg>
 </div>
 <h3>راهنمای استفاده</h3>
 <div class="guide-steps">
 <div class="step">
 <span class="step-number">1</span>
 <div class="step-content">
 <h4>ایجاد متدها</h4>
 <p>متدهای مورد نیاز خود را در تب "مدیریت متدها" ایجاد کنید</p>
 </div>
 </div>
 <div class="step">
 <span class="step-number">2</span>
 <div class="step-content">
 <h4>تنظیم کاربران</h4>
 <p>در تب "مدیریت کاربران" برای هر کاربر اطلاعات مربوطه را وارد کنید</p>
 </div>
 </div>
 <div class="step">
 <span class="step-number">3</span>
 <div class="step-content">
 <h4>نمایش اطلاعات</h4>
 <p>کاربران اطلاعات خود را در پنل کاربری مشاهده خواهند کرد</p>
 </div>
 </div>
 </div>
 </div>
 
 <div class="setting-item">
 <div class="setting-icon">
 <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
 <rect x="2" y="3" width="20" height="14" rx="2" ry="2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 <line x1="8" y1="21" x2="16" y2="21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 <line x1="12" y1="17" x2="12" y2="21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 </svg>
 </div>
 <h3>انواع متدها</h3>
 <div class="method-types">
 <div class="method-type">
 <strong>کد رهگیری:</strong> متن ساده برای کدهای پیگیری
 </div>
 <div class="method-type">
 <strong>فایل:</strong> لینک فایل قابل دانلود
 </div>
 <div class="method-type">
 <strong>تصویر:</strong> نمایش تصاویر
 </div>
 <div class="method-type">
 <strong>کد HTML:</strong> کد HTML سفارشی
 </div>
 <div class="method-type">
 <strong>شورتکد:</strong> اجرای شورتکدهای وردپرس
 </div>
 </div>
 </div>
 </div>
 </div>
 </div>
 </div>
 </div>
 </div>
 
 <style>
 @import url('https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap');
 
 .pro-tracker-admin {
 font-family: 'Vazirmatn', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
 background: #f8fafc;
 margin: 0 -20px 0 -2px;
 padding: 25px;
 direction: rtl;
 min-height: 100vh;
 }
 
 .pro-tracker-admin * {
 font-family: 'Vazirmatn', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
 }
 
 .pro-tracker-header {
 background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
 color: white;
 padding: 40px;
 border-radius: 20px;
 margin-bottom: 35px;
 box-shadow: 0 15px 35px rgba(102, 126, 234, 0.3);
 position: relative;
 overflow: hidden;
 }
 
 .pro-tracker-header:before {
 content: '';
 position: absolute;
 top: 0;
 left: 0;
 right: 0;
 bottom: 0;
 background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
 opacity: 0.3;
 }
 
 .header-content {
 display: flex;
 align-items: center;
 gap: 25px;
 position: relative;
 z-index: 1;
 }
 
 .header-icon {
 background: rgba(255,255,255,0.2);
 padding: 20px;
 border-radius: 15px;
 backdrop-filter: blur(10px);
 }
 
 .header-text h1 {
 margin: 0 0 8px 0;
 font-size: 2.8em;
 font-weight: 700;
 text-shadow: 0 2px 10px rgba(0,0,0,0.2);
 }
 
 .header-text p {
 margin: 0;
 opacity: 0.9;
 font-size: 1.2em;
 font-weight: 400;
 }
 
 .nav-tab-wrapper {
 background: white;
 border-radius: 20px;
 padding: 20px;
 margin-bottom: 30px;
 box-shadow: 0 8px 30px rgba(0,0,0,0.08);
 border: none;
 display: flex;
 gap: 10px;
 }
 
 .nav-tab {
 background: transparent !important;
 border: none !important;
 padding: 18px 28px !important;
 margin: 0 !important;
 border-radius: 15px !important;
 transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1) !important;
 font-weight: 600 !important;
 font-size: 15px !important;
 color: #64748b !important;
 position: relative !important;
 overflow: hidden !important;
 display: flex !important;
 align-items: center !important;
 gap: 12px !important;
 text-decoration: none !important;
 flex: 1;
 justify-content: center;
 }
 
 .nav-tab:before {
 content: '';
 position: absolute;
 top: 0;
 left: 0;
 right: 0;
 bottom: 0;
 background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
 opacity: 0;
 transition: opacity 0.4s ease;
 z-index: -1;
 }
 
 .nav-tab:hover:not(.nav-tab-active) {
 background: #f1f5f9 !important;
 color: #667eea !important;
 transform: translateY(-3px);
 box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
 }
 
 .nav-tab.nav-tab-active {
 background: transparent !important;
 color: white !important;
 transform: translateY(-3px);
 box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
 }
 
 .nav-tab.nav-tab-active:before {
 opacity: 1;
 }
 
 .nav-tab.nav-tab-active:hover {
 background: transparent !important;
 color: white !important;
 }
 
 .tab-content {
 display: none;
 animation: fadeIn 0.4s ease-in-out;
 }
 
 .tab-content.active {
 display: block;
 }
 
 @keyframes fadeIn {
 from { opacity: 0; transform: translateY(15px); }
 to { opacity: 1; transform: translateY(0); }
 }
 
 .pro-tracker-card {
 background: white;
 border-radius: 20px;
 box-shadow: 0 8px 30px rgba(0,0,0,0.08);
 margin-bottom: 30px;
 overflow: hidden;
 border: 1px solid #e2e8f0;
 transition: all 0.4s ease;
 }
 
 .pro-tracker-card:hover {
 box-shadow: 0 15px 50px rgba(0,0,0,0.12);
 transform: translateY(-5px);
 }
 
 .card-header {
 background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
 color: white;
 padding: 30px;
 position: relative;
 }
 
 .card-header:before {
 content: '';
 position: absolute;
 top: 0;
 left: 0;
 right: 0;
 bottom: 0;
 background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
 opacity: 0.3;
 }
 
 .card-header-content {
 display: flex;
 align-items: center;
 gap: 15px;
 position: relative;
 z-index: 1;
 }
 
 .card-header h2 {
 margin: 0;
 font-weight: 600;
 font-size: 1.5em;
 }
 
 .card-body {
 padding: 40px;
 }
 
 .pro-form {
 max-width: 100%;
 }
 
 .form-grid {
 display: grid;
 grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
 gap: 30px;
 margin-bottom: 30px;
 }
 
 .form-group {
 margin-bottom: 25px;
 }
 
 .form-group label {
 display: block;
 margin-bottom: 12px;
 font-weight: 600;
 color: #334155;
 font-size: 15px;
 }
 
 .form-control {
 width: 100%;
 padding: 18px 22px;
 border: 2px solid #e2e8f0;
 border-radius: 15px;
 font-size: 15px;
 font-weight: 400;
 transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
 box-sizing: border-box;
 background: #fafbfc;
 }
 
 .form-control:focus {
 outline: none;
 border-color: #667eea;
 background: white;
 box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
 transform: translateY(-2px);
 }
 
 .btn {
 padding: 18px 35px;
 border: none;
 border-radius: 15px;
 font-weight: 600;
 text-decoration: none;
 display: inline-flex;
 align-items: center;
 gap: 12px;
 cursor: pointer;
 transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
 font-size: 15px;
 position: relative;
 overflow: hidden;
 }
 
 .btn:before {
 content: '';
 position: absolute;
 top: 0;
 left: -100%;
 width: 100%;
 height: 100%;
 background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
 transition: left 0.6s;
 }
 
 .btn:hover:before {
 left: 100%;
 }
 
 .btn-primary {
 background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
 color: white;
 box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
 }
 
 .btn-secondary {
 background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
 color: #8b4513;
 box-shadow: 0 6px 20px rgba(252, 182, 159, 0.3);
 }
 
 .btn-success {
 background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
 color: #065f46;
 box-shadow: 0 6px 20px rgba(168, 237, 234, 0.3);
 }
 
 .btn-danger {
 background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
 color: #dc2626;
 box-shadow: 0 6px 20px rgba(255, 154, 158, 0.3);
 }
 
 .btn:hover {
 transform: translateY(-4px);
 box-shadow: 0 12px 35px rgba(0,0,0,0.15);
 }
 
 .btn:active {
 transform: translateY(-2px);
 }
 
 .methods-grid {
 display: grid;
 grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
 gap: 30px;
 }
 
 .method-card {
 background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
 border: 2px solid #e2e8f0;
 border-radius: 20px;
 padding: 30px;
 transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
 position: relative;
 overflow: hidden;
 }
 
 .method-card:before {
 content: '';
 position: absolute;
 top: 0;
 left: 0;
 right: 0;
 height: 5px;
 background: linear-gradient(90deg, #667eea, #764ba2, #f093fb, #f5576c);
 opacity: 0;
 transition: opacity 0.4s ease;
 }
 
 .method-card:hover {
 border-color: #667eea;
 transform: translateY(-8px);
 box-shadow: 0 20px 50px rgba(102, 126, 234, 0.15);
 }
 
 .method-card:hover:before {
 opacity: 1;
 }
 
 .method-header {
 display: flex;
 justify-content: space-between;
 align-items: center;
 margin-bottom: 18px;
 }
 
 .method-name {
 font-weight: 600;
 color: #1e293b;
 font-size: 18px;
 }
 
 .method-type {
 background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
 color: white;
 padding: 8px 16px;
 border-radius: 25px;
 font-size: 13px;
 font-weight: 500;
 box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
 }
 
 .method-date {
 color: #64748b;
 font-size: 14px;
 margin-bottom: 25px;
 font-weight: 400;
 }
 
 .user-selection {
 display: grid;
 grid-template-columns: 1fr 1fr;
 gap: 30px;
 margin-bottom: 40px;
 }
 
 .search-box {
 display: flex;
 gap: 15px;
 }
 
 .user-select {
 min-height: 55px;
 }
 
 .user-info-card {
 background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
 color: white;
 padding: 30px;
 border-radius: 20px;
 margin-bottom: 30px;
 box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
 position: relative;
 overflow: hidden;
 }
 
 .user-info-card:before {
 content: '';
 position: absolute;
 top: 0;
 left: 0;
 right: 0;
 bottom: 0;
 background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
 opacity: 0.3;
 }
 
 .tracking-fields-grid {
 display: grid;
 gap: 30px;
 }
 
 .tracking-field {
 background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
 border: 2px solid #e2e8f0;
 border-radius: 20px;
 padding: 30px;
 transition: all 0.4s ease;
 }
 
 .tracking-field:hover {
 border-color: #cbd5e1;
 box-shadow: 0 8px 25px rgba(0,0,0,0.08);
 transform: translateY(-2px);
 }
 
 .tracking-field h5 {
 margin: 0 0 20px 0;
 color: #1e293b;
 font-weight: 600;
 font-size: 16px;
 display: flex;
 align-items: center;
 gap: 12px;
 }
 
 .file-upload-area {
 display: flex;
 gap: 15px;
 align-items: center;
 flex-wrap: wrap;
 }
 
 .upload-file {
 white-space: nowrap;
 }
 
 .settings-grid {
 display: grid;
 grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
 gap: 35px;
 }
 
 .setting-item {
 background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
 padding: 35px;
 border-radius: 20px;
 border: 2px solid #e2e8f0;
 transition: all 0.4s ease;
 text-align: center;
 }
 
 .setting-item:hover {
 border-color: #cbd5e1;
 box-shadow: 0 12px 35px rgba(0,0,0,0.08);
 transform: translateY(-5px);
 }
 
 .setting-icon {
 background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
 color: white;
 width: 70px;
 height: 70px;
 border-radius: 20px;
 display: flex;
 align-items: center;
 justify-content: center;
 margin: 0 auto 25px auto;
 box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
 }
 
 .setting-item h3 {
 margin: 0 0 20px 0;
 color: #1e293b;
 font-weight: 600;
 font-size: 18px;
 }
 
 .code-block {
 background: #1e293b;
 color: #e2e8f0;
 padding: 25px;
 border-radius: 15px;
 font-family: 'JetBrains Mono', 'Fira Code', Consolas, monospace;
 display: flex;
 justify-content: space-between;
 align-items: center;
 box-shadow: inset 0 4px 15px rgba(0,0,0,0.3);
 margin-top: 20px;
 }
 
 .copy-btn {
 background: #475569;
 color: white;
 border: none;
 padding: 10px 20px;
 border-radius: 10px;
 cursor: pointer;
 font-size: 13px;
 font-weight: 500;
 transition: all 0.3s ease;
 }
 
 .copy-btn:hover {
 background: #64748b;
 transform: translateY(-2px);
 }
 
 .guide-steps {
 display: flex;
 flex-direction: column;
 gap: 30px;
 text-align: right;
 }
 
 .step {
 display: flex;
 align-items: flex-start;
 gap: 20px;
 }
 
 .step-number {
 background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
 color: white;
 width: 40px;
 height: 40px;
 border-radius: 50%;
 display: flex;
 align-items: center;
 justify-content: center;
 font-weight: 600;
 flex-shrink: 0;
 font-size: 16px;
 box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
 }
 
 .step-content h4 {
 margin: 0 0 10px 0;
 color: #1e293b;
 font-weight: 600;
 font-size: 16px;
 }
 
 .step-content p {
 margin: 0;
 color: #64748b;
 line-height: 1.7;
 font-weight: 400;
 }
 
 .method-types {
 display: flex;
 flex-direction: column;
 gap: 18px;
 text-align: right;
 }
 
 .method-type {
 padding: 20px;
 background: white;
 border-radius: 15px;
 border-right: 5px solid #667eea;
 box-shadow: 0 4px 15px rgba(0,0,0,0.05);
 transition: all 0.4s ease;
 }
 
 .method-type:hover {
 transform: translateX(-5px);
 box-shadow: 0 8px 25px rgba(0,0,0,0.1);
 }
 
 .method-type strong {
 color: #1e293b;
 font-weight: 600;
 }
 
 @media (max-width: 768px) {
 .pro-tracker-admin {
 margin: 0 -10px;
 padding: 20px 15px;
 }
 
 .pro-tracker-header {
 padding: 30px 25px;
 }
 
 .header-content {
 flex-direction: column;
 text-align: center;
 gap: 20px;
 }
 
 .header-text h1 {
 font-size: 2.2em;
 }
 
 .nav-tab-wrapper {
 padding: 15px;
 flex-direction: column;
 gap: 8px;
 }
 
 .nav-tab {
 padding: 15px 20px !important;
 font-size: 14px !important;
 }
 
 .card-body {
 padding: 30px 25px;
 }
 
 .form-grid,
 .user-selection {
 grid-template-columns: 1fr;
 gap: 25px;
 }
 
 .settings-grid {
 grid-template-columns: 1fr;
 }
 
 .methods-grid {
 grid-template-columns: 1fr;
 }
 
 .search-box {
 flex-direction: column;
 }
 
 .file-upload-area {
 flex-direction: column;
 align-items: stretch;
 }
 
 .code-block {
 flex-direction: column;
 gap: 20px;
 align-items: stretch;
 }
 
 .copy-btn {
 align-self: center;
 }
 }
 
 @media (max-width: 480px) {
 .pro-tracker-header {
 padding: 25px 20px;
 }
 
 .header-text h1 {
 font-size: 2em;
 }
 
 .nav-tab {
 padding: 12px 18px !important;
 font-size: 13px !important;
 }
 
 .card-body {
 padding: 25px 20px;
 }
 
 .btn {
 padding: 15px 25px;
 font-size: 14px;
 }
 
 .setting-item {
 padding: 25px 20px;
 }
 
 .setting-icon {
 width: 60px;
 height: 60px;
 }
 }
 </style>
 
 <script>
 jQuery(document).ready(function($) {
 $('.nav-tab').click(function(e) {
 e.preventDefault();
 
 if ($(this).hasClass('nav-tab-active')) {
 return false;
 }
 
 var target = $(this).attr('href');
 $('.nav-tab').removeClass('nav-tab-active');
 $(this).addClass('nav-tab-active');
 $('.tab-content').removeClass('active');
 $(target).addClass('active');
 });
 
 loadAllUsers();
 
 $('#add-method-form').submit(function(e) {
 e.preventDefault();
 var formData = {
 action: 'save_tracking_method',
 method_name: $('#method_name').val(),
 method_type: $('#method_type').val(),
 nonce: '<?php echo wp_create_nonce('tracking_nonce'); ?>'
 };
 
 $.post(ajaxurl, formData, function(response) {
 if(response.success) {
 alert('متد با موفقیت اضافه شد');
 location.reload();
 } else {
 alert('خطا در افزودن متد');
 }
 });
 });
 
 $('#user_dropdown').change(function() {
 var userId = $(this).val();
 if(userId) {
 loadUserTrackingFormById(userId);
 }
 });
 
 $('#search_user').click(function() {
 var searchTerm = $('#user_search').val();
 if(searchTerm) {
 loadUserTrackingForm(searchTerm);
 }
 });
 
 $('#user-data-form').submit(function(e) {
 e.preventDefault();
 var formData = $(this).serialize();
 formData += '&action=save_user_tracking_data&nonce=<?php echo wp_create_nonce('tracking_nonce'); ?>';
 
 $.post(ajaxurl, formData, function(response) {
 if(response.success) {
 alert('اطلاعات با موفقیت ذخیره شد');
 } else {
 alert('خطا در ذخیره اطلاعات');
 }
 });
 });
 
 $(document).on('click', '.delete-method', function() {
 if(confirm('آیا از حذف این متد اطمینان دارید؟')) {
 var methodId = $(this).data('id');
 $.post(ajaxurl, {
 action: 'delete_tracking_method',
 method_id: methodId,
 nonce: '<?php echo wp_create_nonce('tracking_nonce'); ?>'
 }, function(response) {
 if(response.success) {
 location.reload();
 }
 });
 }
 });
 
 $(document).on('click', '.copy-btn', function() {
 var text = $(this).data('copy');
 navigator.clipboard.writeText(text).then(function() {
 alert('کپی شد!');
 });
 });
 
 var mediaUploader;
 
 $(document).on('click', '.upload-file', function(e) {
 e.preventDefault();
 
 var button = $(this);
 var methodId = button.data('method');
 var inputField = button.siblings('input[name="tracking_files[' + methodId + ']"]');
 
 if (mediaUploader) {
 mediaUploader.open();
 return;
 }
 
 mediaUploader = wp.media({
 title: 'انتخاب فایل',
 button: {
 text: 'انتخاب فایل'
 },
 library: {
 type: ['image', 'application/pdf']
 },
 multiple: false
 });
 
 mediaUploader.on('select', function() {
 var attachment = mediaUploader.state().get('selection').first().toJSON();
 inputField.val(attachment.url);
 });
 
 mediaUploader.open();
 });
 });
 
 function loadAllUsers() {
 jQuery.post(ajaxurl, {
 action: 'get_all_users',
 nonce: '<?php echo wp_create_nonce('tracking_nonce'); ?>'
 }, function(response) {
 if(response.success) {
 jQuery('#user_dropdown').html(response.data.options);
 }
 });
 }
 
 function loadUserTrackingForm(searchTerm) {
 jQuery.post(ajaxurl, {
 action: 'get_user_tracking_data',
 search_term: searchTerm,
 nonce: '<?php echo wp_create_nonce('tracking_nonce'); ?>'
 }, function(response) {
 if(response.success) {
 jQuery('#selected_user_id').val(response.data.user_id);
 jQuery('#user-info').html(response.data.user_info);
 jQuery('#tracking-fields').html(response.data.fields_html);
 jQuery('#user-tracking-form').show();
 }
 });
 }
 
 function loadUserTrackingFormById(userId) {
 jQuery.post(ajaxurl, {
 action: 'get_user_tracking_data',
 user_id: userId,
 nonce: '<?php echo wp_create_nonce('tracking_nonce'); ?>'
 }, function(response) {
 if(response.success) {
 jQuery('#selected_user_id').val(response.data.user_id);
 jQuery('#user-info').html(response.data.user_info);
 jQuery('#tracking-fields').html(response.data.fields_html);
 jQuery('#user-tracking-form').show();
 }
 });
 }
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
 $type_labels = array(
 'code' => 'کد رهگیری',
 'file' => 'فایل',
 'image' => 'تصویر',
 'html' => 'کد HTML',
 'shortcode' => 'شورتکد'
 );
 $type_label = isset($type_labels[$method->method_type]) ? $type_labels[$method->method_type] : $method->method_type;
 
 echo '<div class="method-card">';
 echo '<div class="method-header">';
 echo '<div class="method-name">' . esc_html($method->method_name) . '</div>';
 echo '<div class="method-type">' . $type_label . '</div>';
 echo '</div>';
 echo '<div class="method-date">ایجاد شده: ' . date_i18n('Y/m/d H:i', strtotime($method->created_at)) . '</div>';
 echo '<button class="btn btn-danger btn-sm delete-method" data-id="' . $method->id . '">';
 echo '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">';
 echo '<polyline points="3,6 5,6 21,6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
 echo '<path d="M19,6V20C19,21 18,22 17,22H7C6,22 5,21 5,20V6M8,6V4C8,3 9,2 10,2H14C15,2 16,3 16,4V6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
 echo '</svg>';
 echo ' حذف';
 echo '</button>';
 echo '</div>';
 }
 echo '</div>';
 } else {
 echo '<div style="text-align: center; padding: 80px 30px; color: #64748b; font-size: 16px; font-weight: 400;">';
 echo '<div style="font-size: 5em; margin-bottom: 25px;">📋</div>';
 echo '<h3 style="margin: 0 0 15px 0; color: #334155; font-size: 1.5em;">هیچ متدی تعریف نشده است</h3>';
 echo '<p style="margin: 0;">برای شروع، متد جدیدی اضافه کنید</p>';
 echo '</div>';
 }
 }
 
 public function get_all_users() {
 check_ajax_referer('tracking_nonce', 'nonce');
 
 if(!current_user_can('manage_options')) {
 wp_die('دسترسی غیرمجاز');
 }
 
 $users = get_users(array('number' => 100));
 $options = '<option value="">کاربری را انتخاب کنید...</option>';
 
 foreach($users as $user) {
 $options .= '<option value="' . $user->ID . '">' . $user->display_name . ' (' . $user->user_email . ')</option>';
 }
 
 wp_send_json_success(array('options' => $options));
 }
 
 public function save_tracking_method() {
 check_ajax_referer('tracking_nonce', 'nonce');
 
if(!current_user_can('manage_options')) {
 wp_die('دسترسی غیرمجاز');
}

global $wpdb;
$table = $wpdb->prefix . 'wc_tracking_methods';

$method_name = sanitize_text_field($_POST['method_name']);
$method_type = sanitize_text_field($_POST['method_type']);

$result = $wpdb->insert(
 $table,
 array(
 'method_name' => $method_name,
 'method_type' => $method_type
 ),
 array('%s', '%s')
);

if($result) {
 wp_send_json_success();
} else {
 wp_send_json_error();
}
}

public function delete_tracking_method() {
check_ajax_referer('tracking_nonce', 'nonce');

if(!current_user_can('manage_options')) {
 wp_die('دسترسی غیرمجاز');
}

global $wpdb;
$table = $wpdb->prefix . 'wc_tracking_methods';
$method_id = intval($_POST['method_id']);

$result = $wpdb->delete($table, array('id' => $method_id), array('%d'));

if($result) {
 wp_send_json_success();
} else {
 wp_send_json_error();
}
}

public function get_user_tracking_data() {
check_ajax_referer('tracking_nonce', 'nonce');

if(!current_user_can('manage_options')) {
 wp_die('دسترسی غیرمجاز');
}

$user = null;

if(isset($_POST['user_id'])) {
 $user_id = intval($_POST['user_id']);
 $user = get_user_by('ID', $user_id);
} else {
 $search_term = sanitize_text_field($_POST['search_term']);
 $user = get_user_by('login', $search_term);
 if(!$user) {
 $user = get_user_by('email', $search_term);
 }
}

if(!$user) {
 wp_send_json_error('کاربر یافت نشد');
}

global $wpdb;
$methods_table = $wpdb->prefix . 'wc_tracking_methods';
$data_table = $wpdb->prefix . 'wc_tracking_data';

$methods = $wpdb->get_results("SELECT * FROM $methods_table ORDER BY method_name");

$user_info = '<div style="display: flex; align-items: center; gap: 25px; position: relative; z-index: 1;">';
$user_info .= '<div class="user-avatar" style="border-radius: 50%; overflow: hidden; box-shadow: 0 6px 20px rgba(0,0,0,0.2);">' . get_avatar($user->ID, 80) . '</div>';
$user_info .= '<div>';
$user_info .= '<h3 style="margin: 0; font-size: 1.4em; font-weight: 600;">' . $user->display_name . '</h3>';
$user_info .= '<p style="margin: 10px 0 0 0; opacity: 0.9; font-weight: 400; font-size: 1.1em;">' . $user->user_email . '</p>';
$user_info .= '</div>';
$user_info .= '</div>';

$fields_html = '';

foreach($methods as $method) {
 $existing_data = $wpdb->get_row($wpdb->prepare(
 "SELECT * FROM $data_table WHERE user_id = %d AND method_id = %d",
 $user->ID, $method->id
 ));

 $fields_html .= '<div class="tracking-field">';
 $fields_html .= '<h5>';
 $fields_html .= '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">';
 $fields_html .= '<path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
 $fields_html .= '<path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
 $fields_html .= '<path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
 $fields_html .= '</svg>';
 $fields_html .= ' ' . esc_html($method->method_name) . '</h5>';

 switch($method->method_type) {
 case 'code':
 $value = $existing_data ? $existing_data->tracking_value : '';
 $fields_html .= '<input type="text" name="tracking_data[' . $method->id . ']" value="' . esc_attr($value) . '" class="form-control" placeholder="کد رهگیری را وارد کنید">';
 break;

 case 'html':
 $value = $existing_data ? $existing_data->tracking_value : '';
 $fields_html .= '<textarea name="tracking_data[' . $method->id . ']" class="form-control" rows="6" placeholder="کد HTML را وارد کنید" style="font-family: \'JetBrains Mono\', monospace; font-size: 14px;">' . esc_textarea($value) . '</textarea>';
 break;

 case 'shortcode':
 $value = $existing_data ? $existing_data->tracking_value : '';
 $fields_html .= '<input type="text" name="tracking_data[' . $method->id . ']" value="' . esc_attr($value) . '" class="form-control" placeholder="[shortcode] شورتکد را وارد کنید" style="font-family: \'JetBrains Mono\', monospace;">';
 break;

 case 'file':
 case 'image':
 $file_url = $existing_data ? $existing_data->file_url : '';
 $fields_html .= '<div class="file-upload-area">';
 $fields_html .= '<input type="url" name="tracking_files[' . $method->id . ']" value="' . esc_url($file_url) . '" class="form-control" placeholder="لینک فایل را وارد کنید یا از دکمه انتخاب استفاده کنید">';
 $fields_html .= '<button type="button" class="btn btn-secondary upload-file" data-method="' . $method->id . '">';
 $fields_html .= '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">';
 $fields_html .= '<path d="M21 15V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
 $fields_html .= '<polyline points="7,10 12,15 17,10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
 $fields_html .= '<line x1="12" y1="15" x2="12" y2="3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
 $fields_html .= '</svg>';
 $fields_html .= ' انتخاب فایل';
 $fields_html .= '</button>';
 $fields_html .= '</div>';
 if($file_url) {
 $fields_html .= '<div style="margin-top: 18px; padding: 15px; background: #f0f9ff; border: 2px solid #bae6fd; border-radius: 12px;">';
 $fields_html .= '<a href="' . esc_url($file_url) . '" target="_blank" style="color: #0369a1; text-decoration: none; font-weight: 500; display: flex; align-items: center; gap: 8px;">';
 $fields_html .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">';
 $fields_html .= '<path d="M18 13V19C18 19.5304 17.7893 20.0391 17.4142 20.4142C17.0391 20.7893 16.5304 21 16 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V8C3 7.46957 3.21071 6.96086 3.58579 6.58579C3.96086 6.21071 4.46957 6 5 6H11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
 $fields_html .= '<polyline points="15,3 21,3 21,9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
 $fields_html .= '<line x1="10" y1="14" x2="21" y2="3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
 $fields_html .= '</svg>';
 $fields_html .= 'مشاهده فایل فعلی</a>';
 $fields_html .= '</div>';
 }
 break;
 }

 $fields_html .= '</div>';
}

wp_send_json_success(array(
 'user_id' => $user->ID,
 'user_info' => $user_info,
 'fields_html' => $fields_html
));
}

public function save_user_tracking_data() {
check_ajax_referer('tracking_nonce', 'nonce');

if(!current_user_can('manage_options')) {
 wp_die('دسترسی غیرمجاز');
}

$user_id = intval($_POST['user_id']);
$tracking_data = isset($_POST['tracking_data']) ? $_POST['tracking_data'] : array();
$tracking_files = isset($_POST['tracking_files']) ? $_POST['tracking_files'] : array();

global $wpdb;
$table = $wpdb->prefix . 'wc_tracking_data';

foreach($tracking_data as $method_id => $value) {
 $method_id = intval($method_id);
 $value = wp_kses_post($value);

 $existing = $wpdb->get_row($wpdb->prepare(
 "SELECT id FROM $table WHERE user_id = %d AND method_id = %d",
 $user_id, $method_id
 ));

 if($existing) {
 $wpdb->update(
 $table,
 array('tracking_value' => $value),
 array('user_id' => $user_id, 'method_id' => $method_id),
 array('%s'),
 array('%d', '%d')
 );
 } else {
 $wpdb->insert(
 $table,
 array(
 'user_id' => $user_id,
 'method_id' => $method_id,
 'tracking_value' => $value
 ),
 array('%d', '%d', '%s')
 );
 }
}

foreach($tracking_files as $method_id => $file_url) {
 $method_id = intval($method_id);
 
 if(!empty($file_url)) {
 $user_dir = $this->create_user_directory($user_id);
 $file_path = '';
 
 if(filter_var($file_url, FILTER_VALIDATE_URL)) {
 $file_extension = pathinfo(parse_url($file_url, PHP_URL_PATH), PATHINFO_EXTENSION);
 $file_name = 'method_' . $method_id . '_' . time() . '.' . $file_extension;
 $file_path = $user_dir . '/' . $file_name;
 
 $file_content = wp_remote_get($file_url);
 if(!is_wp_error($file_content)) {
 file_put_contents($file_path, wp_remote_retrieve_body($file_content));
 $file_url = str_replace(wp_upload_dir()['basedir'], wp_upload_dir()['baseurl'], $file_path);
 }
 }
 
 $existing = $wpdb->get_row($wpdb->prepare(
 "SELECT id FROM $table WHERE user_id = %d AND method_id = %d",
 $user_id, $method_id
 ));

 if($existing) {
 $wpdb->update(
 $table,
 array(
 'file_url' => esc_url_raw($file_url),
 'file_path' => $file_path
 ),
 array('user_id' => $user_id, 'method_id' => $method_id),
 array('%s', '%s'),
 array('%d', '%d')
 );
 } else {
 $wpdb->insert(
 $table,
 array(
 'user_id' => $user_id,
 'method_id' => $method_id,
 'file_url' => esc_url_raw($file_url),
 'file_path' => $file_path
 ),
 array('%d', '%d', '%s', '%s')
 );
 }
 }
}

wp_send_json_success();
}

public function upload_tracking_file() {
check_ajax_referer('tracking_nonce', 'nonce');

if(!current_user_can('manage_options')) {
 wp_die('دسترسی غیرمجاز');
}

if(!function_exists('wp_handle_upload')) {
 require_once(ABSPATH . 'wp-admin/includes/file.php');
}

$uploadedfile = $_FILES['file'];
$upload_overrides = array('test_form' => false);
$movefile = wp_handle_upload($uploadedfile, $upload_overrides);

if($movefile && !isset($movefile['error'])) {
 wp_send_json_success(array('url' => $movefile['url']));
} else {
 wp_send_json_error($movefile['error']);
}
}

public function display_tracking_panel($atts) {
if(!is_user_logged_in()) {
 return '<div class="wc-tracking-login-notice">
 <div class="notice-content">
 <div class="login-icon">
 <svg width="60" height="60" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
 <rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 <circle cx="12" cy="16" r="1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 <path d="M7 11V7C7 5.67392 7.52678 4.40215 8.46447 3.46447C9.40215 2.52678 10.6739 2 12 2C13.3261 2 14.5979 2.52678 15.5355 3.46447C16.4732 4.40215 17 5.67392 17 7V11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 </svg>
 </div>
 <h3>ورود به حساب کاربری</h3>
 <p>برای مشاهده اطلاعات رهگیری سفارشات، لطفاً وارد حساب کاربری خود شوید.</p>
 <a href="' . wp_login_url() . '" class="login-btn">ورود به حساب</a>
 </div>
 </div>';
}

$user_id = get_current_user_id();

global $wpdb;
$methods_table = $wpdb->prefix . 'wc_tracking_methods';
$data_table = $wpdb->prefix . 'wc_tracking_data';

$methods = $wpdb->get_results("SELECT * FROM $methods_table ORDER BY method_name");

if(!$methods) {
 return '<div class="wc-tracking-empty">
 <div class="empty-content">
 <div class="empty-icon">
 <svg width="80" height="80" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
 <path d="M21 16V8C21 6.89543 20.1046 6 19 6H5C3.89543 6 3 6.89543 3 8V16C3 17.1046 3.89543 18 5 18H19C20.1046 18 21 17.1046 21 16Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 <path d="M3 10H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
 </svg>
 </div>
 <h3>اطلاعات رهگیری موجود نیست</h3>
 <p>در حال حاضر هیچ اطلاعات رهگیری‌ای برای نمایش موجود نیست.</p>
 </div>
 </div>';
}

$output = '<div class="wc-tracking-panel">';
$output .= '<div class="tracking-header">';
$output .= '<div class="header-content">';
$output .= '<div class="header-icon">';
$output .= '<svg width="50" height="50" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">';
$output .= '<path d="M1 3H5L7.68 14.39C7.77144 14.8504 8.02191 15.264 8.38755 15.5583C8.75318 15.8526 9.2107 16.009 9.68 16H19.4C19.8693 16.009 20.3268 15.8526 20.6925 15.5583C21.0581 15.264 21.3086 14.8504 21.4 14.39L23 6H6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
$output .= '<circle cx="9" cy="20" r="1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
$output .= '<circle cx="20" cy="20" r="1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
$output .= '</svg>';
$output .= '</div>';
$output .= '<div class="header-text">';
$output .= '<h2>اطلاعات رهگیری سفارشات</h2>';
$output .= '<p>وضعیت و جزئیات سفارشات شما</p>';
$output .= '</div>';
$output .= '</div>';
$output .= '</div>';

$output .= '<div class="tracking-grid">';

foreach($methods as $method) {
 $tracking_data = $wpdb->get_row($wpdb->prepare(
 "SELECT * FROM $data_table WHERE user_id = %d AND method_id = %d",
 $user_id, $method->id
 ));

 if($tracking_data) {
 $output .= '<div class="tracking-card">';
 $output .= '<div class="tracking-card-header">';
 $output .= '<h3>' . esc_html($method->method_name) . '</h3>';
 $output .= '<span class="tracking-type">' . $this->get_method_type_label($method->method_type) . '</span>';
 $output .= '</div>';

 $output .= '<div class="tracking-card-body">';

 switch($method->method_type) {
 case 'code':
 if($tracking_data->tracking_value) {
 $output .= '<div class="tracking-code-display">';
 $output .= '<label>کد رهگیری:</label>';
 $output .= '<div class="code-value">' . esc_html($tracking_data->tracking_value) . '</div>';
 $output .= '</div>';
 }
 break;

 case 'html':
 if($tracking_data->tracking_value) {
 $output .= '<div class="tracking-html-display">';
 $output .= wp_kses_post($tracking_data->tracking_value);
 $output .= '</div>';
 }
 break;

 case 'shortcode':
 if($tracking_data->tracking_value) {
 $output .= '<div class="tracking-shortcode-display">';
 $output .= do_shortcode($tracking_data->tracking_value);
 $output .= '</div>';
 }
 break;

 case 'file':
 if($tracking_data->file_url) {
 $output .= '<div class="tracking-file-display">';
 $output .= '<a href="' . esc_url($tracking_data->file_url) . '" target="_blank" class="file-download-btn">';
 $output .= '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">';
 $output .= '<path d="M14 2H6C5.46957 2 4.96086 2.21071 4.58579 2.58579C4.21071 2.96086 4 3.46957 4 4V20C4 20.5304 4.21071 21.0391 4.58579 21.4142C4.96086 21.7893 5.46957 22 6 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V8L14 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
 $output .= '<polyline points="14,2 14,8 20,8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
 $output .= '<line x1="16" y1="13" x2="8" y2="13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
 $output .= '<line x1="16" y1="17" x2="8" y2="17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
 $output .= '<polyline points="10,9 9,9 8,9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
 $output .= '</svg>';
 $output .= ' دانلود فایل';
 $output .= '</a>';
 $output .= '</div>';
 }
 break;

 case 'image':
 if($tracking_data->file_url) {
 $output .= '<div class="tracking-image-display">';
 $output .= '<img src="' . esc_url($tracking_data->file_url) . '" alt="' . esc_attr($method->method_name) . '" class="tracking-image">';
 $output .= '</div>';
 }
 break;
 }

 $output .= '</div>';

 $output .= '<div class="tracking-card-footer">';
 $output .= '<small>';
 $output .= '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">';
 $output .= '<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
 $output .= '<polyline points="12,6 12,12 16,14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
 $output .= '</svg>';
 $output .= ' آخرین بروزرسانی: ' . date_i18n('Y/m/d H:i', strtotime($tracking_data->updated_at)) . '</small>';
 $output .= '</div>';

 $output .= '</div>';
 }
}

$output .= '</div>';
$output .= '</div>';

$output .= '<style>
@import url("https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap");

.wc-tracking-panel {
font-family: "Vazirmatn", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
max-width: 1200px;
margin: 30px auto;
padding: 0 25px;
direction: rtl;
}

.wc-tracking-panel * {
font-family: "Vazirmatn", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
}

.tracking-header {
text-align: center;
margin-bottom: 50px;
padding: 50px 40px;
background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
color: white;
border-radius: 25px;
box-shadow: 0 15px 50px rgba(102, 126, 234, 0.3);
position: relative;
overflow: hidden;
}

.tracking-header:before {
content: "";
position: absolute;
top: 0;
left: 0;
right: 0;
bottom: 0;
background: url("data:image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 100 100\'><defs><pattern id=\'grain\' width=\'100\' height=\'100\' patternUnits=\'userSpaceOnUse\'><circle cx=\'25\' cy=\'25\' r=\'1\' fill=\'white\' opacity=\'0.1\'/><circle cx=\'75\' cy=\'75\' r=\'1\' fill=\'white\' opacity=\'0.1\'/></pattern></defs><rect width=\'100\' height=\'100\' fill=\'url(%23grain)\'/></svg>");
opacity: 0.3;
}

.header-content {
display: flex;
align-items: center;
justify-content: center;
gap: 30px;
position: relative;
z-index: 1;
}

.header-icon {
background: rgba(255,255,255,0.2);
padding: 25px;
border-radius: 20px;
backdrop-filter: blur(10px);
}

.header-text h2 {
margin: 0 0 15px 0;
font-size: 2.5em;
font-weight: 700;
text-shadow: 0 2px 10px rgba(0,0,0,0.2);
}

.header-text p {
margin: 0;
opacity: 0.9;
font-size: 1.2em;
font-weight: 400;
}

.tracking-grid {
display: grid;
grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
gap: 35px;
}

.tracking-card {
background: white;
border-radius: 25px;
box-shadow: 0 10px 40px rgba(0,0,0,0.1);
overflow: hidden;
transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
border: 2px solid #f1f5f9;
}

.tracking-card:hover {
transform: translateY(-10px);
box-shadow: 0 25px 60px rgba(0,0,0,0.15);
border-color: #667eea;
}

.tracking-card-header {
background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
color: white;
padding: 30px;
display: flex;
justify-content: space-between;
align-items: center;
position: relative;
}

.tracking-card-header:before {
content: "";
position: absolute;
top: 0;
left: 0;
right: 0;
bottom: 0;
background: url("data:image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 100 100\'><defs><pattern id=\'grain\' width=\'100\' height=\'100\' patternUnits=\'userSpaceOnUse\'><circle cx=\'25\' cy=\'25\' r=\'1\' fill=\'white\' opacity=\'0.1\'/><circle cx=\'75\' cy=\'75\' r=\'1\' fill=\'white\' opacity=\'0.1\'/></pattern></defs><rect width=\'100\' height=\'100\' fill=\'url(%23grain)\'/></svg>");
opacity: 0.3;
}

.tracking-card-header h3 {
margin: 0;
font-weight: 600;
font-size: 1.4em;
position: relative;
z-index: 1;
}

.tracking-type {
background: rgba(255,255,255,0.25);
padding: 10px 18px;
border-radius: 30px;
font-size: 0.85em;
font-weight: 500;
position: relative;
z-index: 1;
backdrop-filter: blur(10px);
}

.tracking-card-body {
padding: 40px;
}

.tracking-code-display label {
display: block;
margin-bottom: 15px;
font-weight: 600;
color: #334155;
font-size: 15px;
}

.code-value {
background: linear-gradient(145deg, #f8fafc 0%, #e2e8f0 100%);
border: 2px solid #cbd5e1;
border-radius: 15px;
padding: 25px;
font-family: "JetBrains Mono", "Fira Code", Consolas, monospace !important;
font-size: 1.2em;
font-weight: 600;
color: #1e293b;
word-break: break-all;
box-shadow: inset 0 4px 15px rgba(0,0,0,0.1);
}

.tracking-html-display,
.tracking-shortcode-display {
background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
border-radius: 15px;
padding: 25px;
border: 2px solid #e2e8f0;
box-shadow: inset 0 2px 10px rgba(0,0,0,0.05);
}

.file-download-btn {
display: inline-flex;
align-items: center;
gap: 15px;
background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
color: white;
padding: 20px 40px;
border-radius: 35px;
text-decoration: none;
font-weight: 600;
transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
font-size: 16px;
}

.file-download-btn:hover {
transform: translateY(-5px);
box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
color: white;
text-decoration: none;
}

.tracking-image {
width: 100%;
height: auto;
border-radius: 15px;
box-shadow: 0 8px 30px rgba(0,0,0,0.15);
transition: all 0.4s ease;
}

.tracking-image:hover {
transform: scale(1.03);
box-shadow: 0 12px 40px rgba(0,0,0,0.2);
}

.tracking-card-footer {
padding: 25px 40px;
background: linear-gradient(145deg, #f8fafc 0%, #e2e8f0 100%);
border-top: 2px solid #cbd5e1;
}

.tracking-card-footer small {
color: #64748b;
display: flex;
align-items: center;
gap: 10px;
font-weight: 500;
font-size: 14px;
}

.wc-tracking-login-notice,
.wc-tracking-empty {
margin: 40px auto;
max-width: 600px;
}

.notice-content,
.empty-content {
text-align: center;
padding: 80px 40px;
background: white;
border-radius: 25px;
box-shadow: 0 15px 50px rgba(0,0,0,0.1);
border: 2px solid #f1f5f9;
}

.login-icon,
.empty-icon {
margin-bottom: 30px;
display: flex;
justify-content: center;
color: #667eea;
}

.wc-tracking-login-notice h3,
.wc-tracking-empty h3 {
margin: 0 0 20px 0;
color: #1e293b;
font-size: 1.8em;
font-weight: 600;
}

.wc-tracking-login-notice p,
.wc-tracking-empty p {
margin: 0 0 35px 0;
color: #64748b;
line-height: 1.8;
font-weight: 400;
font-size: 16px;
}

.login-btn {
display: inline-block;
background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
color: white;
padding: 18px 40px;
border-radius: 35px;
text-decoration: none;
font-weight: 600;
transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
font-size: 16px;
}

.login-btn:hover {
transform: translateY(-5px);
box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
color: white;
text-decoration: none;
}

@media (max-width: 768px) {
.wc-tracking-panel {
padding: 0 20px;
}

.tracking-grid {
grid-template-columns: 1fr;
gap: 30px;
}

.tracking-header {
padding: 40px 30px;
margin-bottom: 40px;
}

.header-content {
flex-direction: column;
gap: 25px;
}

.header-text h2 {
font-size: 2em;
}

.tracking-card-header {
flex-direction: column;
gap: 15px;
text-align: center;
}

.tracking-card-body,
.tracking-card-footer {
padding: 30px 25px;
}

.notice-content,
.empty-content {
padding: 60px 30px;
}
}

@media (max-width: 480px) {
.tracking-header {
padding: 30px 20px;
}

.header-text h2 {
font-size: 1.8em;
}

.tracking-card-body,
.tracking-card-footer {
padding: 25px 20px;
}

.file-download-btn {
padding: 15px 30px;
font-size: 14px;
}

.notice-content,
.empty-content {
padding: 50px 25px;
}
}
</style>';

return $output;
}

private function get_method_type_label($type) {
$labels = array(
 'code' => 'کد رهگیری',
 'file' => 'فایل',
 'image' => 'تصویر',
 'html' => 'HTML',
 'shortcode' => 'شورتکد'
);
return isset($labels[$type]) ? $labels[$type] : $type;
}

public function add_tracking_endpoint() {
add_rewrite_endpoint('order-tracking', EP_ROOT | EP_PAGES);
}

public function add_tracking_menu_item($items) {
$items['order-tracking'] = 'رهگیری سفارشات';
return $items;
}

public function tracking_endpoint_content() {
echo $this->display_tracking_panel(array());
}

public function enqueue_frontend_scripts() {
wp_enqueue_script('jquery');
wp_enqueue_media();
}

public function enqueue_admin_scripts($hook) {
if(strpos($hook, 'wc-order-tracking') !== false) {
 wp_enqueue_script('jquery');
 wp_enqueue_media();
}
}
}

new WC_Order_Tracking_System();
?>
