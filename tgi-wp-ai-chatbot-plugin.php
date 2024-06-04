<?php
/*
Plugin Name: TGI WP AI Chatbot Plugin
Description: A WordPress plugin to add a floating ChatGPT chatbot icon.
Version: 1.0
Author: Zeeshan Ahmad
Author URI: https://tabsgi.com
Author Email : ziishanahmad@gmail.com
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class TGI_WP_AI_Chatbot_Plugin {

    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_menu', array($this, 'create_admin_menu'));
        add_action('wp_footer', array($this, 'add_chat_icon'));
        add_action('wp_ajax_tgi_chatgpt_send', array($this, 'handle_chat'));
        add_action('wp_ajax_nopriv_tgi_chatgpt_send', array($this, 'handle_chat'));
        register_activation_hook(__FILE__, array($this, 'create_db'));
    }

    public function enqueue_scripts() {
        wp_enqueue_style('tgi-wp-ai-chatbot-font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css');
        wp_enqueue_style('tgi-wp-ai-chatbot-style', plugin_dir_url(__FILE__) . 'css/style.css');
        wp_enqueue_script('tgi-wp-ai-chatbot-script', plugin_dir_url(__FILE__) . 'js/script.js', array('jquery'), null, true);
        wp_localize_script('tgi-wp-ai-chatbot-script', 'tgi_chatgpt', array(
            'ajax_url' => admin_url('admin-ajax.php')
        ));
    }

    public function create_admin_menu() {
        add_menu_page('TGI WP AI Chatbot', 'AI Chatbot', 'manage_options', 'tgi-wp-ai-chatbot', array($this, 'admin_page'), 'dashicons-format-chat', 6);
    }
    public function add_chat_icon() {
        echo '
        <div id="tgi-chatgpt-icon" title="Talk to our AI chatbot"><i class="fas fa-comments"></i></div>
        <div id="tgi-chatgpt-modal" style="display: none;">
            <div class="tgi-chatgpt-modal-content">
                <span class="tgi-chatgpt-close">&times;</span>
                <div class="tgi-chatgpt-messages"></div>
                <input type="text" id="tgi-chatgpt-input" placeholder="Type your message...">
                <button id="tgi-chatgpt-send">Send</button>
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
                'instructions' => 'Respond to the user query.'
            ))
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
                    $response = $this->get_assistant_response($thread_id);
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

    private function get_assistant_response($thread_id) {
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

    public function handle_chat() {
        if (!isset($_POST['message']) || empty($_POST['message'])) {
            wp_send_json_error('No message provided');
        }
    
        $message = sanitize_text_field($_POST['message']);
        $thread_id = get_option('tgi_chatgpt_thread_id');
        $assistant_id = get_option('tgi_chatgpt_assistant_id');
    
        $message_added = $this->add_message_to_thread($thread_id, $message);
    
        if (!$message_added) {
            wp_send_json_error('Failed to add message to thread');
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
    
            global $wpdb;
            $table_name = $wpdb->prefix . 'tgi_chatgpt_chats';
            $session_id = wp_generate_uuid4();
            $wpdb->insert(
                $table_name,
                array(
                    'session_id' => $session_id,
                    'user_message' => $message,
                    'bot_response' => $response,
                    'time' => current_time('mysql')
                )
            );
    
            wp_send_json_success($response);
        } else {
            wp_send_json_error('Failed to get assistant response');
        }
    }
    
    
    

    private function log_error($message) {
        global $wpdb;
        $error_log_table = $wpdb->prefix . 'tgi_chatgpt_error_logs';
        $wpdb->insert(
            $error_log_table,
            array(
                'error_message' => $message,
                'time' => current_time('mysql')
            )
        );
    }

    public function admin_page() {
        if ($_POST['tgi_chatgpt_api_key']) {
            update_option('tgi_chatgpt_api_key', sanitize_text_field($_POST['tgi_chatgpt_api_key']));
            echo '<div class="updated"><p>Changes were saved.</p></div>';
        }

        if ($_POST['tgi_chatgpt_assistant_id']) {
            update_option('tgi_chatgpt_assistant_id', sanitize_text_field($_POST['tgi_chatgpt_assistant_id']));
            echo '<div class="updated"><p>Changes were saved.</p></div>';
        }

        if ($_POST['tgi_chatgpt_thread_id']) {
            update_option('tgi_chatgpt_thread_id', sanitize_text_field($_POST['tgi_chatgpt_thread_id']));
            echo '<div class="updated"><p>Changes were saved.</p></div>';
        }

        $api_key = get_option('tgi_chatgpt_api_key', '');
        $assistant_id = get_option('tgi_chatgpt_assistant_id', '');
        $thread_id = get_option('tgi_chatgpt_thread_id', '');

        ?>
        <div class="wrap">
            <h1>TGI WP AI Chatbot Settings</h1>
            <form method="post">
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">ChatGPT API Key</th>
                        <td><input type="text" name="tgi_chatgpt_api_key" value="<?php echo esc_attr($api_key); ?>" size="50"/></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Assistant ID</th>
                        <td><input type="text" name="tgi_chatgpt_assistant_id" value="<?php echo esc_attr($assistant_id); ?>" size="50"/></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Thread ID</th>
                        <td><input type="text" name="tgi_chatgpt_thread_id" value="<?php echo esc_attr($thread_id); ?>" size="50"/></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <h2>Chat Records</h2>
            <?php
            global $wpdb;
            $table_name = $wpdb->prefix . 'tgi_chatgpt_chats';
            $chats = $wpdb->get_results("SELECT * FROM $table_name ORDER BY time DESC");

            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>ID</th><th>Session ID</th><th>User Message</th><th>Bot Response</th><th>Time</th></tr></thead>';
            echo '<tbody>';

            foreach ($chats as $chat) {
                echo '<tr>';
                echo '<td>' . $chat->id . '</td>';
                echo '<td>' . $chat->session_id . '</td>';
                echo '<td>' . $chat->user_message . '</td>';
                echo '<td>' . $chat->bot_response . '</td>';
                echo '<td>' . $chat->time . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            ?>

            <h2>Error Logs</h2>
            <?php
            $error_log_table = $wpdb->prefix . 'tgi_chatgpt_error_logs';
            $errors = $wpdb->get_results("SELECT * FROM $error_log_table ORDER BY time DESC");

            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>ID</th><th>Error Message</th><th>Time</th></tr></thead>';
            echo '<tbody>';

            foreach ($errors as $error) {
                echo '<tr>';
                echo '<td>' . $error->id . '</td>';
                echo '<td>' . $error->error_message . '</td>';
                echo '<td>' . $error->time . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            ?>
        </div>
        <?php
    }

    public function create_db() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tgi_chatgpt_chats';
        $error_log_table = $wpdb->prefix . 'tgi_chatgpt_error_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            session_id varchar(255) NOT NULL,
            user_message text NOT NULL,
            bot_response text NOT NULL,
            time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;

        CREATE TABLE $error_log_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            error_message text NOT NULL,
            time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

new TGI_WP_AI_Chatbot_Plugin();
?>
