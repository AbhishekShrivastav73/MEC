<?php
/** no direct access **/
defined('MECEXEC') or die();

/**
 * Webnus MEC RESTful class.
 * @author Webnus <info@webnus.net>
 */
class MEC_restful extends MEC_base
{
    /**
     * @var MEC_main
     */
    private $main;

    /**
     * @var MEC_db
     */
    private $db;

    /**
     * Constructor method
     * @author Webnus <info@webnus.net>
     */
    public function __construct()
    {
        // Main
        $this->main = $this->getMain();

        // Database
        $this->db = $this->getDB();
    }

    public function get_endpoint_url()
    {
        return get_rest_url(null, $this->get_namespace());
    }

    public function get_namespace()
    {
        return 'mec/v1.0';
    }

    public function get_api_version()
    {
        return '1';
    }

    public function permission(WP_REST_Request $request)
    {
        // Validate API Token
        if(!$this->is_api_token_valid($request, $request->get_header('mec-token'))) return new WP_Error('invalid_api_token', esc_html__('Invalid API Token!', 'modern-events-calendar-lite'));

        // Validate User Token
        if(!$this->is_user_token_valid($request, $request->get_header('mec-user'))) return new WP_Error('invalid_user_token', esc_html__('Invalid User Token!', 'modern-events-calendar-lite'));

        return true;
    }

    public function guest(WP_REST_Request $request)
    {
        // Validate API Token
        if(!$this->is_api_token_valid($request, $request->get_header('mec-token'))) return new WP_Error('invalid_api_token', esc_html__('Invalid API Token!', 'modern-events-calendar-lite'));

        // Set Current User if Token Provided
        $this->is_user_token_valid($request, $request->get_header('mec-user'));

        return true;
    }

    public function response(array $response): WP_REST_Response
    {
        $data = $response['data'] ?? [];
        $status = $response['status'] ?? 200;

        $wp = new WP_REST_Response($data);
        $wp->set_status($status);

        return $wp;
    }

    public function is_api_token_valid(WP_REST_Request $request, $token = '')
    {
        // Check Token
        if($token)
        {
            $settings = $this->main->get_settings();

            $tokens = [];
            foreach($settings['api_keys'] as $k => $t)
            {
                if(!is_numeric($k)) continue;
                $tokens[] = $t['key'];
            }

            if(in_array($token, $tokens)) return true;
        }

        return false;
    }

    public function is_user_token_valid(WP_REST_Request $request, $token = '')
    {
        // Check User
        if($token)
        {
            $user_id = $this->db->select("SELECT `user_id` FROM `#__usermeta` WHERE `meta_key`='mec_token' AND `meta_value`='".esc_sql($token)."'", 'loadResult');
            if(!$user_id) return false;

            // Set Current User
            wp_set_current_user($user_id);
            return true;
        }

        return false;
    }

    public function get_user_token($user_id): string
    {
        $token = $this->main->str_random(40);
        update_user_meta($user_id, 'mec_token', $token);

        return $token;
    }
}
