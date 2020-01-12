<?php
/**
 * Plugin Name: WooMS
 * Plugin URI: https://wpcraft.ru/product/wooms/
 * Description: Integration for WooCommerce and MoySklad (moysklad.ru, МойСклад) via REST API (wooms)
 * Author: WPCraft
 * Author URI: https://wpcraft.ru/
 * Developer: WPCraft
 * Developer URI: https://wpcraft.ru/
 * Text Domain: wooms
 * Domain Path: /languages
 * WC requires at least: 3.3
 * WC tested up to: 3.5
 * PHP requires at least: 5.6
 * WP requires at least: 5.0
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Version: 5.8
 * WooMS XT Latest: 5.8
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Core
 */
class WooMS_Core {

  /**
   * $wooms_version
   */
  public static $wooms_version;

  /**
   * $plugin_file_path
   */
  public static $plugin_file_path;

  /**
   * The init
   */
  public static function init(){

    /**
     * Этот класс должен работать до хука plugins_loaded
     * Птм что иначе хук wooms_activate не срабатывает
     */
    require_once __DIR__ . '/inc/class-logger.php';
    require_once __DIR__ . '/functions.php';

    /**
     * Add hook for activate plugin
     */
    register_activation_hook( __FILE__, function(){
      do_action('wooms_activate');
    });

    register_deactivation_hook( __FILE__, function(){
      do_action('wooms_deactivate');
    });

    add_filter('woocommerce_status_log_items_per_page', function($per_page){
      return 100;
    });

    add_action('plugins_loaded', function(){

      /**
       * Подключение компонентов
       */
      require_once __DIR__ . '/inc/class-menu-settings.php';
      require_once __DIR__ . '/inc/class-menu-tool.php';
      require_once __DIR__ . '/inc/class-products-walker.php';
      require_once __DIR__ . '/inc/MSImagesTrait.php';
      require_once __DIR__ . '/inc/ProductGallery.php';
      require_once __DIR__ . '/inc/class-import-product-categories.php';
      require_once __DIR__ . '/inc/class-import-prices.php';
      require_once __DIR__ . '/inc/class-hide-old-products.php';

      require_once __DIR__ . '/inc/ProductImage.php';

      add_action( 'admin_notices', array(__CLASS__, 'show_notices_35') );
      add_action( 'admin_notices', array(__CLASS__, 'show_error_notice') );

      add_action( 'after_plugin_row_wooms-extra/wooms-extra.php', array(__CLASS__, 'xt_plugin_update_message'), 10, 2 );

      add_filter( "plugin_action_links_" . plugin_basename( __FILE__ ), array(__CLASS__, 'plugin_add_settings_link') );

    });

  }

  /**
   * Add Settings link in pligins list
   */
  public static function plugin_add_settings_link( $links ) {
    $settings_link = '<a href="admin.php?page=mss-settings">Настройки</a>';
    $xt_link = '<a href="//wpcraft.ru/product/wooms-xt/" target="_blank">Расширенная версия</a>';
    array_unshift($links, $xt_link);
    array_unshift($links, $settings_link);
    return $links;
  }

  /**
   * Проверяем актуальность расширенной версии и сообщаем если есть обновления
   * Проверка происходит на базе данных в комментарии базовой версии
   */
  public static function xt_plugin_update_message( $data, $response ) {

    $data = get_file_data( __FILE__, array('xt_version' => 'WooMS XT Latest') );
    $xt_version_remote = $data['xt_version'];

    // $data = get_file_data( __FILE__, array('xt_version' => 'WooMS XT Latest') );
    $data = get_plugin_data( plugin_dir_path( __DIR__ ) . "wooms-extra/wooms-extra.php", false, false );
    $xt_version_local = $data['Version'];
    // $data = plugin_dir_path( __DIR__ );

    $check = version_compare( $xt_version_local, $xt_version_remote, '>=' );

    if($check){
      return;
    }
    $wp_list_table = _get_list_table( 'WP_Plugins_List_Table' );

    printf(
      '<tr class="plugin-update-tr">
        <td colspan="%s" class="plugin-update update-message notice inline notice-warning notice-alt">
          <div class="update-message">
            <span>Вышла новая версия плагина WooMS XT: %s. Скачать обновление можно в консоли: <a href="https://wpcraft.ru/my" target="_blank">https://wpcraft.ru/my</a></span>
          </div>
        </td>
      </tr>',
      $wp_list_table->get_column_count(),
      $xt_version_remote
    );

  }

  /**
   * Ошибки - проверка и уведомленич
   */
  public static function show_error_notice() {
    global $wp_version;

    $wooms_version = get_file_data( __FILE__, array('wooms_ver' => 'Version') );

    $message = '';

    $php       = 5.6;
    $wp        = 4.7;
    $php_check = version_compare( PHP_VERSION, $php, '<' );
    $wp_check  = version_compare( $wp_version, $wp, '<' );

    if ( $php_check ) {
      $message .= sprintf('<p>Для работы плагина WooMS требуется более свежая версия php минимум - %s</p>', $php);
    }

    if ( $wp_check ) {
      $message .= sprintf('<p>Для работы плагина WooMS требуется более свежая версия WordPress минимум - %s</p>', $wp);
    }

    $message = apply_filters('wooms_error_message', $message);

    if ( empty($message) ) {
      return;
    }

    printf('<div class="notice notice-error">%s</div>', $message);
  }

  /**
   * Вывод сообщения в консоли
   */
  public static function show_notices_35() {

    if(is_plugin_active( 'wooms-extra/wooms-extra.php' )){
      $data = get_plugin_data( plugin_dir_path( __DIR__ ) . "wooms-extra/wooms-extra.php", false, false );
      if(empty($data['Version'])){
        return;
      }

      $xt_version_local = $data['Version'];
      // $data = plugin_dir_path( __DIR__ );

      $check = version_compare( $xt_version_local, '3.5', '>=' );

      if($check){
        return;
      }
      ?>
      <div class="notice notice-error">
        <p>
          <strong>Плагин WooMS XT нужно срочно обновить до версии 3.5! </strong>
          <a href="https://wpcraft.ru/my">https://wpcraft.ru/my</a>
        </p>
      </div>
      <?php
    }

    return;

  }

}

WooMS_Core::init();
