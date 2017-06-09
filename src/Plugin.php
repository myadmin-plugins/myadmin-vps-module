<?php

namespace Detain\MyAdminVps;

use Symfony\Component\EventDispatcher\GenericEvent;

class Plugin {

	public function __construct() {
	}

	public static function Load(GenericEvent $event) {
		$service = $event->getSubject();
		$service->set_module('vps')
			->set_enable(function($service) {
				$service_info = $service->get_service_info();
				$settings = get_module_settings($service->get_module());
				$GLOBALS['tf']->history->add($service->get_module().'queue', $service_info[$settings['PREFIX'].'_id'], 'initial_install', '', $service_info[$settings['PREFIX'].'_custid']);
				$GLOBALS['tf']->history->add($service->get_module().'queue', $service_info[$settings['PREFIX'].'_id'], 'initial_install', '', $service_info[$settings['PREFIX'].'_custid']);
				admin_email_vps_pending_setup($service_info[$settings['PREFIX'].'_id']);
			})->set_reactivate(function($service) {
				$service_types = run_event('get_service_types', false, $service->get_module());
				$service_info = $service->get_service_info();
				$settings = get_module_settings($service->get_module());
				$db = get_module_db($service->get_module());
				if ($service_info[$settings['PREFIX'].'_server_status'] === 'deleted' || $service_info[$settings['PREFIX'].'_ip'] == '') {
					$GLOBALS['tf']->history->add($settings['PREFIX'], 'change_status', 'pending-setup', $service_info[$settings['PREFIX'].'_id'], $service_info[$settings['PREFIX'].'_custid']);
					$db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_status='pending-setup' where {$settings['PREFIX']}_id='{$service_info[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
					$GLOBALS['tf']->history->add($service->get_module().'queue', $service_info[$settings['PREFIX'].'_id'], 'initial_install', '', $service_info[$settings['PREFIX'].'_custid']);
				} else {
					$GLOBALS['tf']->history->add($settings['PREFIX'], 'change_status', 'active', $service_info[$settings['PREFIX'].'_id'], $service_info[$settings['PREFIX'].'_custid']);
					$db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_status='active' where {$settings['PREFIX']}_id='{$service_info[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
					$GLOBALS['tf']->history->add($service->get_module().'queue', $service_info[$settings['PREFIX'].'_id'], 'enable', '', $service_info[$settings['PREFIX'].'_custid']);
					$GLOBALS['tf']->history->add($service->get_module().'queue', $service_info[$settings['PREFIX'].'_id'], 'start', '', $service_info[$settings['PREFIX'].'_custid']);
				}
				$smarty = new \TFSmarty;
				$smarty->assign('vps_name', $service_types[$service_info[$settings['PREFIX'] . '_type']]['services_name']);
				$email = $smarty->fetch('email/admin_email_vps_reactivated.tpl');
				$subject = $service_info[$settings['TITLE_FIELD']].' '.$service_types[$service_info[$settings['PREFIX'] . '_type']]['services_name'].' '.$settings['TBLNAME'].' Re-Activated';
				$headers = '';
				$headers .= 'MIME-Version: 1.0' . EMAIL_NEWLINE;
				$headers .= 'Content-type: text/html; charset=UTF-8' . EMAIL_NEWLINE;
				$headers .= 'From: ' . TITLE . ' <' . EMAIL_FROM . '>' . EMAIL_NEWLINE;
				admin_mail($subject, $email, $headers, false, 'admin_email_vps_reactivated.tpl');
			})->set_disable(function($service) {
			})->register();
	}

	public static function Settings(GenericEvent $event) {
		$module = 'vps';
		$settings = $event->getSubject();
		$settings->add_text_setting($module, 'Credentials', 'webuzo_license_key', 'Webuzo License Key:', 'API Credentials for Webuozo', $settings->get_setting('WEBUZO_LICENSE_KEY'));
		$settings->add_text_setting($module, 'Slice Costs', 'vps_ny_cost', 'VPS NY4 Multiplier:', 'This is the multiplier to a normal cost for an item to be hosted in NY.', $settings->get_setting('VPS_NY_COST'));
		$settings->add_text_setting($module, 'Slice Amounts', 'vps_slice_ram', 'Ram Per Slice:', 'Amount of ram in MB per VPS Slice', $settings->get_setting('VPS_SLICE_RAM'));
		$settings->add_text_setting($module, 'Slice Amounts', 'vps_slice_hd', 'HD Space Per Slice:', 'Amount of HD space in GB per VPS Slice', $settings->get_setting('VPS_SLICE_HD'));
		$settings->add_text_setting($module, 'Slice Amounts', 'vps_bw_type', 'Bandwidth Limited by Total Traffic or Throttling', 'Enable/Disable Sales Of This Type', $settings->get_setting('VPS_BW_TYPE'), array('1', '2'), array('Throttled in mbps', 'Total GBytes Used', ));
		$settings->add_text_setting($module, 'Slice Amounts', 'vps_slice_bw', 'Bandwidth Limit Per Slice in Mbits/s  or Gbytes:', 'Amount of Bandwidth per slice.', $settings->get_setting('VPS_SLICE_BW'));
		$settings->add_text_setting($module, 'Slice Amounts', 'vps_slice_max', 'Max Slices Per Order:', 'Maximum amount of slices any one VPS can be.', $settings->get_setting('VPS_SLICE_MAX'));
		$settings->add_select_master_autosetup($module, 'Auto-Setup Servers', $module, 'setup_servers', 'Auto-Setup Servers:', '<p>Choose which servers are used for auto-server Setups.</p>');
		$settings->add_dropdown_setting($module, 'Out of Stock', 'outofstock_vps', 'Out Of Stock VPS', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_VPS'), array('0', '1'), array('No', 'Yes'));
	}
}
