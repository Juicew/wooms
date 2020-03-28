<?php

namespace WooMS;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}


/**
 * Import Product Images
 */
class SiteHealth
{


    public static $plugin_dir = ABSPATH . "wp-content/plugins/";
    public static $base_plugin_url = "wooms/wooms.php";
    public static $xt_plugin_url = "wooms-extra/wooms-extra.php";
    public static $settings_page_url = 'admin.php?page=mss-settings';
    public static $wooms_check_login_password;
    public static $wooms_check_woocommerce_version_for_wooms;



    public static function init()
    {
        add_filter('site_status_tests', [__CLASS__, 'new_health_tests']);

        add_action('wp_ajax_health-check-wooms-check_login_password', [__CLASS__, 'wooms_check_login_password']);

        add_action('wp_ajax_health-check-wooms-check_webhooks', [__CLASS__, 'wooms_check_webhooks']);
    }

    /**
     * adding hooks for site health
     *
     * @param [type] $tests
     * @return void
     */
    public static function new_health_tests($tests)
    {

        $tests['direct']['wooms_check_woocommerce_version_for_wooms'] = [
            'test'  => [__CLASS__, 'wooms_check_woocommerce_version_for_wooms'],
        ];

        $tests['direct']['wooms_check_different_versions'] = [
            'test'  => [__CLASS__, 'wooms_check_different_versions_of_plugins'],
        ];

        $tests['async']['wooms_check_credentials'] = [
            'test'  => 'wooms_check_login_password',
        ];

        $tests['async']['wooms_check_webhooks'] = [
            'test'  => 'wooms_check_webhooks',
        ];

        return $tests;
    }

    /**
     * Checking version WooCommerce
     *
     * @return void
     */
    public static function wooms_check_woocommerce_version_for_wooms()
    {

        $wc_version = WC()->version;
        $result = [
            'label' => 'Проверка версии WooCommerce для работы плагина WooMS & WooMS XT',
            'status'      => 'good',
            'badge'       => [
                'label' => 'Уведомление WooMS',
                'color' => 'blue',
            ],
            'description' => sprintf('Все хорошо! Спасибо что выбрали наш плагин %s', '🙂'),
            'test' => 'wooms_check_woocommerce_version_for_wooms' // this is only for class in html block
        ];

        if (version_compare($wc_version, '3.6.0', '<=')) {
            $result['status'] = 'critical';
            $result['badge']['color'] = 'red';
            $result['actions'] = sprintf(
                '<p><a href="%s">%s</a></p>',
                admin_url('plugins.php'),
                sprintf("Обновить WooCommerce")
            );
            $result['description'] = sprintf('Ваша версия WooCommerce плагина %s. Обновите пожалуйста WooCommerce чтобы WooMS & WooMS XT работали ', $wc_version);
        }

        return $result;
    }

    /**
     * check differences of versions
     *
     * @return void
     */
    public static function wooms_check_different_versions_of_plugins()
    {

        $base_plugin_data = get_plugin_data(self::$plugin_dir . self::$base_plugin_url);
        $xt_plugin_data = get_plugin_data(self::$plugin_dir . self::$xt_plugin_url);
        $base_version = $base_plugin_data['Version'];
        $xt_version = $xt_plugin_data['Version'];

        $result = [
            'label' => 'Разные версии плагина WooMS & WooMS XT',
            'status'      => 'good',
            'badge'       => [
                'label' => 'Уведомление WooMS',
                'color' => 'blue',
            ],
            'description' => sprintf('Все хорошо! Спасибо что выбрали наш плагин %s', '🙂'),
            'test' => 'wooms_check_different_versions' // this is only for class in html block
        ];

        if ($base_version !== $xt_version) {
            $result['status'] = 'critical';
            $result['badge']['color'] = 'red';
            $result['actions'] = sprintf(
                '<p><a href="%s">%s</a></p>',
                admin_url('plugins.php'),
                sprintf("Обновить плагин")
            );
        }

        /**
         * if base version is lower
         */
        if ($base_version < $xt_version) {

            $result['description'] = sprintf('Пожалуйста, обновите плагин %s для лучшей производительности', $base_plugin_data['Name']);
        }

        /**
         * if xt version is lower
         */
        if ($base_version > $xt_version) {
            $result['description'] = sprintf('Пожалуйста, обновите плагин %s для лучшей производительности', $xt_plugin_data['Name']);
        }

        return $result;
    }

    /**
     * checking credentials
     *
     * @return void
     */
    public static function wooms_check_login_password()
    {
        check_ajax_referer('health-check-site-status');

        if (!current_user_can('view_site_health_checks')) {
            wp_send_json_error();
        }

        $base_plugin_data = get_plugin_data(self::$plugin_dir . self::$base_plugin_url);
        $url = 'https://online.moysklad.ru/api/remap/1.2/security/token';
        $data_api = wooms_request($url, [], 'POST');

        $result = [
            'label' => "Проверка логина и пароля МойСклад",
            'status'      => 'good',
            'badge'       => [
                'label' => 'Уведомление WooMS',
                'color' => 'blue',
            ],
            'description' => sprintf("Все хорошо! Спасибо что используете наш плагин %s", '🙂'),
            'test' => 'wooms_check_credentials' // this is only for class in html block
        ];

        if (!array_key_exists('errors', $data_api)) {
            wp_send_json_success($result);
        }

        if (array_key_exists('errors', $data_api)) {
            $result['status'] = 'critical';
            $result['badge']['color'] = 'red';
            $result['description'] = sprintf("Что то пошло не так при подключении к МойСклад", '🤔');
        }

        /**
         * 1056 is mean that login or the password is not correct
         */
        if ($data_api["errors"][0]['code'] === 1056) {
            $result['description'] = sprintf("Неверный логин или пароль от МойСклад %s", '🤔');
            $result['actions'] = sprintf(
                '<p><a href="%s">%s</a></p>',
                self::$settings_page_url,
                sprintf("Поменять доступы")
            );
        }

        set_transient('wooms_check_login_password', true, 60 * 30);

        wp_send_json_success($result);
    }

    /**
     * Check can we add webhooks
     *
     * @return bool
     */
    public static function wooms_check_webhooks()
    {
        $url  = 'https://online.moysklad.ru/api/remap/1.2/entity/webhook';

        $employee_url = 'https://online.moysklad.ru/api/remap/1.1/context/employee';

        // создаем веб хук в МойСклад
        $data   = array(
            'url'        => rest_url('/wooms/v1/order-update/'),
            'action'     => "UPDATE",
            "entityType" => "customerorder",
        );
        $api_result = wooms_request($url, $data);

        $result = [
            'label' => "Проверка подписки МойСклад",
            'status'      => 'good',
            'badge'       => [
                'label' => 'Уведомление WooMS',
                'color' => 'blue',
            ],
            'description' => sprintf("Все хорошо! Спасибо что используете наш плагин %s", '🙂'),
            'test' => 'wooms_check_weebhooks' // this is only for class in html block
        ];


        if (!empty($api_result['errors'])) {

            $result['status'] = 'critical';
            $result['badge']['color'] = 'red';
            $result['description'] = sprintf("%s %s", $api_result['errors'][0]['error'], '❌');
        }

        // Checking permissions too
        $data_api_p = wooms_request($employee_url, [], 'GET');

        foreach ($data_api_p['permissions']['webhook'] as $permission) {
            if (!$permission) {
                $description = "У данного пользователя не хватает прав для работы с вебхуками";
                $result['description'] = sprintf('%s %s', $description, '❌');
                if (!empty($api_result['errors'])) {
                    $result['description'] = sprintf("1. %s 2. %s %s", $api_result['errors'][0]['error'], $description, '❌');
                }
            }

            // Добовляем значение для вывода ошибки в здаровье сайта
            set_transient('wooms_check_moysklad_tariff', $result['description'], 60 * 60);
        }

        wp_send_json_success($result);
    }
}

SiteHealth::init();
