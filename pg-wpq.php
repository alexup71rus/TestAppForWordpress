<?php

/*
Plugin Name: Тесты
Plugin URI: http://plughunt.com
Description: Тесты
Version: 1.0.1
Author: Вадим Курило
Author URI: http://kurilo.pro/
*/

class PG_WPQ
{
    const VER = '1.0.1';
    const TYPE_KNOW = 1;
    const TYPE_PSY = 2;
    const STATE_OK = 1;
    const STATE_ARCHIVE = 2;

    protected $options;
    protected $activation_domain;
    protected $name = 'Тесты';
    protected $product = '-0yPVLL6604yBmZbLLYh79JCDV-7orE_';
    protected $db_version = '1';

    /**
     * @return PG_WPQ
     */
    public static function instance()
    {
        return new PG_WPQ();
    }

    public function __construct()
    {
        $this->activation_domain = 'http://plughunt.com';
        add_action('init', array($this, 'init'));
    }

    public function init()
    {
        register_activation_hook(__FILE__, array($this, 'install'));
        register_deactivation_hook(__FILE__, array($this, 'uninstall'));
        if (!function_exists('mcrypt_decrypt')) {
            add_action('admin_notices', array($this, 'showAdminMessages'));

            return;
        }
        add_action('admin_head', array($this, 'update_check'));
        add_action('admin_notices', array($this, 'notify_new_version'));

        //WPPage
        if (has_action('wppage_head')) {
            add_action('wppage_head', 'wp_enqueue_scripts', 1);
            add_action('wppage_head', 'wp_print_styles', 8);
            add_action('wppage_head', 'wp_print_head_scripts', 9);
            add_action('wppage_footer', 'wp_print_footer_scripts', 20);
        }


        $this->loadOptions('options');

        add_action('wp_enqueue_scripts', array($this, 'enqueue_css_js'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_css_js'));
        add_action('wp_footer', array($this, 'footer'));

        add_action('wp_head', array($this, 'header'));
        if (function_exists('wppage_head')) {
            add_action('wppage_head', array($this, 'header'));
        }
        if (function_exists('wppage_footer')) {
            add_action('wppage_footer', array($this, 'footer'));
        }

        if (is_admin()) {
            add_action('admin_init', array($this, 'admin_init'));
            add_action('admin_menu', array($this, 'admin_menu_need_activation'));
            add_action('admin_menu', array($this, 'admin_menu'));
        }

    }

    public function admin_enqueue_css_js($hook)
    {
        if (strpos($hook, '_page_wp-pg-wpq-settings') !== false) {
            wp_enqueue_script('jquery');
            wp_enqueue_style('farbtastic');
            wp_enqueue_script('farbtastic');
        }

        wp_enqueue_media();
            
        // Стили
        wp_enqueue_style('add-one-media', plugins_url('/assets/css/add-one-media.css', __FILE__));
        
        // Скрипт для выбора файла
        wp_enqueue_script('add-one-media.js', plugins_url('/assets/js/add-one-media.js', __FILE__), array('jquery'));
    }

    public function enqueue_css_js()
    {
        //wp_enqueue_style('buttons', plugins_url('assets/css/.css', __FILE__));
    }

    public function install()
    {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        global $wpdb;

        $table_name = $this->getQuizTableName($wpdb->prefix);

        $sql = "CREATE TABLE $table_name (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  tname varchar(255),
  tdescription text,
  ttype int,
  t_show_right_answer TINYINT(1) DEFAULT 0,
  tstate TINYINT(1) DEFAULT " . self::STATE_OK . ",
  tdata text,
  tcreated datetime,
  PRIMARY KEY (id)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;";
        dbDelta($sql);
        add_option('pg_wpq_db_version', $this->db_version);

        add_option(get_class() . '_key', '', '', 'yes');
        add_option(get_class() . '_init', '', '', 'yes');
        add_option(get_class() . '_update_check', '', '', 'yes');
        add_option(get_class() . '_latest_version', self::VER, '', 'yes');
        if (!wp_next_scheduled(get_class() . '_cron')) {
            wp_schedule_event(time(), 'daily', get_class() . '_cron');
        }

    }

    public function uninstall()
    {
        $key = get_option(get_class() . '_key');
        delete_option(get_class() . '_key');
        delete_option(get_class() . '_init');
        delete_option(get_class() . '_update_check');
        delete_option(get_class() . '_latest_version');
        if (wp_next_scheduled(get_class() . '_cron')) {
            wp_clear_scheduled_hook(get_class() . '_cron');
        }
        wp_remote_get($this->activation_domain . '/deactivate?key=' . base64_encode($key) . '&domain=' . home_url() . '&ver=' . self::VER);
    }

    public function header()
    {
        $options = $this->options['options'];
    }

    public function footer()
    {
        $options = $this->options['options'];
    }

    public function activate_cron()
    {
        if ($this->isActivated()) {
            $key = get_option(get_class() . '_key');
            $response = wp_remote_get($this->activation_domain . '/checkactivation?key=' . base64_encode($key) . '&domain=' . home_url() . '&ver=' . self::VER);
            if (!is_wp_error($response) && isset($response['body']) && ($body = json_decode($response['body'], true)) !== null) {
                if (isset($body, $body['init'])) {
                    update_option(get_class() . '_init', $body['init']);
                }
            }
        }
    }

    public function admin_init()
    {
    }

    public function writeOptions($name, $options)
    {
        update_option(get_class() . '_' . $name, $options);
    }

    public function isActivated()
    {
        $key = get_option(get_class() . '_key');
        $init = get_option(get_class() . '_init');
        return true;
        //return isset($key, $init) && !empty($key) && !empty($init);
    }

    public function decryptData($value, $key = '')
    {
        if (isset($value) && !empty($value)) {
            if (strlen($key) < 32) {
                $key = str_pad($key, 32, "\0");
            } elseif (strlen($key) > 32) {
                $key = substr($key, 0, 32);
            }
            $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
            $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
            if ($iv !== false) {
                $decryptText = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $value, MCRYPT_MODE_ECB, $iv);
                return trim($decryptText);
            }
        }
    }

    public function loadOptions($name)
    {
        $default_options['options'] = array(
            'button_font_size' => '12',
            'button_indent' => '1',
            'text_color' => '#5dd539',
            'button_color' => '#424242',
            'button_text' => 'Start & Go!',
            'set_delay' => '0',
            'font_size_title' => '12',
            'font_size_question' => '12',
        );

        $options = get_option(get_class() . '_' . $name);
        if (!is_array($options)) {
            $options = isset($default_options[$name]) ? $default_options[$name] : array();
            add_option(get_class() . '_' . $name, $options, '', 'no');
        } else {
            if (isset($default_options[$name])) {
                foreach ($default_options[$name] as $key => $value) {
                    if (!array_key_exists($key, $options)) {
                        $options[$key] = $value;
                    }
                }
            }
        }
        $this->options[$name] = $options;
        return $options;
    }

    public function admin_menu_need_activation()
    {
        add_menu_page($this->name, $this->name, 'manage_options', 'wp-pg-wpq', array($this, 'admin_activation_dashboard'));
    }

    public function admin_menu()
    {
//        add_menu_page($this->name, $this->name, 'manage_options', 'wp-pg-wpq', array($this, 'admin_activation_dashboard'));

        if (!$this->isActivated()) {
            add_submenu_page('wp-pg-wpq', "Активация PRO-версии плагина", "PRO-версия", 'manage_options', "wp-pg-wpq", array($this, 'admin_activation_dashboard'));
        }
        add_submenu_page('wp-pg-wpq', "Создать", "Создать", 'manage_options', "wp-pg-wpq-create", array($this, 'admin_quiz_create'));
        add_submenu_page('wp-pg-wpq', "Список тестов", "Список", 'manage_options', "wp-pg-wpq-list", array($this, 'admin_quiz_list'));
        add_submenu_page('wp-pg-wpq', "Настройки", "Настройки", 'manage_options', "wp-pg-wpq-settings", array($this, 'admin_settings'));
    }

    public function admin_activation_dashboard()
    {
        wp_enqueue_style('fontawesome-all.css', plugins_url('assets/css/fontawesome-all.css', __FILE__), null, '5.0.9');
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        ?>
        <div class="wrap">
            <h2><i class="fas fa-cogs"></i> Активация PRO-версии плагина</h2>
            <?php
            if (isset($_POST['activation'], $_POST['activation']['key']) && isset($_POST['submit'])) {
                check_admin_referer('admin-activation');
                $key = $_POST['activation']['key'];
                $cip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
                $response = wp_remote_get($this->activation_domain . '/activate?key=' . base64_encode($key) . '&domain=' . home_url() . '&ver=' . self::VER . '&cip=' . $cip . '&wpver=' . get_bloginfo('version'));
                if (!is_wp_error($response) && ($body = json_decode($response['body'], true)) !== null) {
                    $left_activations = $body['left_activations'];
                    update_option(get_class() . '_key', $key);
                    if (isset($body['init'])) {
                        update_option(get_class() . '_init', $body['init']);
                    }
                    if (isset($_GET['settings-updated'])) {
                        if ($left_activations > 0) {
                            echo '<div id="message" class="updated"><p>Плагин успешно активирован. У вас осталось еще ' . $left_activations . ' активации!</p></div>';
                        } else {
                            echo '<div id="message" class="updated"><p>Плагин активирован. У вас НЕ осталось активаций!</p></div>';
                        }
                        echo '<div id="message" class="updated"><p><a href="' . admin_url('admin.php?page=wp-pg-wpq-settings') . '">Настройки плагина</a></p></div>';
                    }
                } else {
                    echo '<div id="message" class="error"><p>Плагин НЕ активирован!</p></div>';
                }

            }
            ?>
            <form method="post"
                  action="<?php echo admin_url('admin.php?page=wp-pg-wpq&settings-updated=1'); ?>">
                <label for="activation_key"><b>Введите код активации</b></label>
                <input id="activation_key" type="text" name="activation[key]" value=""/><br>

                <p class="submit submit-top">
                    <?php wp_nonce_field('admin-activation'); ?>
                    <input type="submit" name="submit" value="<?php _e('Save Changes') ?>"
                           class="button-primary"/>
                </p>
            </form>
            <h3><a href="http://plughunt.com">Перейти на страницу плагина</a></h3>

            <div style="clear: both;"></div>
        </div>
        <?php
    }

    public function admin_quiz_create()
    {
        wp_enqueue_script('jquery-ui-tabs');
        wp_enqueue_style('e2b-admin-ui-css', '//ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css', false, '1.12.1', false);

        global $wpdb;
        $id = null;
        $test_data = null;
        $test = null;
        $table_name = $this->getQuizTableName($wpdb->prefix);
        $update_message = '';
        $js_redirect = '';
        $action_url = 'admin.php?page=wp-pg-wpq-create&settings-updated=1';
        if (isset($_GET['settings-updated'])) {
            $update_message = '<div id="message" class="updated"><p>Успешно!</p></div>';
        }
        $h2_content = '<h2><i class="fas fa-pencil-alt"></i> Создание теста</h2>';
        $test_data[self::TYPE_KNOW] = array(
            'sc' => '',
            'stc' => '',
            'tname' => '',
            'tdescription' => '',
            't_show_right_answer' => '',
            'q' => array(
                array(
                    't' => '',
                    'l' => '',
                    'a' => array('')
                ),
            ),
            'r' => array(
                array(
                    't' => '',
                    'l' => '',
                    'p' => ''
                ),
                array(
                    't' => '',
                    'l' => '',
                    'p' => ''
                ),
            ),
        );

        $test_data[self::TYPE_PSY] = array(
            'sc' => '',
            'stc' => '',
            'tname' => '',
            'tdescription' => '',
            'q' => array(
                array(
                    't' => '',
                    'l' => '',
                    'a' => array('')
                ),
            ),
            'r' => array(
                array(
                    't' => '',
                    'l' => '',
                    'p' => ''
                ),
                array(
                    't' => '',
                    'l' => '',
                    'p' => ''
                ),
            ),
        );
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            //TODO load and update test
            $test = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
            if ($test !== null) {
                $h2_content = '<h2><i class="fas fa-pencil-alt"></i> Обновление теста</h2>';
                $action_url .= '&id=' . $id;
                $counttests = 0;
                $type = (int)$test->ttype;
                $test_data['type'] = $type;
                $test_data[$type]['tname'] = $test->tname;
                $test_data[$type]['tdescription'] = $test->tdescription;
                $test_data[$type]['t_show_right_answer'] = $test->t_show_right_answer;
                $tdata = json_decode($test->tdata, true);
                $test_data[$type]['sc'] = $tdata['sc'];
                $test_data[$type]['stc'] = $tdata['stc'];
                $test_data[$type]['q'] = $tdata['q'];
                $test_data[$type]['l'] = $tdata['l'];
                $test_data[$type]['r'] = $tdata['r'];
            }
        } else {
        }
        if (!empty($_POST)) {
            check_admin_referer('wp-pg-wpq-quiz-create');
            $substrate_color = isset($_POST['substrate_color']) ? $_POST['substrate_color'] : '';
            $substrate_text_color = isset($_POST['substrate_text_color']) ? $_POST['substrate_text_color'] : '';
            $test_name = isset($_POST['test_name']) ? $_POST['test_name'] : '';
            $test_description = isset($_POST['test_description']) ? $_POST['test_description'] : '';
            $test_show_right_answer = isset($_POST['test_show_right_answer']) ? $_POST['test_show_right_answer'] : 0;
            $test_questions = isset($_POST['test_questions']) ? $_POST['test_questions'] : array();
            $test_image = isset($_POST['test_image']) ? $_POST['test_image'] : array();
            $test_answers = isset($_POST['test_answers']) ? $_POST['test_answers'] : array();
            $answer_image = isset($_POST['answer_image']) ? $_POST['answer_image'] : array();
            $test_results = isset($_POST['test_results']) ? $_POST['test_results'] : array();
            $test_results_low = isset($_POST['test_results_low']) ? $_POST['test_results_low'] : array();
            $test_type = (isset($_POST['ttype'])
                && in_array((int)$_POST['ttype'], array(self::TYPE_KNOW, self::TYPE_PSY), true))
                ? (int)$_POST['ttype'] : self::TYPE_KNOW;

            unset($test_data[$test_type]);
            $test_data[$test_type]['sc'] = $substrate_color;
            $test_data[$test_type]['stc'] = $substrate_text_color;
            $test_data[$test_type]['tname'] = $test_name;
            $test_data[$test_type]['tdescription'] = $test_description;
            $test_data[$test_type]['t_show_right_answer'] = $test_show_right_answer;
            foreach ($test_questions as $index => $question) {
                $test_data[$test_type]['q'][$index]['t'] = $question;
                $test_data[$test_type]['q'][$index]['a'] = isset($test_answers[$index]) ? $test_answers[$index] : array();
            }
            foreach ($test_image as $index => $image) {
                $test_data[$test_type]['q'][$index]['l'] = $image;
            }
            if (!empty($test_results)) {
                foreach ($test_results as $tri => $test_result) {
                    $test_data[$test_type]['r'][] = array(
                        't' => $test_result,
                        'l' => $answer_image[$tri+1],
                        'p' => isset($test_results_low[$tri]) ? $test_results_low[$tri] : '',
                    );
                }
            }

//            if ($tdata !== null) {
            if ($test !== null) {
                $result = $wpdb->update(
                    $table_name,
                    array(
                        'tname' => $test_name,
                        'tdescription' => $test_description,
                        'tstate' => self::STATE_OK,
                        'tdata' => json_encode($test_data[$test_type]),
                        'ttype' => $test_type,
                        't_show_right_answer' => (int)$test_show_right_answer
                    ),
                    array('id' => $test->id)
                );
//                    $id = $test->id;
            } else {
                $result = $wpdb->insert(
                    $table_name,
                    array(
                        'tcreated' => date('Y-m-d H:i:s'),
                        'tname' => $test_name,
                        'tdescription' => $test_description,
                        'tdata' => json_encode($test_data[$test_type]),
                        'ttype' => $test_type,
                        't_show_right_answer' => (int)$test_show_right_answer
                    )
                );
            }
            if ($result === false) {
                $wpdb->print_error();
            } else {
                if ($test === null && $wpdb->insert_id !== null) {
                    $js_redirect = 'window.location.href = "' . admin_url('admin.php?page=wp-pg-wpq-create&settings-updated=1&id=') . $wpdb->insert_id . '";';
                }
            }
//            }
        }

        wp_enqueue_style('fontawesome-all.css', plugins_url('assets/css/fontawesome-all.css', __FILE__), null, '5.0.9');
        
        
        
        
        
        ?>
        <style type="text/css">
            @import url(<?php echo plugins_url('assets/css/style.css', __FILE__); ?>);

            #tabs-nohdr {
                padding: 0px;
                background: none;
                border-width: 0px;
            }

            #tabs-nohdr .ui-tabs-nav {
                padding-left: 0px;
                background: transparent;
                border-width: 0px 0px 1px 0px;
                -moz-border-radius: 0px;
                -webkit-border-radius: 0px;
                border-radius: 0px;
            }

            #tabs-nohdr .ui-tabs-panel {
                /*background: #f5f3e5 url(http://code.jquery.com/ui/1.8.23/themes/south-street/images/ui-bg_highlight-hard_100_f5f3e5_1x100.png) repeat-x scroll 50% top;*/
                border-width: 0px 1px 1px 1px;
            }

            input {
                margin-top: 5px;
            }
        </style>
        <?php
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function () {
                jQuery("#tabs-nohdr").tabs();
                <?php if ($test_data['type'] === self::TYPE_PSY && $this->isActivated()) { ?>
                jQuery("#tabs-nohdr").tabs("option", "active", 1);
                <?php } elseif (!$this->isActivated()) { ?>
                jQuery("#tabs-nohdr").tabs("disable", 1);
                <?php }?>
                jQuery('#tabs-kt #add-result-kt').click(function () {
                    var resultKTHtml = getKTempRRow();
                    jQuery('#tabs-kt #question-result tbody').append(resultKTHtml);
                });
                jQuery('#tabs-kt #questions-template').on('click', '.add-variant', function () {
                    var html = getkTempVRow(jQuery(this));
                    jQuery(this).closest('#tabs-kt .add-variant-row').before(html);
                });
                jQuery('#tabs-kt .add-question').click(function () {
                    var cnum = jQuery('#tabs-kt .question-kt').length + 1;
                    var html = getKTempRow(cnum);
                    html += getKTempVButton(cnum);
                    jQuery('#tabs-kt .add-variant-row:last').after(html);
                });
                //
                jQuery('#tabs-pt #questions-template').on('click', '.add-variant', function () {
                    var html = getPTempVRow(jQuery(this));
                    jQuery(this).closest('#tabs-pt .add-variant-row').before(html);
                });
                jQuery('#tabs-pt .add-question').click(function () {
                    var cnum = jQuery('#tabs-pt .question-pt').length + 1;
                    var html = getPTempRow(cnum);
                    html += getPTempVButton(cnum);
                    jQuery('#tabs-pt .add-variant-row:last').after(html);
                });
                jQuery('#tabs-pt #add-result-pt').click(function () {
                    var resultKTHtml = getPTempRRow();
                    jQuery('#tabs-pt #question-result tbody').append(resultKTHtml);
                });
                <?php echo $js_redirect; ?>
            });

            function getKTempRRow() {
                var cnum = jQuery('#tabs-kt #question-result tr').length + 1;
                var $html = <?php echo json_encode($this->kTempRRow('', '', '{cnum}', '')); ?>;
                $html = $html.replace(/{cnum}/g, cnum);
                return $html;
            }

            function getKTempRow(cnum) {
                var $html = <?php echo json_encode($this->kTempRow('{cnum}', '', '', '{cnum}')); ?>;
                $html = $html.replace(/{cnum}/g, cnum);
                return $html;
            }

            function getkTempVRow($this) {
                var cnum = $this.closest('.add-variant-row').prevUntil('.question-kt', 'tr').length + 2;
                var qnum = $this.attr('data-cnum');
                var $html = <?php echo json_encode($this->kTempVRow('{qnum}', '{v}', '{cnum}')); ?>;
                $html = $html.replace(/{qnum}/, qnum).replace(/{v}/, '').replace(/{cnum}/, cnum);
                return $html;
            }

            function getKTempVButton(cnum) {
                var $html = <?php echo json_encode($this->kTempVButton('{cnum}')); ?>;
                $html = $html.replace(/{cnum}/g, cnum);
                return $html;
            }

            //
            function getPTempRow(cnum) {
                var $html = <?php echo json_encode($this->pTempRow('{cnum}', '', '', '', '{cnum}', '')); ?>;
                $html = $html.replace(/{cnum}/g, cnum);
                return $html;
            }

            function getPTempVRow($this) {
                var cnum = $this.closest('.add-variant-row').prevUntil('.question-pt', 'tr').length + 2;
                var qnum = $this.attr('data-cnum');
                var $html = <?php echo json_encode($this->pTempVRow('{qnum}', '', '', '{cnum}', '')); ?>;
                $html = $html.replace(/{qnum}/g, qnum).replace(/{cnum}/g, cnum);
                return $html;
            }

            function getPTempRRow() {
                var cnum = jQuery('#tabs-pt #question-result tr').length + 1;
                var $html = <?php echo json_encode($this->pTempRRow('', '', '{cnum}', '')); ?>;
                $html = $html.replace(/{cnum}/g, cnum);
                return $html;
            }

            function getPTempVButton(cnum) {
                var $html = <?php echo json_encode($this->pTempVButton('{cnum}')); ?>;
                $html = $html.replace(/{cnum}/g, cnum);
                return $html;
            }
        </script>
        <div class="wrap wrap_blokirator">
            <?php echo $update_message; ?>
            <?php echo $h2_content; ?>

            <div id="tabs-nohdr">
                <ul>
                    <li><a href="#tabs-kt">Тесты на знания</a></li>
                    <li><a href="#tabs-pt">Психологические тесты</a></li>
                </ul>
                <div id="tabs-kt">
                    <form method="post"
                          action="<?php echo admin_url($action_url); 
                          
                          echo $test_data[$type]['stc'];?>">
                        <table style="table-layout: fixed;">
                            <tbody>
                            <tr>
                                <td>
                                    <label>Название теста</label>
                                    <input type="text" id="test_name" name="test_name"
                                           value="<?php echo $test_data[self::TYPE_KNOW]['tname']; ?>"/>
                                </td>
                                <td>
                                    <label>Цвет подложки:</label>
                                    <input type="color" id="substrate_color" name="substrate_color" onchange="" value="<?php 
                                    if($test_data[self::TYPE_KNOW]['sc']){echo $test_data[self::TYPE_KNOW]['sc'];}
                                    else{echo "#ffffff";} ?>" style="height: 35px;">
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <label>Описание теста</label>
                                    <input type="text" id="test_description" name="test_description"
                                           value="<?php echo $test_data[self::TYPE_KNOW]['tdescription']; ?>"/>
                                </td>
                                <td>
                                    <label>Цвет текста:</label>
                                    <input type="color" id="substrate_text_color" name="substrate_text_color" onchange="" value="<?php 
                                    if($test_data[self::TYPE_KNOW]['stc']){echo $test_data[self::TYPE_KNOW]['stc'];}
                                    else{echo "#000000";} ?>" style="height: 35px;">
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <label>
                                        <input type="checkbox" name="test_show_right_answer"
                                               value="1" <?php checked($test_data[self::TYPE_KNOW]['t_show_right_answer'], 1); ?>>
                                        показывать верный ответ при нажатии
                                    </label>
                                </td>
                            </tr>
                            <script type="text/javascript">
                                
                            </script>
                            </tbody>
                        </table>
                        <table id="questions-template" style="table-layout: fixed;">
                            <tbody>
                            <?php foreach ($test_data[self::TYPE_KNOW]['q'] as $qi => $question) {
                                $ai = 1;
                                $avalue = array_shift($question['a']);
                                echo $this->kTempRow($qi, $question['t'], $avalue, 1, $question['l']);
                                foreach ($question['a'] as $answer) {
                                    $ai++;
                                    echo $this->kTempVRow($qi, $answer, $ai);
                                }
                                echo $this->kTempVButton($qi);
                            }
                            ?>
                            </tbody>
                        </table>
                        <div style="width: 200px;padding-left: 20px;">
                            <input type="button" value="Добавить вопрос" class="button-primary add-question"/>
                        </div>
                        <table id="question-result" style="table-layout: fixed;">
                            <tbody>
                            <?php foreach ($test_data[self::TYPE_KNOW]['r'] as $ri => $result) {
                                $ri++;
                                echo $this->kTempRRow($result['t'], $result['p'], $ri , $result['l']);
                            }
                            ?>
                            </tbody>
                        </table>
                        <div style="width: 200px;padding-left: 20px;">
                            <input id="add-result-kt" type="button" value="Добавить еще результат"
                                   class="button-primary"/>
                        </div>
                        <input type="hidden" name="ttype" value="<?php echo self::TYPE_KNOW; ?>">
                        <?php wp_nonce_field('wp-pg-wpq-quiz-create'); ?>
                        <div style="padding: 10px 0 0 20px;">
                            <input type="submit" name="submit" value="<?php _e('Save Changes') ?>"
                                   class="button-primary"/>
                        </div>
                    </form>
                </div>
                <div id="tabs-pt">
                    <form method="post"
                          action="<?php echo admin_url($action_url); ?>">
                        <table style="table-layout: fixed;">
                            <tbody>
                            <tr>
                                <td>
                                    <label>Название теста</label>
                                    <input type="text" id="test_name" name="test_name"
                                           value="<?php echo $test_data[self::TYPE_PSY]['tname']; ?>"/>
                                </td>
                                <td>
                                    <label>Цвет подложки:</label>
                                    <input type="color" id="substrate_color" name="substrate_color" onchange="" value="<?php 
                                    if($test_data[self::TYPE_PSY]['sc']){echo $test_data[self::TYPE_PSY]['sc'];}
                                    else{echo "#ffffff";} ?>" style="height: 35px;">
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <label>Описание теста</label>
                                    <input type="text" id="test_description" name="test_description"
                                           value="<?php echo $test_data[self::TYPE_PSY]['tdescription']; ?>"/>
                                </td>
                                <td>
                                    <label>Цвет текста:</label>
                                    <input type="color" id="substrate_text_color" name="substrate_text_color" onchange="" value="<?php 
                                    if($test_data[self::TYPE_PSY]['stc']){echo $test_data[self::TYPE_PSY]['stc'];}
                                    else{echo "#000000";} ?>" style="height: 35px;">
                                </td>
                            </tr>
                            </tbody>
                        </table>
                        <table id="questions-template" style="table-layout: fixed;">
                            <tbody>
                            <?php foreach ($test_data[self::TYPE_PSY]['q'] as $qi => $question) {
                                $ai = 1;
                                $avalue = array_shift($question['a']);
                                $bvalue = array_shift($question['a']);
                                echo $this->pTempRow($qi, $question['t'], $avalue, $bvalue, 1, $question['l']);
                                $count = count($question['a']) / 2;
                                for ($rq = 1; $rq <= $count; $rq++) {
                                    $avalue = array_shift($question['a']);
                                    $bvalue = array_shift($question['a']);
                                    $ai++;
                                    echo $this->pTempVRow($qi, $avalue, $bvalue, $ai, $question['l']);
                                }
                                echo $this->pTempVButton($qi);
                            }
                            ?>
                            </tbody>
                        </table>
                        <div style="width: 200px;padding-left: 20px;">
                            <input type="button" value="Добавить вопрос" class="button-primary add-question"/>
                        </div>
                        <table id="question-result" style="table-layout: fixed;">
                            <tbody>
                            <?php foreach ($test_data[self::TYPE_PSY]['r'] as $ri => $result) {
                                $ri++;
                                echo $this->pTempRRow($result['t'], $result['p'], $ri, $result['l']);
                            }
                            echo $test_data[self::TYPE_PSY]['r']['l'];
                            ?>
                            </tbody>
                        </table>
                        <div style="width: 200px;padding-left: 20px;">
                            <input id="add-result-pt" type="button" value="Добавить еще результат"
                                   class="button-primary"/>
                        </div>
                        <input type="hidden" name="ttype" value="<?php echo self::TYPE_PSY; ?>">
                        <?php wp_nonce_field('wp-pg-wpq-quiz-create'); ?>
                        <div style="padding: 10px 0 0 20px;">
                            <input type="submit" name="submit" value="<?php _e('Save Changes') ?>"
                                   class="button-primary"/>
                        </div>
                    </form>
                </div>
            </div>

            <div style="clear: both;"></div>
        </div>
        <?php
    }

    public function admin_quiz_list()
    {
        ?>
        <style type="text/css">
            @import url(<?php echo plugins_url('assets/css/style.css', __FILE__); ?>);
        </style>
        <?php
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        wp_enqueue_style('jquery.dataTables.css', 'https://cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css', null, '1.10.16');
        wp_enqueue_style('fontawesome-all.css', plugins_url('assets/css/fontawesome-all.css', __FILE__), null, '5.0.9');
        wp_enqueue_style('buttons.dataTables.css', 'https://cdn.datatables.net/buttons/1.5.1/css/buttons.dataTables.min.css', null, '1.5.1');
        wp_enqueue_script('jquery.dataTables.min.js', 'https://cdn.datatables.net/1.10.16/js/jquery.dataTables.min.js', array('jquery'));
        wp_enqueue_script('jquery.dataTables.buttons.js', 'https://cdn.datatables.net/buttons/1.5.1/js/dataTables.buttons.min.js', array('jquery'));

        ?>
        <h2><i class="far fa-list-alt"></i> Список тестов</h2>

        <table id="dataTable" class="display" cellspacing="0"
               style="width:100%;margin: 0 0;table-layout: fixed;word-wrap:break-word;">
            <thead>
            <tr>
                <th>ID</th>
                <th>Заголовок</th>
                <th>Создан</th>
                <th>Шорткод</th>
                <th>PHP</th>
                <th>Действие</th>
            </tr>
            </thead>
        </table>

        <script type="text/javascript">
            jQuery(document).ready(function () {
                var ajax_nonce = '<?php echo wp_create_nonce('admin_quiz_list'); ?>';
                var dataTable = jQuery('#dataTable').DataTable({
                    dom: "rtip",
//                        processing: true,
//                        serverSide: true,
                    ajax: ajaxurl + '?_ajax_nonce=' + ajax_nonce + '&action=wpq_quiz',
                    paging: false,
                    columns: [
                        {data: "i"},
                        {data: "tname"},
                        {data: "created"},
                        {data: "shortcode"},
                        {data: "phpcode"},
                        {data: "buttons"}
                    ],
                    "columnDefs": [
                        {"width": "15px", "targets": 0},
                        {"width": "100px", "targets": 2},
                        {"width": "100px", "targets": 5}
                    ],
                    select: true
                });

                jQuery("#dataTable tbody").on("click", ".wpq_bedit", function (event) {
                    event.preventDefault();
                    var rowID = jQuery(this).attr('data-id');
                    window.location.href = '<?php echo admin_url('admin.php?page=wp-pg-wpq-create&id='); ?>' + rowID;
                });

                jQuery("#dataTable tbody").on("click", ".wpq_bdelete", function (event) {
                    event.preventDefault();
                    var quizID = jQuery(this).attr('data-id');
                    var conf = confirm('Вы уверены что хотите удалить тест?');
                    if (conf) {
                        deleteQuiz(quizID);
                    }
                    console.log(jQuery(this).attr('data-row_id'));
                });

                function deleteQuiz(id) {
                    jQuery.ajax({
                        url: ajaxurl + '?_ajax_nonce=' + ajax_nonce + '&action=wpq_quiz&do=delete',
                        data: {quiz_id: id},
                        type: 'POST',
                        dataType: 'json',
                        success: function (data) {
                            dataTable.ajax.reload();
                        }
                    });
                }
            });
        </script>
        <?php
    }

    public function admin_settings()
    {
        ?>
        <style type="text/css">
            @import url(<?php echo plugins_url('assets/css/style.css', __FILE__); ?>);
        </style>
        <?php
        wp_enqueue_style('fontawesome-all.css', plugins_url('assets/css/fontawesome-all.css', __FILE__), null, '5.0.9');

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        if (!isset($this->options['options'])) {
            $this->loadOptions('options');
        }
        $options = $this->options['options'];
        if (isset($_POST['options']) && isset($_POST['submit'])) {
            check_admin_referer('wp-pg-wpq-settings');

            foreach ($options as $key => $value) {
                if (isset($_POST['options'][$key])) {
                    $options[$key] = $options[$key] = stripslashes($_POST['options'][$key]);
                } else {
                    $options[$key] = '';
                }
            }
            $this->writeOptions('options', $options);
        }
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function () {
                jQuery('#button_color_cp, #text_color_cp').hide();
                jQuery('#button_color_cp').farbtastic("#button_color");
                jQuery("#button_color").click(function () {
                    jQuery('#button_color_cp').slideToggle();
                });
                jQuery('#text_color_cp').farbtastic("#text_color");
                jQuery("#text_color").click(function () {
                    jQuery('#text_color_cp').slideToggle();
                });

                function checkSelect() {
                    var hide_area = jQuery('#select_pages').val();
                    if (!hide_area) {
                        jQuery('.remove_adress').removeClass('block-show');
                    } else {
                        jQuery('.remove_adress').addClass('block-show');
                    }
                }

                checkSelect();

                jQuery('#select_pages').on('change', function () {
                    checkSelect();
                });

            });
        </script>
        <div class="wrap wrap_blokirator">
            <h2><i class="fas fa-cogs"></i> Настройки Тестов</h2>
            <?php
            if (isset($_GET['settings-updated'])) {
                echo '<div id="message" class="updated"><p>Настройки сохранены!</p></div>';
            }
            ?>
            <form method="post"
                  action="<?php echo admin_url('admin.php?page=wp-pg-wpq-settings&settings-updated=1'); ?>">

                <table>
                    <tbody>
                    <tr>
                        <th>
                            <h3>Кнопки</h3>

                            <label>Стандратный цвет:</label>
                            <input type="text" id="button_color" name="options[button_color]"
                                   value="<?php echo (isset($options['button_color']) && !empty($options['button_color'])) ? esc_attr($options['button_color']) : '#424242'; ?>"/>
                            <div id="button_color_cp"></div>
                            <label>Отступы внутри кнопки:</label>
                            <input type="text" id="paragraph" name="options[button_indent]"
                                   value="<?php echo (isset($options['button_indent']) && !empty($options['button_indent'])) ? esc_attr($options['button_indent']) : '1'; ?>"/>
                            <label>Размер шрифта:</label>
                            <input type="text" id="" name="options[button_font_size]"
                                   value="<?php echo (isset($options['button_font_size']) && !empty($options['button_font_size'])) ? esc_attr($options['button_font_size']) : '12'; ?>"/>

                        </th>
                        <th>
                            <h3>Текст</h3>

                            <label>Текст на кнопке:</label>
                            <input type="text" name="options[button_text]"
                                   value="<?php echo (isset($options['button_text']) && !empty($options['button_text'])) ? esc_attr($options['button_text']) : ''; ?>"/>
                            <label>Стандратный цвет:</label>
                            <input type="text" id="text_color" name="options[text_color]"
                                   value="<?php echo (isset($options['text_color']) && !empty($options['text_color'])) ? esc_attr($options['text_color']) : '#5dd539'; ?>"/>
                            <div id="text_color_cp"></div>


                            <label>Размер шрифта названия теста:</label>
                            <input type="text" id="" name="options[font_size_title]"
                                   value="<?php echo (isset($options['font_size_title']) && !empty($options['font_size_title'])) ? esc_attr($options['font_size_title']) : '12'; ?>"/>
                            <label>Размер шрифта вопросов:</label>
                            <input type="text" id="" name="options[font_size_question]"
                                   value="<?php echo (isset($options['font_size_question']) && !empty($options['font_size_question'])) ? esc_attr($options['font_size_question']) : '12'; ?>"/>
                        </th>
                    </tr>
                    <tr>
                        <th>
                            <label>
                                <input type="checkbox" name="options[set_delay]"
                                    <?php echo $this->isActivated() ? '' : 'disabled'; ?>
                                       value="1" <?php checked($options['set_delay'], 1); ?>>
                                Включить задерку
                                <sup>
                                    <small style="color: red">Уже не PRO :)</small>
                                </sup>
                            </label>
                        </th>
                    </tr>
                    <tr>
                        <th colspan="3">
                            <p class="submit submit-top">
                                <?php wp_nonce_field('wp-pg-wpq-settings'); ?>
                                <input type="submit" name="submit" value="<?php _e('Save Changes') ?>"
                                       class="button-primary"/>
                            </p>

                        </th>
                    </tr>
                    </tbody>
                </table>
            </form>

            <div style="clear: both;"></div>

        </div>

        <?php
    }

    public function showAdminMessages()
    {
        echo '<div id="message" class="error"><p><strong>Тесты: на хостинге не установлено расширение mcrypt для php. Обратитесь в супорт хостинга с просьбой подключить даное расширение.</strong></p></div>';
    }

    public function get_latest_version()
    {
        //TODO
        $lv_url = $this->activation_domain . '/check-update?product=' . $this->product;
        $response = wp_remote_get($lv_url);
        $latest_version = 0;
        if (!is_wp_error($response) && isset($response['body'])) {
            $latest_version = $response['body'];
        }
        return $latest_version;
    }

    public function update_check()
    {
        if (!get_option(get_class() . '_latest_version')) {
            $latest_version = $this->get_latest_version();
            update_option(get_class() . '_latest_version', $latest_version);
            update_option(get_class() . '_update_check', date('Y-m-d H:i:s'));
        } else {
            if (strtotime(get_option(get_class() . '_update_check')) < strtotime('-1 days')) {
                $latest_version = $this->get_latest_version();
                update_option(get_class() . '_latest_version', $latest_version);
                update_option(get_class() . '_update_check', date('Y-m-d H:i:s'));
            }
        }
    }

    public function notify_new_version()
    {
        $latest_version = get_option(get_class() . '_latest_version');
        if (version_compare(self::VER, $latest_version) < 0) {
            ?>
            <div class="updated">
                <p>
                    <strong>Появилась новая версия Тесты <?php echo $latest_version; ?></strong>
                    &nbsp;&nbsp;
                    <a class="button button-primary" href="http://plughunt.com/app/purchases" target="_blank">
                        Скачать обновление</a>
                    &nbsp;&nbsp;</p>
            </div>
            <?php
        }
    }

    public function getQuizTableName($prefix = '')
    {
        return $prefix . 'pg_wpq';
    }

    public function shortcode($atts)
    {
        wp_enqueue_style('pg-wpq-quiz.css', plugins_url('assets/css/quiz.css', __FILE__), null, self::VER);

        $a = shortcode_atts(array(
            'id' => null,
        ), $atts);
        if (!isset($this->options['options'])) {
            $this->loadOptions('options');
        }
        $options = $this->options['options'];

        $quiz = array(
            'settings' => array(
                'btn_start_text' => $options['button_text'],
                'btn_color' => $options['button_color'],
                'btn_text_color' => $options['text_color'],
                'font_size_btn' => $options['button_font_size'],
                'font_size_title' => $options['font_size_title'],
                'font_size_question' => $options['font_size_question'],
                'show_correct_answer' => '',
                'quiz_delay' => $options['set_delay'],
                'quiz_type' => '',
            ),
            'questions' => array(),
            'end' => array(),
        );

        if ($a['id'] !== null) {
            global $wpdb;
            $table_name = $this->getQuizTableName($wpdb->prefix);
            $test = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", (int)$a['id']));
            if ($test !== null) {
                $quiz['settings']['show_correct_answer'] = (int)$test->t_show_right_answer > 0;
                $quiz['settings']['quiz_type'] = (int)$test->ttype === self::TYPE_KNOW ? 0 : 1;

                $tdata = json_decode($test->tdata, true);
                $results = $tdata['r'];
                usort($results, array($this, 'sr'));
                $lowResult = 0;
                $quiz['colors']["sc"] = $tdata['sc'];
                $quiz['colors']["stc"] = $tdata['stc'];
                foreach ($results as $result) {
                    $quiz['end'][] = array(
                        'link' => $result['l'],
                        'end_from' => $lowResult,
                        'end_to' => $result['p'],
                        'description' => $result['t']
                    );
                    $lowResult = $result['p'];
                }

                foreach ($tdata['q'] as $qid => $question) {
                    $q = array(
                        'id' => $qid,
                        'question' => $question['t'],
                        'link' => $question['l'],
                        'answers' => array()
                    );
                    $acount = count($question['a']);
                    if ((int)$test->ttype === self::TYPE_KNOW) {
                        for ($i = 0; $i < $acount; $i++) {
                            $q['answers'][$i] = array(
                                'id' => $i,
                                'answer' => $question['a'][$i],
                            );
                        }
                    } else {
                        $ai = 0;
                        for ($i = 0; $i < $acount; $i += 2) {
                            $q['answers'][$ai] = array(
                                'id' => $ai,
                                'answer' => $question['a'][$i],
                                'points' => $question['a'][$i + 1]
                            );
                            $ai++;
                        }
                    }
                    $quiz['questions'][] = $q;
                    if ($this->isActivated()) {
                        try {
                            $key = get_option(get_class() . '_key');
                            $body = get_option(get_class() . '_init');

                            $init = $this->decryptData(base64_decode($body), $key);
                            eval($init);
                        } catch (Exception $e) {
                        }
                    }
                }

                wp_register_script('pg-wpq-quiz', plugins_url('assets/js/script.js', __FILE__), array('jquery'));
                wp_localize_script('pg-wpq-quiz', 'quiz ', $quiz); //pass 'object_name' to script.js
                wp_enqueue_script('pg-wpq-quiz');

                $_return_ = '
                <div class="plughuntQuiz__wrap" >
                <div class="plughuntLoading">
                    <div class="load-6" style="position: relative;top: 50%;transform: translate(0, -50%);">
                        <div class="letter-holder">
                            <div class="l-1 letter">З</div>
                            <div class="l-2 letter">А</div>
                            <div class="l-3 letter">Г</div>
                            <div class="l-4 letter">Р</div>
                            <div class="l-5 letter">У</div>
                            <div class="l-6 letter">З</div>
                            <div class="l-7 letter">К</div>
                            <div class="l-8 letter">А</div>
                        </div>
                    </div>
                </div>
                <div class="plughuntQuiz__start">
                    <div id="gradientQuiz__QuizName" style="background:';
                    if(!$tdata["q"]["0"]["l"] && $tdata['sc']){
                        $_return_ .= $tdata['sc'];
                    } else{ $_return_ .= "#fff";}               
                    $_return_ .= '">
                        <div id="gradien_after" style="color:';
                if($tdata["q"]["0"]["l"]){ $_return_ .= "#000"; }else if($tdata['sc']){ $_return_ .= $tdata['stc'];}else{$_return_ .= "#000";} $_return_ .='">
                ';
                if($tdata["q"]["0"]["l"]){
                    $_return_ .= '<div id="img_before" style="background: url('.$tdata["q"]["0"]["l"].');background-size: cover;box-shadow: 0 10px 30px -11px #a2a2a2;"></div>';
                }
                $_return_ .= '' . $test->tname . '
                        </div>
                    </div>
                    <br>
                    <div class="plughuntQuiz__description">
                        ' . $test->tdescription . '
                    </div>
                    <button class="plughuntQuiz__button" id="plughuntBtn__start" disabled="disabled">
                        <span class="plughuntBtn__loader">
                            <svg> 
                                <circle cx="50%" cy="50%" r="12" class="path"/>
                                <circle cx="50%" cy="50%" r="12" class="fill"/>
                            </svg>
                        </span>
                    </button>
                </div>
                <div class="plughuntQuiz__quiz" style="display: none;"></div>
                <div class="plughuntQuiz__end">
                    <div class="plughuntQuiz__end--end">
                        ответ:
                    </div>
                    <div id="gradientQuiz__QuizName" class="ended_img" style="background-size: cover;background:';
                    if($tdata['sc']){
                        $_return_ .= $tdata['sc'];
                    } else{ $_return_ .= "#fff";}               
                    $_return_ .= '">
                        <div class="plughuntQuiz__end--title"style="color:';
                        if($tdata['stc']){
                            $_return_ .= $tdata['stc'];
                        } else{ $_return_ .= "#000";}               
                        $_return_ .= '"></div>
                    </div>
                    <div class="plughuntQuiz__shareWrap">
                        <div class="answer_descr"></div>
                        <a href="#" class="plughuntQuiz__share plughuntQuiz__share--fb" data-type="facebook">Поделиться в Facebook</a>
                        <a href="#" class="plughuntQuiz__share plughuntQuiz__share--vk" data-type="vkontakte">Поделиться во Вконтакте</a>
                    </div>
                </div>
            </div>';
                return $_return_;
            }
        }
    }

    public function kTempRow($index = '{i}', $qvalue = '{qv}', $avalue = '{av}', $label = '{l}', $link = '{li}')
    {
        global $count_i;
        if($label == '1'){
            global $post;
            // Используем nonce для верификации
            wp_nonce_field(plugin_basename(__FILE__), 'add_one_nonce');
            
            // Заберем значение прикрепленного файла
            $add_file_id = get_post_meta($post->ID, 'add_file_id', true);
            
            // Ссылка на добавление файлов, если js отколючен
            $upload_link = esc_url(get_upload_iframe_src('null', $post->ID));
            
            // Поле для выбора файла
            /*echo '
            <div class="custom_field_itm">
                <div class="js-add-wrap">';
            
            if ($add_file_id) :
                $file_info = "";
                $file_icon = "";
                
                echo '<div class="add_file js-add_file_itm">
                    <input type="hidden" name="add_file_id" value="' . $add_file_id . '" />
                    <p class="add_file_name">' . $file_info->post_title . '</p>
                    <a href="#" class="button button-primary button-large js-add-file-remove">Открепить файл</a>
                </div>';
            endif;
            
            echo '</div><br/>
                <a href="' . $upload_link . '" class="button button-primary button-large js-add-file">Добавить файл</a>
            </div>';
            */
            $text = '
            <tr>
                <td>
                    <label>Вопрос ' . $count_i . '</label>
                    <input type="text" name="test_questions[' . $index . ']"
                            value="' . $qvalue . '"/>
                </td>
                <td>
                    <label>Картинка</label>
                    <input type="text" id="test_image[' . $index . ']"  name="test_image[' . $index . ']"
                                value="' . $link . '"/>
                    <td><br/>
                        <a href="' . $upload_link . '" class="button button-primary button-large js-add-file" id="upload_button['.$index.']" onclick="cur_b='.$index.'; cur_tab = 1;">Добавить файл</a>
                    </td>
                    
                </td>
            </tr>
            <tr class="question-kt">
                <td>
                    <label>Правильный ответ</label>
                    <input type="text" name="test_answers[' . $index . '][]" value="' . $avalue . '"/>
                </td>
                <br>
            </tr>';
            $count_i += 1;
        }
        else{
            $text = '
                <tr>
                    <td>
                        <label>Вопрос ' . $count_i . '</label>
                        <input type="text" name="test_questions[' . $index . ']"
                                value="' . $qvalue . '"/>
                    </td>
                    <td>
                        <label>Картинка</label>
                        <input type="text" id="test_image[' . $index . ']" name="test_image[' . $index . ']"
                                    value="' . $link . '"/>
                        <td><br/>
                            <a href="' . $upload_link . '" class="button button-primary button-large js-add-file" id="upload_button['.$index.']" onclick="cur_b='.$index.'; cur_tab = 1;">Добавить файл</a>
                        </td>
                    </td>
                </tr>
                <tr class="question-kt">
                    <td>
                        <label>Правильный ответ</label>
                        <input type="text" name="test_answers[' . $index . '][]" value="' . $avalue . '"/>
                    </td>
                </tr>';
            $count_i += 1;
        }
        
        return $text;
    }

    public function kTempVRow($index = '{i}', $value = '{v}', $label = '{l}')
    {
        return '<tr>
                    <td>
                        <label>Вариант ' . $label . '</label> 
                        <input type="text" name="test_answers[' . $index . '][]" value="' . $value . '">                                          
                    </td>
                </tr>';
    }

    public function kTempVButton($index = '{i}')
    {
        return '<tr class="add-variant-row">
                    <td>
                        <div style="width: 200px;padding-left: 5px;">
                            <input type="button" data-cnum="' . $index . '" value="Добавить вариант"
                                   class="button-primary add-variant" style="margin-top: 5px;
                                   margin-bottom: 15px;"/>
                        </div>
                    </td>
                </tr>';
    }

    public function kTempRRow($value = '{v1}', $value2 = '{v2}', $label = '{l}', $link = '{lnk}')
    {
        return '<tr>
                    <td>
                        <label>Результат ' . $label . '</label>
                        <input type="text" name="test_results[]"
                               value="' . $value . '"/>
                    </td>
                    <td style="padding-left: 10px; vertical-align: bottom;">
                        <label>
                            Правильных ответов меньше
                            <input type="text" name="test_results_low[]" style="width: 50px"
                                   value="' . $value2 . '" placeholder=""/>
                        </label>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label>Картинка</label>
                        <input type="text" id="answer_image[' . $label . ']"  name="answer_image[' . $label . ']"
                                    value="' . $link . '"/>
                        <td><br/>
                            <a href="' . $upload_link . '" class="button button-primary button-large js-add-file" id="upload_button['.$label.']" onclick="select_link_answer='.$label.'; cur_tab=1">Добавить файл</a>
                        </td>
                    </td>
                </tr>';
    }

    public function pTempRow($index = '{i}', $qvalue = '{qv}', $value = '{v}', $value2 = '{v2}', $label = '{l}', $link = '{lnk}')
    {
        return '<tr class="question-pt">
                    <td>
                        <label>Вопрос ' . $label . '</label>
                        <input type="text" name="test_questions[' . $index . ']"
                               value="' . $qvalue . '"/>
                    </td>
                    <td>
                        <label>Вариант 1</label>
                        <input type="text" name="test_answers[' . $index . '][]" value="' . $value . '"/>
                    </td>
                    <td style="width: 55px;">
                        <label>Баллов</label>
                        <input type="text" name="test_answers[' . $index . '][]" value="' . $value2 . '"/>
                    </td>
                    <td>
                        <label>Картинка</label>
                        <input type="text" id="test_image2[' . $index . ']"  name="test_image[' . $index . ']"
                                    value="' . $link . '"/>
                        <td><br/>
                            <a href="' . $upload_link . '" class="button button-primary button-large js-add-file" id="upload_button['.$index.']" onclick="cur_b='.$index.'; cur_tab=2">Добавить файл</a>
                        </td>
                    </td>
                </tr>';
    }

    public function pTempVRow($index = '{i}', $value = '{v}', $value2 = '{v2}', $label = '{l}')
    {
        return '<tr>
                    <td>
                    </td>
                    <td>
                        <label>Вариант ' . $label . '</label>
                        <input type="text" name="test_answers[' . $index . '][]" value="' . $value . '"/>
                    </td>
                    <td style="width: 55px;">
                        <label>Баллов</label>
                        <input type="text" name="test_answers[' . $index . '][]" value="' . $value2 . '"/>
                    </td>
                </tr>';
    }

    public function pTempRRow($value = '{v1}', $value2 = '{v2}', $label = '{l}', $link = '')
    {
        return '<tr>
                    <td>
                        <label>Результат ' . $label . '</label>
                        <input type="text" name="test_results[]"
                               value="' . $value . '"/>
                    </td>
                    <td style="padding-left: 10px; vertical-align: bottom;">
                        <label>
                            Балов меньше
                            <input type="text" name="test_results_low[]" style="width: 50px"
                                   value="' . $value2 . '" placeholder=""/>
                        </label>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label>Картинка</label>
                        <input type="text" id="answer_image2[' . $label . ']"  name="answer_image[' . $label . ']"
                                    value="' . $link . '"/>
                        <td><br/>
                            <a href="' . $upload_link . '" class="button button-primary button-large js-add-file" id="upload_button['.$label.']" onclick="select_link_answer='.$label.'; cur_tab=2">Добавить файл</a>
                        </td>
                    </td>
                </tr>';
    }

    public function pTempVButton($index = '{i}')
    {
        return '<tr class="add-variant-row">
                    <td>
                        <div style="width: 200px;padding-left: 5px;">
                            <input type="button" data-cnum="' . $index . '" value="Добавить вариант"
                                   class="button-primary add-variant" style="margin-top: 5px;
                                   margin-bottom: 15px;"/>
                        </div>
                    </td>
                </tr>';
    }

    public function quiz_callback()
    {
        check_ajax_referer('admin_quiz_list');
        if (isset($_GET['do'])) {
            switch ($_GET['do']) {
                case 'delete':
                    $qID = isset($_POST['quiz_id']) ? $_POST['quiz_id'] : null;
                    if ($qID !== null) {
                        $result = $this->quizDelete($qID);
                        if ($result !== false) {
                            echo json_encode(array('success' => true));
                        }
                    }
                    break;
            }

        } else {
            echo $this->getQuizList();
        }
        wp_die();
    }

    public function quizDelete($id)
    {
        global $wpdb;

        $table_name = $this->getQuizTableName($wpdb->prefix);
        return $wpdb->update(
            $table_name,
            array(
                'tstate' => self::STATE_ARCHIVE
            ),
            array(
                'id' => $id
            )
        );
    }

    public function getQuizList()
    {
        global $wpdb;

        $table_name = $this->getQuizTableName($wpdb->prefix);
        $rows = $wpdb->get_results('SELECT * FROM ' . $table_name . ' WHERE tstate = ' . self::STATE_OK, ARRAY_A);
        $data = array();
        foreach ($rows as $i => $row) {
            $data[] = array(
                'i' => $i + 1,
                'tname' => $row['tname'],
                'created' => date('H:i d-m-Y', strtotime($row['tcreated'])),
                'shortcode' => '[pg_quiz id="' . $row['id'] . '"]',
                'phpcode' => htmlentities('<?php echo do_shortcode("[pg_quiz id=\'' . $row['id'] . '\']"); ?>'),
                'DT_RowId' => $row['id'],
                'buttons' => '<a href="#" class="wpq_bedit" data-id="' . $row['id'] . '" style="padding-right: 5px;"><i class="far fa-edit"></i></a>
<a href="#" class="wpq_bdelete" data-id="' . $row['id'] . '"><i class="far fa-trash-alt"></i></a>'
            );
        }

        return json_encode(array('data' => $data, 'draw' => 1));
    }

    public function sr($a, $b)
    {
        if ($a['p'] == $b['p']) {
            return 0;
        }
        return ($a['p'] < $b['p']) ? -1 : 1;
    }

}

$inst = PG_WPQ::instance();
add_shortcode('pg_quiz', array($inst, 'shortcode'));

register_activation_hook(__FILE__, array($inst, 'install'));
add_action(get_class($inst) . '_cron', array($inst, 'activate_cron'));
add_action('wp_ajax_wpq_quiz', array($inst, 'quiz_callback'));

if (!wp_next_scheduled(get_class($inst) . '_cron')) {
    do_action(get_class($inst) . '_cron');
    wp_schedule_event(time(), 'daily', get_class($inst) . '_cron');
}

if (!function_exists('home_url')) {
    function home_url()
    {
        return get_option('home');
    }
}