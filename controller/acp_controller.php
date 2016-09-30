<?php

/**
 *
 * @package phpBB Extension - mChat
 * @copyright (c) 2016 dmzx - http://www.dmzx-web.net
 * @copyright (c) 2016 kasimi
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace dmzx\mchat\controller;

use dmzx\mchat\core\functions;
use dmzx\mchat\core\settings;
use phpbb\cache\driver\driver_interface as cache_interface;
use phpbb\db\driver\driver_interface as db_interface;
use phpbb\log\log_interface;
use phpbb\request\request_interface;
use phpbb\template\template;
use phpbb\user;

class acp_controller
{
	/** @var functions */
	protected $functions;

	/** @var template */
	protected $template;

	/** @var log_interface */
	protected $log;

	/** @var user */
	protected $user;

	/** @var db_interface */
	protected $db;

	/** @var cache_interface */
	protected $cache;

	/** @var request_interface */
	protected $request;

	/** @var settings */
	protected $settings;

	/** @var string */
	protected $mchat_table;

	/** @var string */
	protected $mchat_log_table;

	/** @var string */
	protected $root_path;

	/** @var string */
	protected $php_ext;

	/**
	 * Constructor
	 *
	 * @param functions				$functions
	 * @param template				$template
	 * @param log_interface			$log
	 * @param user					$user
	 * @param db_interface			$db
	 * @param cache_interface		$cache
	 * @param request_interface		$request
	 * @param settings				$settings
	 * @param string				$mchat_table
	 * @param string				$mchat_log_table
	 * @param string				$root_path
	 * @param string				$php_ext
	 */
	public function __construct(
		functions $functions,
		template $template,
		log_interface $log,
		user $user,
		db_interface $db,
		cache_interface $cache,
		request_interface $request,
		settings $settings,
		$mchat_table,
		$mchat_log_table,
		$root_path, $php_ext
	)
	{
		$this->functions		= $functions;
		$this->template			= $template;
		$this->log				= $log;
		$this->user				= $user;
		$this->db				= $db;
		$this->cache			= $cache;
		$this->request			= $request;
		$this->settings			= $settings;
		$this->mchat_table		= $mchat_table;
		$this->mchat_log_table	= $mchat_log_table;
		$this->root_path		= $root_path;
		$this->php_ext			= $php_ext;
	}

	/**
	 * Display the options the admin can configure for this extension
	 *
	 * @param string $u_action
	 */
	public function globalsettings($u_action)
	{
		add_form_key('acp_mchat');

		$error = array();

		$is_founder = $this->user->data['user_type'] == USER_FOUNDER;

		if ($this->request->is_set_post('submit'))
		{
			$mchat_new_config = array();
			$validation = array();
			foreach ($this->settings->global as $config_name => $config_data)
			{
				$default = $this->settings->cfg($config_name);
				settype($default, gettype($config_data['default']));
				$mchat_new_config[$config_name] = $this->request->variable($config_name, $default, is_string($default));
				if (isset($config_data['validation']))
				{
					$validation[$config_name] = $config_data['validation'];
				}
			}

			// Don't allow changing pruning settings for non founders
			if (!$is_founder)
			{
				unset($mchat_new_config['mchat_prune']);
				unset($mchat_new_config['mchat_prune_gc']);
				unset($mchat_new_config['mchat_prune_mode']);
				unset($mchat_new_config['mchat_prune_num']);
			}

			if (!function_exists('validate_data'))
			{
				include($this->root_path . 'includes/functions_user.' . $this->php_ext);
			}

			$error = array_merge($error, validate_data($mchat_new_config, $validation));

			if (!check_form_key('acp_mchat'))
			{
				$error[] = 'FORM_INVALID';
			}

			if (!$error)
			{
				// Set the options the user configured
				foreach ($mchat_new_config as $config_name => $config_value)
				{
					$this->settings->set_cfg($config_name, $config_value);
				}

				// Add an entry into the log table
				$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_MCHAT_CONFIG_UPDATE', false, array($this->user->data['username']));

				trigger_error($this->user->lang('MCHAT_CONFIG_SAVED') . adm_back_link($u_action));
			}

			// Replace "error" strings with their real, localised form
			$error = array_map(array($this->user, 'lang'), $error);
		}

		if (!$error)
		{
			if ($is_founder && $this->request->is_set_post('mchat_purge') && $this->request->variable('mchat_purge_confirm', false) && check_form_key('acp_mchat'))
			{
				$this->db->sql_query('TRUNCATE TABLE ' . $this->mchat_table);
				$this->db->sql_query('TRUNCATE TABLE ' . $this->mchat_log_table);
				$this->cache->destroy('sql', $this->mchat_log_table);
				$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_MCHAT_TABLE_PURGED', false, array($this->user->data['username']));
				trigger_error($this->user->lang('MCHAT_PURGED') . adm_back_link($u_action));
			}
			else if ($is_founder && $this->request->is_set_post('mchat_prune_now') && $this->request->variable('mchat_prune_now_confirm', false) && check_form_key('acp_mchat'))
			{
				$num_pruned_messages = count($this->functions->mchat_prune());
				trigger_error($this->user->lang('MCHAT_PRUNED', $num_pruned_messages) . adm_back_link($u_action));
			}
		}

		foreach (array_keys($this->settings->global) as $key)
		{
			$this->template->assign_var(strtoupper($key), $this->settings->cfg($key));
		}

		$this->template->assign_vars(array(
			'MCHAT_ERROR'							=> implode('<br />', $error),
			'MCHAT_VERSION'							=> $this->settings->cfg('mchat_version'),
			'MCHAT_FOUNDER'							=> $is_founder,
			'S_MCHAT_PRUNE_MODE_OPTIONS'			=> $this->get_prune_mode_options($this->settings->cfg('mchat_prune_mode')),
			'L_MCHAT_BBCODES_DISALLOWED_EXPLAIN'	=> $this->user->lang('MCHAT_BBCODES_DISALLOWED_EXPLAIN', '<a href="' . append_sid("{$this->root_path}adm/index.$this->php_ext", 'i=bbcodes', true, $this->user->session_id) . '">', '</a>'),
			'L_MCHAT_TIMEOUT_EXPLAIN'				=> $this->user->lang('MCHAT_TIMEOUT_EXPLAIN','<a href="' . append_sid("{$this->root_path}adm/index.$this->php_ext", 'i=board&amp;mode=load', true, $this->user->session_id) . '">', '</a>', $this->settings->cfg('session_length')),
			'U_ACTION'								=> $u_action,
		));
	}

	/**
	 * @param string $u_action
	 */
	public function globalusersettings($u_action)
	{
		add_form_key('acp_mchat');

		$error = array();

		if ($this->request->is_set_post('submit'))
		{
			$mchat_new_config = array();
			$validation = array();
			foreach ($this->settings->ucp as $config_name => $config_data)
			{
				$default = $this->settings->cfg($config_name, true);
				settype($default, gettype($config_data['default']));
				$mchat_new_config[$config_name] = $this->request->variable('user_' . $config_name, $default, is_string($default));

				if (isset($config_data['validation']))
				{
					$validation[$config_name] = $config_data['validation'];
				}
			}

			if (!function_exists('validate_data'))
			{
				include($this->root_path . 'includes/functions_user.' . $this->php_ext);
			}

			$error = array_merge($error, validate_data($mchat_new_config, $validation));

			if (!check_form_key('acp_mchat'))
			{
				$error[] = 'FORM_INVALID';
			}

			if (!$error)
			{
				if ($this->request->variable('mchat_overwrite', 0) && $this->request->variable('mchat_overwrite_confirm', 0))
				{
					$mchat_new_user_config = array();
					foreach ($mchat_new_config as $config_name => $config_value)
					{
						$mchat_new_user_config['user_' . $config_name] = $config_value;
					}

					$sql = 'UPDATE ' . USERS_TABLE . ' SET ' . $this->db->sql_build_array('UPDATE', $mchat_new_user_config);
					$this->db->sql_query($sql);
				}

				// Set the options the user configured
				foreach ($mchat_new_config as $config_name => $config_value)
				{
					$this->settings->set_cfg($config_name, $config_value);
				}

				// Add an entry into the log table
				$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_MCHAT_CONFIG_UPDATE', false, array($this->user->data['username']));

				trigger_error($this->user->lang('MCHAT_CONFIG_SAVED') . adm_back_link($u_action));
			}

			// Replace "error" strings with their real, localised form
			$error = array_map(array($this->user, 'lang'), $error);
		}

		foreach (array_keys($this->settings->ucp) as $key)
		{
			$this->template->assign_var(strtoupper($key), $this->settings->cfg($key, true));
		}

		// Force global date format for $selected value, not user-specific
		$selected = $this->settings->cfg('mchat_date', true);
		$date_template_data = $this->settings->get_date_template_data($selected);
		$this->template->assign_vars($date_template_data);

		$notifications_template_data = $this->settings->get_enabled_post_notifications_lang();
		$this->template->assign_var('MCHAT_POSTS_ENABLED_LANG', $notifications_template_data);

		$this->template->assign_vars(array(
			'MCHAT_ERROR'		=> implode('<br />', $error),
			'MCHAT_VERSION'		=> $this->settings->cfg('mchat_version'),
			'U_ACTION'			=> $u_action,
		));
	}

	/**
	 * @param $selected
	 * @return array
	 */
	protected function get_prune_mode_options($selected)
	{
		if (empty($this->settings->prune_modes[$selected]))
		{
			$selected = 0;
		}

		$prune_mode_options = '';

		foreach ($this->settings->prune_modes as $i => $prune_mode)
		{
			$prune_mode_options .= '<option value="' . $i . '"' . (($i == $selected) ? ' selected="selected"' : '') . '>';
			$prune_mode_options .= $this->user->lang('MCHAT_ACP_' . strtoupper($prune_mode));
			$prune_mode_options .= '</option>';
		}

		return $prune_mode_options;
	}
}
