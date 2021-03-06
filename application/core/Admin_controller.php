<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Admin_controller extends CRM_Controller
{
    private $current_db_version;

    public function __construct()
    {
        parent::__construct();

        $this->current_db_version = $this->app->get_current_db_version();

        if ($this->app->is_db_upgrade_required($this->current_db_version)) {
            if ($this->input->post('upgrade_database')) {
                $this->app->upgrade_database();
            }
            include_once(APPPATH . 'views/admin/includes/db_update_required.php');
            die;
        }

        if (CI_VERSION != '3.1.9') {
            echo '<h2>Additionally you will need to replace the <b>system</b> folder. We updated Codeigniter to 3.1.9.</h2>';
            echo '<p>From the newest downloaded files upload the <b>system</b> folder to your Perfex CRM installation directory.';
            die;
        }

        if (!extension_loaded('mbstring') && (!function_exists('mb_strtoupper') || !function_exists('mb_strtolower'))) {
            die('<h1>"mbstring" PHP extension is not loaded. Enable this extension from cPanel or consult with your hosting provider to assist you enabling "mbstring" extension.</h4>');
        }

        $this->load->model('authentication_model');
        $this->authentication_model->autologin();

        if (!is_staff_logged_in()) {
            if (strpos(current_full_url(), get_admin_uri() . '/authentication') === false) {
                redirect_after_login_to_current_url();
            }

            redirect(admin_url('authentication'));
        }

        // In case staff have setup logged in as client - This is important don't change it
        foreach (['client_user_id', 'contact_user_id', 'client_logged_in', 'logged_in_as_client'] as $sk) {
            if ($this->session->has_userdata($sk)) {
                $this->session->unset_userdata($sk);
            }
        }

        // Update staff last activity
        $this->db->where('staffid', get_staff_user_id());
        $this->db->update('tblstaff', ['last_activity' => date('Y-m-d H:i:s')]);

        $this->load->model('staff_model');

        // Do not check on ajax requests
        if (!$this->input->is_ajax_request()) {
            if (ENVIRONMENT == 'production' && is_admin()) {
                if ($this->config->item('encryption_key') === '') {
                    die('<h1>Encryption key not sent in application/config/app-config.php</h1>For more info visit <a href="https://help.perfexcrm.com/encryption-key-explained/">Encryption key explained</a> FAQ3');
                } elseif (strlen($this->config->item('encryption_key')) != 32) {
                    die('<h1>Encryption key length should be 32 charachters</h1>For more info visit <a href="https://help.perfexcrm.com/encryption-key-explained/">Encryption key explained</a>');
                }
            }

            _maybe_system_setup_warnings();
            is_mobile() ? $this->session->set_userdata(['is_mobile' => true]) : $this->session->unset_userdata('is_mobile');
        }

        $currentUser = $this->staff_model->get(get_staff_user_id());

        // Deleted or inactive but have session
        if (!$currentUser || $currentUser->active == 0) {
            $this->authentication_model->logout();
            redirect(admin_url('authentication'));
        }

        $GLOBALS['current_user'] = $currentUser;
        $GLOBALS['language']     = load_admin_language();
        $GLOBALS['locale']       = get_locale_key($GLOBALS['language']);

        init_admin_assets();

        $auto_loaded_vars = [
            'current_user'    => $currentUser,
            'app_language'    => $GLOBALS['language'],
            'locale'          => $GLOBALS['locale'],
            'current_version' => $this->current_db_version,
            'task_statuses'   => $this->tasks_model->get_statuses(),
        ];

        $auto_loaded_vars = do_action('before_set_auto_loaded_vars_admin_area', $auto_loaded_vars);
        $this->load->vars($auto_loaded_vars);

        if (!$this->input->is_ajax_request()) {
            $this->init_quick_actions_links();
        }
    }

    private function init_quick_actions_links()
    {
        $this->app->add_quick_actions_link([
            'name'       => _l('invoice'),
            'permission' => 'invoices',
            'url'        => 'invoices/invoice',
            ]);

        $this->app->add_quick_actions_link([
            'name'       => _l('estimate'),
            'permission' => 'estimates',
            'url'        => 'estimates/estimate',
            ]);

        $this->app->add_quick_actions_link([
            'name'       => _l('proposal'),
            'permission' => 'proposals',
            'url'        => 'proposals/proposal',
            ]);

        $this->app->add_quick_actions_link([
            'name'       => _l('credit_note'),
            'permission' => 'credit_notes',
            'url'        => 'credit_notes/credit_note',
            ]);


        $this->app->add_quick_actions_link([
            'name'       => _l('client'),
            'permission' => 'customers',
            'url'        => 'clients/client',
            ]);

        $this->app->add_quick_actions_link([
            'name'       => _l('subscription'),
            'permission' => 'subscriptions',
            'url'        => 'subscriptions/create',
            ]);


        $this->app->add_quick_actions_link([
            'name'       => _l('project'),
            'url'        => 'projects/project',
            'permission' => 'projects',
            ]);


        $this->app->add_quick_actions_link([
            'name'            => _l('task'),
            'url'             => '#',
            'custom_url'      => true,
            'href_attributes' => [
                'onclick' => 'new_task();return false;',
                ],
            'permission' => 'tasks',
            ]);

        $this->app->add_quick_actions_link([
            'name'            => _l('lead'),
            'url'             => '#',
            'custom_url'      => true,
            'permission'      => 'is_staff_member',
            'href_attributes' => [
                'onclick' => 'init_lead(); return false;',
                ],
            ]);

        $this->app->add_quick_actions_link([
            'name'       => _l('expense'),
            'permission' => 'expenses',
            'url'        => 'expenses/expense',
            ]);


        $this->app->add_quick_actions_link([
            'name'       => _l('contract'),
            'permission' => 'contracts',
            'url'        => 'contracts/contract',
            ]);


        $this->app->add_quick_actions_link([
            'name'       => _l('goal'),
            'url'        => 'goals/goal',
            'permission' => 'goals',
            ]);

        $this->app->add_quick_actions_link([
            'name'       => _l('kb_article'),
            'permission' => 'knowledge_base',
            'url'        => 'knowledge_base/article',
            ]);

        $this->app->add_quick_actions_link([
            'name'       => _l('survey'),
            'permission' => 'surveys',
            'url'        => 'surveys/survey',
            ]);

        $tickets = [
            'name' => _l('ticket'),
            'url'  => 'tickets/add',
            ];

        if (get_option('access_tickets_to_none_staff_members') == 0 && !is_staff_member()) {
            $tickets['permission'] = 'is_staff_member';
        }

        $this->app->add_quick_actions_link($tickets);

        $this->app->add_quick_actions_link([
            'name'       => _l('staff_member'),
            'url'        => 'staff/member',
            'permission' => 'staff',
            ]);

        $this->app->add_quick_actions_link([
            'name'       => _l('calendar_event'),
            'url'        => 'utilities/calendar?new_event=true&date=' . _d(date('Y-m-d')),
            'permission' => '',
            ]);
    }
}
