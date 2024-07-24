<?php
/*
Plugin Name: TGI WP AI Chatbot Plugin
Description: A WordPress plugin to add a floating ChatGPT chatbot icon.
Version: 1.0
Author: Zeeshan Ahmad
Author URI: https://tabsgi.com
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define the plugin version as a constant
if (!defined('TGI_WP_AI_CHATBOT_VERSION')) {
    define('TGI_WP_AI_CHATBOT_VERSION', '1.0.0'); // Replace '1.0.0' with your actual plugin version
}

add_action('plugins_loaded', 'tgi_wp_ai_chatbot_load_textdomain');
function tgi_wp_ai_chatbot_load_textdomain() {
    load_plugin_textdomain('tgi-wp-ai-chatbot-plugin', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}

// so you can use [tgi_chatgpt_icon] on any page
add_action('init', 'tgi_chatgpt_add_shortcode');
function tgi_chatgpt_add_shortcode() {
    add_shortcode('tgi_chatgpt_icon', array('TGI_WP_AI_Chatbot_Plugin', 'add_chat_icon_shortcode'));
}

class TGI_WP_AI_Chatbot_Plugin {

    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_menu', array($this, 'create_admin_menu'));
        add_action('wp_footer', array($this, 'add_chat_icon_global'));
        add_action('wp_ajax_tgi_chatgpt_send', array($this, 'handle_chat'));
        add_action('wp_ajax_nopriv_tgi_chatgpt_send', array($this, 'handle_chat'));
        add_action('wp_ajax_tgi_load_session', array($this, 'load_session'));
        add_action('wp_ajax_tgi_chatgpt_reset', array($this, 'reset_chat'));
        add_action('wp_ajax_clear_tgi_chat_logs', array($this, 'clear_logs'));
        register_activation_hook(__FILE__, array($this, 'create_db'));
    }

    public function enqueue_scripts() {
        wp_enqueue_style('tgi-wp-ai-chatbot-font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css');
        wp_enqueue_style('tgi-wp-ai-chatbot-style', plugin_dir_url(__FILE__) . 'css/style.css', array(), TGI_WP_AI_CHATBOT_VERSION);
        wp_enqueue_script('jquery-ui-draggable');
        wp_enqueue_script('marked-js', plugin_dir_url(__FILE__) . 'js/marked.min.js', array(), TGI_WP_AI_CHATBOT_VERSION, true);
        wp_enqueue_script('tgi-wp-ai-chatbot-script', plugin_dir_url(__FILE__) . 'js/script.js', array('jquery', 'jquery-ui-draggable', 'marked-js'), TGI_WP_AI_CHATBOT_VERSION, true);
        wp_localize_script('tgi-wp-ai-chatbot-script', 'tgi_chatgpt', array(
            'ajax_url' => admin_url('admin-ajax.php')
        ));
    }

    public function enqueue_admin_scripts($hook_suffix) {
        if ($hook_suffix === 'toplevel_page_tgi-wp-ai-chatbot') {
            wp_enqueue_script('tgi-chatgpt-admin-script', plugin_dir_url(__FILE__) . 'js/admin.js', array('jquery'), null, true);
            wp_localize_script('tgi-chatgpt-admin-script', 'tgi_chatgpt_admin', array(
                'nonce' => wp_create_nonce('clear_tgi_chat_logs_nonce')
            ));
        }
    }

    public function create_admin_menu() {
        add_menu_page('TGI WP AI Chatbot', 'AI Chatbot', 'manage_options', 'tgi-wp-ai-chatbot', array($this, 'admin_page'), 'dashicons-format-chat', 6);
        
        add_submenu_page(
            'tgi-wp-ai-chatbot',          // Parent slug
            'Settings',                   // Page title
            'Settings',                   // Menu title
            'manage_options',             // Capability
            'tgi-wp-ai-chatbot-settings', // Menu slug
            array($this, 'settings_page') // Callback function
        );
    }

    public function admin_page() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            check_admin_referer('tgi_chatgpt_nonce');
            // Update API Key
            if (isset($_POST['tgi_chatgpt_api_key'])) {
                update_option('tgi_chatgpt_api_key', sanitize_text_field($_POST['tgi_chatgpt_api_key']));
            }
    
            // Update Assistant ID
            if (isset($_POST['tgi_chatgpt_assistant_id'])) {
                update_option('tgi_chatgpt_assistant_id', sanitize_text_field($_POST['tgi_chatgpt_assistant_id']));
            }
    
            // Update ChatGPT Icon Enable Globally
            $icon_enable_globally = isset($_POST['tgi_chatgpt_icon_enable_globally']) ? '1' : '0';
            update_option('tgi_chatgpt_icon_enable_globally', $icon_enable_globally);
    
            echo '<div class="updated"><p>' . esc_html__('Changes were saved.', 'tgi-wp-ai-chatbot-plugin') . '</p></div>';
        }
    
        $api_key = get_option('tgi_chatgpt_api_key', '');
        $assistant_id = get_option('tgi_chatgpt_assistant_id', '');
        $icon_enable_globally = get_option('tgi_chatgpt_icon_enable_globally', '0');
    
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('TGI WP AI Chatbot Settings', 'tgi-wp-ai-chatbot-plugin')?></h1>
            <form method="post">
                <?php wp_nonce_field('tgi_chatgpt_nonce'); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('ChatGpt API Key', 'tgi-wp-ai-chatbot-plugin')?></th>
                        <td><input type="text" name="tgi_chatgpt_api_key" value="<?php echo esc_attr($api_key); ?>" size="50"/></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Assistant ID', 'tgi-wp-ai-chatbot-plugin')?></th>
                        <td><input type="text" name="tgi_chatgpt_assistant_id" value="<?php echo esc_attr($assistant_id); ?>" size="50"/></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Enable ChatGPT Icon Globally, you can use [tgi_chatgpt_icon] shortcode if not want to enable the chat globally', 'tgi-wp-ai-chatbot-plugin')?></th>
                        <td><input type="checkbox" name="tgi_chatgpt_icon_enable_globally" value="<?php echo $icon_enable_globally; ?>" <?php checked($icon_enable_globally, '1'); ?> /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <button id="clear-logs" class="button button-secondary"><?php esc_html_e('Clear All Chat and Error Logs', 'tgi-wp-ai-chatbot-plugin')?></button>
            <div id="clear-logs-message" style="margin-top: 20px;"></div>
            
            <h2><?php esc_html_e('Chat Records', 'tgi-wp-ai-chatbot-plugin')?></h2>
            <?php
            global $wpdb;
            $items_per_page = 10;
            $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
            $offset = ($current_page - 1) * $items_per_page;
    
            $total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tgi_chatgpt_chats");
            $total_pages = ceil($total_items / $items_per_page);
    
            $prepared_query = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}tgi_chatgpt_chats ORDER BY id DESC LIMIT %d OFFSET %d", $items_per_page, $offset);
            $chats = $wpdb->get_results($prepared_query);
    
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>ID</th><th>Session ID</th><th>Thread ID</th><th>User Message</th><th>Bot Response</th><th>Time</th></tr></thead>';
            echo '<tbody>';
    
            if ($chats) {
                foreach ($chats as $chat) {
                    echo '<tr>';
                    echo '<td>' . $chat->id . '</td>';
                    echo '<td>' . $chat->session_id . '</td>';
                    echo '<td>' . $chat->thread_id . '</td>';
                    echo '<td>' . esc_html($chat->user_message) . '</td>';
                    echo '<td>' . esc_html($chat->bot_response) . '</td>';
                    echo '<td>' . $chat->time . '</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="6">' . esc_html__('No chat records found.', 'tgi-wp-ai-chatbot-plugin') . '</td></tr>';
            }
    
            echo '</tbody></table>';
    
            // Pagination
            if ($total_pages > 1) {
                echo '<div class="tablenav"><div class="tablenav-pages">';
                echo paginate_links(array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo;', 'text-domain'),
                    'next_text' => __('&raquo;', 'text-domain'),
                    'total' => $total_pages,
                    'current' => $current_page,
                ));
                echo '</div></div>';
            }
            ?>
    
            <h2><?php esc_html_e('Error Logs', 'tgi-wp-ai-chatbot-plugin')?></h2>
            <?php
            $error_items_per_page = 3;
            $error_current_page = isset($_GET['error_paged']) ? max(1, intval($_GET['error_paged'])) : 1;
            $error_offset = ($error_current_page - 1) * $error_items_per_page;
            $error_total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tgi_chatgpt_error_logs");
            $error_total_pages = ceil($error_total_items / $error_items_per_page);
    
            $prepared_query = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}tgi_chatgpt_error_logs ORDER BY id DESC LIMIT %d OFFSET %d", $error_items_per_page, $error_offset);
            $errors = $wpdb->get_results($prepared_query);
    
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>ID</th><th>Error Message</th><th>Time</th></thead>';
            echo '<tbody>';
    
            if ($errors) {
                foreach ($errors as $error) {
                    echo '<tr>';
                    echo '<td>' . $error->id . '</td>';
                    echo '<td>' . $error->error_message . '</td>';
                    echo '<td>' . $error->time . '</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="3">' . esc_html__('No error logs found.', 'tgi-wp-ai-chatbot-plugin') . '</td></tr>';
            }
    
            echo '</tbody></table>';
    
            // Pagination
            if ($error_total_pages > 1) {
                echo '<div class="tablenav"><div class="tablenav-pages">';
                echo paginate_links(array(
                    'base' => add_query_arg('error_paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo;', 'text-domain'),
                    'next_text' => __('&raquo;', 'text-domain'),
                    'total' => $error_total_pages,
                    'current' => $error_current_page,
                ));
                echo '</div></div>';
            }
            ?>
        </div>
        <?php
    }    
    
    public function settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
    
        // Localization strings to handle
        $localization_fields = array(
            'tgi_wp_ai_chatbot_titles' => array(
                'en_US' => esc_html__('AI Assistant', 'tgi-wp-ai-chatbot-plugin'),
                'zh_CN' => esc_html__('AI助手', 'tgi-wp-ai-chatbot-plugin'),
                'zh_TW' => esc_html__('AI助手', 'tgi-wp-ai-chatbot-plugin')
            ),
            'tgi_wp_ai_chatbot_initial_messages' => array(
                'en_US' => esc_html__('Welcome to the AI Assistant!', 'tgi-wp-ai-chatbot-plugin'),
                'zh_CN' => esc_html__('欢迎使用AI助手！', 'tgi-wp-ai-chatbot-plugin'),
                'zh_TW' => esc_html__('歡迎使用AI助手！', 'tgi-wp-ai-chatbot-plugin')
            ),
            'tgi_wp_ai_chatbot_type_your_message' => array(
                'en_US' => esc_html__('Type your message...', 'tgi-wp-ai-chatbot-plugin'),
                'zh_CN' => esc_html__('请输入您的问题...', 'tgi-wp-ai-chatbot-plugin'),
                'zh_TW' => esc_html__('請輸入您的問題...', 'tgi-wp-ai-chatbot-plugin')
            ),
            'tgi_wp_ai_chatbot_reset' => array(
                'en_US' => esc_html__('Reset', 'tgi-wp-ai-chatbot-plugin'),
                'zh_CN' => esc_html__('重置', 'tgi-wp-ai-chatbot-plugin'),
                'zh_TW' => esc_html__('重置', 'tgi-wp-ai-chatbot-plugin')
            ),
            'tgi_wp_ai_chatbot_reset_confirm' => array(
                'en_US' => esc_html__('Are you sure you want to clear the chat? The chat will be deleted and lost forever.', 'tgi-wp-ai-chatbot-plugin'),
                'zh_CN' => esc_html__('您确定要清除聊天记录吗？聊天记录将被删除并且永久丢失。', 'tgi-wp-ai-chatbot-plugin'),
                'zh_TW' => esc_html__('您確定要清除聊天記錄嗎？聊天記錄將被刪除並且永久丟失。', 'tgi-wp-ai-chatbot-plugin')
            ),
            'tgi_wp_ai_chatbot_question_select_placeholder' => array(
                'en_US' => esc_html__('Question Examples', 'tgi-wp-ai-chatbot-plugin'),
                'zh_CN' => esc_html__('提问样例', 'tgi-wp-ai-chatbot-plugin'),
                'zh_TW' => esc_html__('提問樣例', 'tgi-wp-ai-chatbot-plugin')
            ),
            'tgi_wp_ai_chatbot_questions' => array(
                'en_US' => esc_html__('Who are you? Please use less than 10 words;What can you do? Please use less than 10 words.', 'tgi-wp-ai-chatbot-plugin'),
                'zh_CN' => esc_html__('你是谁？请用10字以内;你能做什么，请用10字以内。', 'tgi-wp-ai-chatbot-plugin'),
                'zh_TW' => esc_html__('你是誰？請用10字以内;你能做什麽，請用10字以内。', 'tgi-wp-ai-chatbot-plugin')
            )
        );
    
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // Handle form submission
            foreach ($localization_fields as $field_key => $default_values) {
                if (isset($_POST[$field_key])) {
                    check_admin_referer('tgi_wp_ai_chatbot_settings');
                    $values = array_map('sanitize_text_field', $_POST[$field_key]);
                    update_option($field_key, $values);
                }
            }

            // Handle rate limit form submission
            if (isset($_POST['tgi_wp_ai_chatbot_time_seconds'])) {
                check_admin_referer('tgi_wp_ai_chatbot_settings');
                $max_messages = intval($_POST['tgi_wp_ai_chatbot_max_messages']);
                $time_seconds = intval($_POST['tgi_wp_ai_chatbot_time_seconds']);
                update_option('tgi_wp_ai_chatbot_max_messages', $max_messages);
                update_option('tgi_wp_ai_chatbot_time_seconds', $time_seconds);
            }
    
            // Handle enable loading of previous chat messages, which will also enable msg clear button
            $load_previous_chat = isset($_POST['tgi_chatgpt_load_previous_chat']) ? '1' : '0';
            update_option('tgi_chatgpt_load_previous_chat', $load_previous_chat);
            
            echo '<div class="updated"><p>' . esc_html__('Changes were saved.', 'tgi-wp-ai-chatbot-plugin') . '</p></div>';
        }

        // Get existing messages or defaults
        $options = array();
        foreach ($localization_fields as $field_key => $default_values) {
            $options[$field_key] = get_option($field_key, $default_values);
        }
        
        // Get rate limit settings or defaults
        $max_messages = get_option('tgi_wp_ai_chatbot_max_messages', 4);
        $time_seconds = get_option('tgi_wp_ai_chatbot_time_seconds', 120);
        $load_previous_chat = get_option('tgi_chatgpt_load_previous_chat', '0');

        ?>
    
        <div class="wrap">
            <h2><?php esc_html_e('Translations', 'tgi-wp-ai-chatbot-plugin'); ?></h2>
            <form method="post" id="tgi_wp_ai_chatbot_form">
                <?php wp_nonce_field('tgi_wp_ai_chatbot_settings'); ?>
                <?php foreach ($options as $field_key => $values) : ?>
                    <h2><?php esc_html_e(ucwords(str_replace('_', ' ', str_replace('tgi_wp_ai_chatbot_', '', $field_key) )), 'tgi-wp-ai-chatbot-plugin'); ?></h2>
                    <table class="form-table" role="presentation" id="<?php echo esc_attr($field_key); ?>_table">
                        <?php foreach ($values as $locale => $message) : ?>
                        <tr>
                            <th scope="row"><label for="<?php echo esc_attr($field_key); ?>_<?php echo esc_attr($locale); ?>"><?php echo $locale; ?></label></th>
                            <td>
                                <input name="<?php echo esc_attr($field_key); ?>[<?php echo esc_attr($locale); ?>]" type="text" id="<?php echo esc_attr($field_key); ?>_<?php echo esc_attr($locale); ?>" value="<?php echo esc_attr($message); ?>" class="regular-text">
                                <button type="button" class="remove-row button"><?php esc_html_e('Remove', 'tgi-wp-ai-chatbot-plugin'); ?></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    <button type="button" class="add-row button" data-field-key="<?php echo esc_attr($field_key); ?>"><?php esc_html_e('Add Language', 'tgi-wp-ai-chatbot-plugin'); ?></button>
                <?php endforeach; ?>

                <h2><?php esc_html_e('Rate Limit Settings', 'tgi-wp-ai-chatbot-plugin'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="tgi_wp_ai_chatbot_max_messages"><?php esc_html_e('Max Messages', 'tgi-wp-ai-chatbot-plugin'); ?></label></th>
                        <td>
                            <input name="tgi_wp_ai_chatbot_max_messages" type="number" id="tgi_wp_ai_chatbot_max_messages" value="<?php echo esc_attr($max_messages); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tgi_wp_ai_chatbot_time_seconds"><?php esc_html_e('Time (seconds)', 'tgi-wp-ai-chatbot-plugin'); ?></label></th>
                        <td>
                            <input name="tgi_wp_ai_chatbot_time_seconds" type="number" id="tgi_wp_ai_chatbot_time_seconds" value="<?php echo esc_attr($time_seconds); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Others', 'tgi-wp-ai-chatbot-plugin'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Load previous chat when starts', 'tgi-wp-ai-chatbot-plugin')?></th>
                        <td><input type="checkbox" name="tgi_chatgpt_load_previous_chat" value="<?php echo $load_previous_chat; ?>" <?php checked($load_previous_chat, '1'); ?> /></td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <script>
            document.querySelectorAll('.add-row').forEach(function(button) {
                button.addEventListener('click', function() {
                    var newLocale = prompt("<?php esc_html_e('Enter the new locale code (e.g., en_US):', 'tgi-wp-ai-chatbot-plugin'); ?>");
                    if (newLocale) {
                        var fieldKey = this.getAttribute('data-field-key');
                        var table = document.getElementById(fieldKey + '_table');
                        var rowCount = table.rows.length;
                        var row = table.insertRow(rowCount);
                        var cell1 = row.insertCell(0);
                        var cell2 = row.insertCell(1);
                        cell1.innerHTML = '<label>' + newLocale + '</label>';
                        cell2.innerHTML = '<input name="' + fieldKey + '[' + newLocale + ']" type="text" id="' + fieldKey + '_' + newLocale + '" value="" class="regular-text"> <button type="button" class="remove-row button"><?php esc_html_e('Remove', 'tgi-wp-ai-chatbot-plugin'); ?></button>';
                    }
                });
            });
    
            document.querySelectorAll('.form-table').forEach(function(table) {
                table.addEventListener('click', function(e) {
                    if (e.target && e.target.className === 'remove-row button') {
                        var row = e.target.closest('tr');
                        row.parentNode.removeChild(row);
                    }
                });
            });
        </script>
        <?php
    }    

    public function clear_logs() {
        check_ajax_referer('clear_tgi_chat_logs_nonce', 'nonce');

        global $wpdb;
        $chat_table = $wpdb->prefix . 'tgi_chatgpt_chats';
        $error_log_table = $wpdb->prefix . 'tgi_chatgpt_error_logs';

        // get all unique thread ids form $chat_table
        $thread_ids = $wpdb->get_col("SELECT DISTINCT thread_id FROM $chat_table");

        // for each thread id, delete the thread
        if (!empty($thread_ids)) {
            foreach ($thread_ids as $thread_id) {
                $this->delete_thread($thread_id);
            }
        }

        $wpdb->query("TRUNCATE TABLE $chat_table");
        $wpdb->query("TRUNCATE TABLE $error_log_table");

        wp_send_json_success();
    }

    public static function add_chat_icon_shortcode() {
        $instance = new self();
        return $instance->add_chat_icon();
    }

    public function add_chat_icon_global() {
        $icon_enable_globally = get_option('tgi_chatgpt_icon_enable_globally', '0');
        if($icon_enable_globally == 1) {
            echo $this->add_chat_icon();
        }
    }

    private function get_locale_msg($key, $default) {
        $locale = determine_locale();
        $messages = get_option($key, array());
        return isset($messages[$locale]) ? $messages[$locale] : $default;
    }

    public function add_chat_icon() {
        $locale = determine_locale();
        $title = $this->get_locale_msg('tgi_wp_ai_chatbot_titles', 'AI Assistant');
        $initial_message = $this->get_locale_msg('tgi_wp_ai_chatbot_initial_messages', '');
        $type_your_message = $this->get_locale_msg('tgi_wp_ai_chatbot_type_your_message', '');
        $reset_message = $this->get_locale_msg('tgi_wp_ai_chatbot_reset', '');
        $questions = $this->get_locale_msg('tgi_wp_ai_chatbot_questions', '');
        $question_placeholder = $this->get_locale_msg('tgi_wp_ai_chatbot_question_select_placeholder', '');
        $reset_message_confirm = $this->get_locale_msg('tgi_wp_ai_chatbot_reset_confirm', '');

        $reset_btn_html = '<span><button id="tgi-chatgpt-reset">' . esc_html($reset_message) . '</button></span> <script>';
        $reset_btn_html .= 'window.reset_message_confirm = "'. $reset_message_confirm . '";';
        $reset_btn_html .= 'window.load_previous_chat = '. get_option('tgi_chatgpt_load_previous_chat', '0') . ';';
        $reset_btn_html .= '</script>';
        
        $question_html = '';
        if(!empty($questions)) {
            $questions = explode(';', $questions);
            $question_html .= '<span><select id="tgi-chatgpt-questions" name="tgi-chatgpt-questions" class="tgi-chatgpt-questions"><option selected value="">' . $question_placeholder . '</option>';
            foreach ($questions as $question) {
                $question_html .= '<option value="' . esc_attr($question) . '">' . esc_html($question) . '</option>';
            }
            $question_html .= '</select></span>';    
        }
    
        return '
        <div id="tgi-chatgpt-icon" title="AI"><i class="fas fa-comments"></i></div>
        <div id="tgi-chatgpt-modal" style="display: none;">
            <div class="tgi-chatgpt-modal-content">
                <div class="tgi-chat-header">
                    <span>' . esc_html($title) . '</span>
                    <span class="tgi-chatgpt-close-container"> '. $reset_btn_html . '<span class="tgi-chatgpt-close">&times;</span> </span>
                </div>
                <div class="tgi-chatgpt-messages">
                    <span>' . esc_html($initial_message) . '</span>
                </div>
                <div class="tgi-chatgpt-input-container">
                    <input type="text" id="tgi-chatgpt-input" placeholder="' . esc_attr($type_your_message) . '">
                    <button id="tgi-chatgpt-send">' . esc_html__('Send', 'tgi-wp-ai-chatbot-plugin') . '</button>
                </div>
                <div class="tgi-chatgpt-tools-container">' . $question_html . '</div>
            </div>
        </div>';
    }   

    private function add_message_to_thread($thread_id, $message) {
        $api_key = get_option('tgi_chatgpt_api_key');
        $api_url = "https://api.openai.com/v1/threads/$thread_id/messages";

        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'assistants=v2'
            ),
            'body' => json_encode(array(
                'role' => 'user',
                'content' => $message
            ))
        ));

        if (is_wp_error($response)) {
            $this->log_error($response->get_error_message());
            return false;
        } else {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            return isset($data['id']);
        }
    }

    private function run_assistant_on_thread($thread_id, $assistant_id) {
        $api_key = get_option('tgi_chatgpt_api_key');
        $api_url = "https://api.openai.com/v1/threads/$thread_id/runs";

        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'assistants=v2'
            ),
            'body' => json_encode(array(
                'assistant_id' => $assistant_id,
                'instructions' => ''  //Respond to the user query using the same language as the user inputs.
            )),
            'timeout' => 60 // Set timeout to 60 seconds
        ));

        if (is_wp_error($response)) {
            $this->log_error($response->get_error_message());
            return null;
        } else {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            if (isset($data['id'])) {
                $run_id = $data['id'];

                // Check the status of the run periodically
                for ($i = 0; $i < 5; $i++) { // Retry up to 5 times
                    sleep(3); // Wait for 3 seconds before retrying
                    $response = $this->get_assistant_response($thread_id, $run_id);
                    if ($response) {
                        return $response;
                    }
                }
                $this->log_error('Assistant run did not complete in time: ' . json_encode($data));
                return null;
            } else {
                $this->log_error('Failed to run assistant: ' . $body);
                return null;
            }
        }
    }

    private function get_assistant_response($thread_id, $run_id) {
        $api_key = get_option('tgi_chatgpt_api_key');
        $api_url = "https://api.openai.com/v1/threads/$thread_id/runs/$run_id";
    
        $max_retries = 10; // Maximum number of retries
        $retry_delay = 2; // Delay between retries in seconds
    
        for ($i = 0; $i < $max_retries; $i++) {
            $response = wp_remote_get($api_url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                    'OpenAI-Beta' => 'assistants=v2'
                )
            ));
    
            if (is_wp_error($response)) {
                $this->log_error($response->get_error_message());
                return null;
            } else {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
    
                if (isset($data['status'])) {
                    $status = $data['status'];
    
                    // Check the run status
                    if ($status === 'completed') {
                        // Fetch the latest messages from the thread
                        return $this->fetch_thread_messages($thread_id);
                    } elseif ($status === 'failed' || $status === 'expired' || $status === 'cancelled') {
                        $this->log_error("Run failed with status: $status");
                        return null;
                    }
                }
            }
    
            // Wait before retrying
            sleep($retry_delay);
        }
    
        return null; // Return null if no valid response is received after retries
    }
    
    private function fetch_thread_messages($thread_id) {
        $api_key = get_option('tgi_chatgpt_api_key');
        $api_url = "https://api.openai.com/v1/threads/$thread_id/messages";
    
        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'assistants=v2'
            )
        ));
    
        if (is_wp_error($response)) {
            $this->log_error($response->get_error_message());
            return null;
        } else {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
    
            if (isset($data['data'])) {
                $messages = $data['data'];
                foreach ($messages as $message) {
                    if ($message['role'] == 'assistant' && isset($message['content'])) {
                        return $message['content']; // Ensure we are returning the content
                    }
                }
            }
            return null;
        }
    }

    private function remove_annotation_links($text) {
        $pattern = '/【.*?】|\[.*?\]/';
        return preg_replace($pattern, '', $text);
    }

    private function create_new_thread() {
        $api_key = get_option('tgi_chatgpt_api_key');
        $api_url = "https://api.openai.com/v1/threads";
    
        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'assistants=v2'
            ),
            'body' => json_encode(array(
                'messages' => array(
                )
            ))
        ));
    
        if (is_wp_error($response)) {
            $this->log_error($response->get_error_message());
            return null;
        } else {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            return $data;
        }
    }

    private function delete_thread($thread_id) {
        $api_key = get_option('tgi_chatgpt_api_key');
        $api_url = "https://api.openai.com/v1/threads/{$thread_id}";

        $response = wp_remote_request($api_url, array(
            'method' => 'DELETE',
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'assistants=v2'
            )
        ));
        
        if (is_wp_error($response)) {
            $this->log_error($response->get_error_message());
            return false;
        }

        $response_body = wp_remote_retrieve_body($response);
        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code === 200) {
            return true;
        } else {
            $this->log_error("Failed to delete thread: " . $response_body);
            return false;
        }
    }

    private function get_thread_id_for_session($session_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tgi_chatgpt_chats';
        $result = $wpdb->get_row($wpdb->prepare("SELECT thread_id FROM $table_name WHERE session_id = %s", $session_id));
        return $result ? $result->thread_id : null;
    }
    
    private function check_rate_limit($session_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tgi_chatgpt_chats';

        $time_limit = get_option('tgi_wp_ai_chatbot_time_seconds');
        $max_requests = get_option('tgi_wp_ai_chatbot_max_messages');
    
        $time_threshold = date('Y-m-d H:i:s', strtotime("-$time_limit seconds"));
    
        $request_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE session_id = %s AND time > %s",
                $session_id, $time_threshold
            )
        );
    
        return $request_count < $max_requests;
    }

    public function handle_chat() {
        if (!isset($_POST['message']) || empty($_POST['message'])) {
            wp_send_json_error(__('No message provided', 'tgi-wp-ai-chatbot-plugin'));
        }

        if (!isset($_COOKIE['tgi_chatgpt_session_id'])) {
            $session_id = wp_generate_uuid4();
            //give the cookie a 24 hour expiration
            $arr_cookie_options = array (
                'expires' => time() + 3600*24, 
                'path' => '/', 
                'domain' => COOKIE_DOMAIN,
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict' // None || Lax  || Strict
                );
            setcookie('tgi_chatgpt_session_id', $session_id,  $arr_cookie_options);
        } else {
            $session_id = sanitize_text_field($_COOKIE['tgi_chatgpt_session_id']);

            if (!$this->check_rate_limit($session_id)) {
                wp_send_json_error(__('Rate limit exceeded. Please wait a while before sending more messages.', 'tgi-wp-ai-chatbot-plugin'));
                return;
            }
        }
        
        $message = sanitize_text_field($_POST['message']);
        $assistant_id = get_option('tgi_chatgpt_assistant_id');
        $thread_id = $this->get_thread_id_for_session($session_id);

        if (!$thread_id) {
            $data = $this->create_new_thread($assistant_id);
            $thread_id = isset($data['id']) ? $data['id'] : null;
            if (!$thread_id) {
                wp_send_json_error(__('Failed to create new thread', 'tgi-wp-ai-chatbot-plugin'));
            }
        }

        $message_added = $this->add_message_to_thread($thread_id, $message);
        if (!$message_added) {
            wp_send_json_error(__('Failed to add message to thread', 'tgi-wp-ai-chatbot-plugin'));
        }
    
        $response = $this->run_assistant_on_thread($thread_id, $assistant_id);
        if ($response) {
            // Parse the response to extract the actual message
            if (is_array($response)) {
                $response = array_map(function($item) {
                    return isset($item['text']['value']) ? $item['text']['value'] : json_encode($item);
                }, $response);
                $response = implode(" ", $response);
            }

            // cleanup response
            $response = $this->remove_annotation_links($response);

            global $wpdb;
            $table_name = $wpdb->prefix . 'tgi_chatgpt_chats';
            $wpdb->insert(
                $table_name,
                array(
                    'session_id' => $session_id,
                    'thread_id' => $thread_id,
                    'user_message' => $message,
                    'bot_response' => $response,
                    'time' => current_time('mysql')
                )
            );

            wp_send_json_success($response);
        } else {
            wp_send_json_error(__('Failed to get assistant response', 'tgi-wp-ai-chatbot-plugin'));
        }
    }

    public function reset_chat() {
        if (!isset($_COOKIE['tgi_chatgpt_session_id'])) {
            return;
        }
        
        $session_id = sanitize_text_field($_COOKIE['tgi_chatgpt_session_id']);
        if (empty($session_id)) {
            return;
        }

        $assistant_id = get_option('tgi_chatgpt_assistant_id');
        $thread_id = $this->get_thread_id_for_session($session_id);

        if (!$thread_id) {
            return;
        }

        $response = $this->delete_thread($thread_id, $assistant_id);
        if ($response) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'tgi_chatgpt_chats';
            $wpdb->delete(
                $table_name,
                array(
                    'session_id' => $session_id,
                ),
                array(
                    '%s' // Data type format (string in this case)
                )
            );

            wp_send_json_success($response);
        } else {
            wp_send_json_error(__('Failed to get assistant response', 'tgi-wp-ai-chatbot-plugin'));
        }
    }

    public function load_session() {
        if (!isset($_COOKIE['tgi_chatgpt_session_id'])) {
            return;
        }
        
        $session_id = sanitize_text_field($_COOKIE['tgi_chatgpt_session_id']);
        if (empty($session_id)) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'tgi_chatgpt_chats';

        #read all rows from table where session_id is $session_id
        $result = $wpdb->get_results($wpdb->prepare("SELECT user_message, bot_response FROM $table_name WHERE session_id = %s order by id", $session_id));
        wp_send_json_success($result);
    }

    private function log_error($message) {
        global $wpdb;
        $error_log_table = $wpdb->prefix . 'tgi_chatgpt_error_logs';
        $wpdb->insert(
            $error_log_table,
            array(
                'error_message' => sanitize_text_field($message),
                'time' => current_time('mysql')
            )
        );
    }

    public function create_db() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tgi_chatgpt_chats';
        $error_log_table = $wpdb->prefix . 'tgi_chatgpt_error_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            session_id varchar(255) NOT NULL,
            thread_id varchar(255) NOT NULL,
            user_message text NOT NULL,
            bot_response text NOT NULL,
            time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            INDEX (session_id),
            INDEX (thread_id)
        ) $charset_collate;

        CREATE TABLE $error_log_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            error_message text NOT NULL,
            time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

new TGI_WP_AI_Chatbot_Plugin();
?>
