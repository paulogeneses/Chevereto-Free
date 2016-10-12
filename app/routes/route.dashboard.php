<?php

/* --------------------------------------------------------------------

  Chevereto
  http://chevereto.com/

  @author	Rodolfo Berrios A. <http://rodolfoberrios.com/>
			<inbox@rodolfoberrios.com>

  Copyright (C) Rodolfo Berrios A. All rights reserved.
  
  BY USING THIS SOFTWARE YOU DECLARE TO ACCEPT THE CHEVERETO EULA
  http://chevereto.com/license

  --------------------------------------------------------------------- */

$route = function($handler) {
	try {
	   
		if($_POST and !$handler::checkAuthToken($_REQUEST['auth_token'])) {
			$handler->template = 'request-denied';
			return;
		}
		
		$logged_user = CHV\Login::getUser();
		
		if(!$logged_user) {
			G\redirect(G\get_base_url('login'));
		}
		
		if(!$logged_user['is_admin']) {
			return $handler->issue404();
		}
		
		$route_prefix = 'dashboard';
		$sub_routes = [
			'stats'		=> _s('Stats'),
			'images'	=> _s('Images'),
			'albums'	=> _s('Albums'),
			'users'		=> _s('Users'),
			'settings'	=> _s('Settings')
		];
		
		$default_route = 'stats';
		$doing = $handler->request[0];
		
		// Hack the user settings route
		if($doing == 'user') {
			$route = $handler->getRouteFn('settings');
			$handler::setCond('dashboard_user', true);
			return $route($handler);
		}
		
		if(!is_null($doing) and !array_key_exists($doing, $sub_routes)) {
			return $handler->issue404();
		}
		
		if($doing == '') $doing = $default_route;

		// Populate the routes
		foreach($sub_routes as $route => $label) {
			$aux = str_replace('_', '-', $route);
			$handler::setCond($route_prefix.'_'.$aux, $doing == $aux);			
			if($handler::getCond($route_prefix.'_'.$aux)) {
				$handler::setVar($route_prefix, $aux);
			}
			$route_menu[$route] = array(
				'label' => $label,
				'url'	=> G\get_base_url($route_prefix . ($route == $default_route ? '' : '/'.$route)),
				'current' => $handler::getCond($route_prefix.'_'.$aux)
			);
		}
		
		$handler::setVar($route_prefix.'_menu', $route_menu);
		$handler::setVar('tabs', $route_menu);
		
		// conds
		$is_error = false;
		$is_changed = false;
		
		// vars
		$input_errors = array();
		$error_message = NULL;
		
		if($doing == '') {
			$doing = 'stats';
		}
		
		// Old and new image size counter
		$image_size_count_qry = 'SELECT SUM(image_size) as count';
		
		switch($doing) {

			case 'stats':
				$totals = CHV\Stat::getTotals();

				$totals_display = [];
				foreach(['images', 'users', 'albums'] as $v) {
					$totals_display[$v] = G\abbreviate_number($totals[$v]);
				}
				$format_disk_ussage = explode(' ', G\format_bytes($totals['disk_used']));				
				$totals_display['disk'] = ['used' => $format_disk_ussage[0], 'unit' => $format_disk_ussage[1]];
				
				if(empty($totals_display['disk']['used'])) {
					$totals_display['disk'] = [
						'used' => 0,
						'unit' => 'KB'
					];
				}
				
				$db = CHV\DB::getInstance();
				
				$chv_version = [
					'files'	=> G\get_app_version(),
					'db'	=> CHV\getSetting('chevereto_version_installed')
				];
				
				$system_values = [
					'chv_version'	=> [
						'label'		=> _s('Chevereto Free'),
						'content'	=>  (version_compare($chv_version['files'], $chv_version['db'], '<=') ? $chv_version['files'] : $chv_version['files'] . ' ('.$chv_version['db'].' DB) <a href="'.G\get_base_url('install').'">'._s('install update').'</a>') . ' – <a data-action="check-for-updates">' . _s("check for updates") . '</a>' 
					],
					'chv_free'		=> [
						'label'		=> 'Get more',
						'content'	=> '<a href="https://chevereto.com/pricing" target="_blank">Upgrade</a> to contribute with Chevereto development and to get more <a href="https://chevereto.com/features" target="_blank">features</a> and support.',
					],
					'g_version'		=> [
						'label'		=> 'G\\',
						'content'	=> '<a href="http://gbackslash.com" target="_blank">G\\ Library '.G\get_version().'</a>'
					],
					'php_version' => [
						'label'		=> _s('PHP version'),
						'content'	=> PHP_VERSION
					],
					'server' => [
						'label'		=> _s('Server'),
						'content'	=> gethostname() . ' ' . PHP_OS . '/' . PHP_SAPI 
					],
					'mysql_version' => [
						'label'		=> _s('MySQL version'),
						'content'	=> $db->getAttr(PDO::ATTR_SERVER_VERSION)
					],
					'mysql_server_info' => [
						'label'		=> _s('MySQL server info'),
						'content'	=> $db->getAttr(PDO::ATTR_SERVER_INFO)
					],
					'gdversion' => [
						'label'		=> _s('GD Library'),
						'content'	=> 'Version ' . gd_info()['GD Version'] . ' JPEG:'.gd_info()['JPEG Support'].' GIF:'.gd_info()['GIF Read Support'].'/'.gd_info()['GIF Create Support'].' PNG:'.gd_info()['PNG Support'].' WBMP:'.gd_info()['WBMP Support'].' XBM:'.gd_info()['XBM Support']
					],
					'file_uploads' => [
						'label'		=> _s('File uploads'),
						'content'	=> ini_get('file_uploads') == 1 ? _s('Enabled') : _s('Disabled')
					],
					'max_upload_size' => [
						'label'		=> _s('Max. upload size'),
						'content'	=> G\format_bytes(G\get_ini_bytes(ini_get('upload_max_filesize')))
					],
					'max_post_size' => [
						'label'		=> _s('Max. post size'),
						'content'	=> G\format_bytes(G\get_ini_bytes(ini_get('post_max_size')))
					],
					'max_execution_time' => [
						'label'		=> _s('Max. execution time'),
						'content'	=> strtr(_n('%d second', '%d seconds', ini_get('max_execution_time')), ['%d' => ini_get('max_execution_time')])
					],
					'memory_limit' => [
						'label'		=> _s('Memory limit'),
						'content'	=> G\format_bytes(G\get_ini_bytes(ini_get('memory_limit')))
					],
					'links' => [
						'label'		=> _s('Links'),
					],
				];
				
				$chevereto_urls = [
					_s('Documentation')			=> 'https://chevereto.com/docs',
					_s('Changelog')				=> 'https://chevereto.com/changelog',
					_s('Request new features')	=> 'https://chevereto.com/request-new-features',
					_s('Bug tracking')			=> 'https://chevereto.com/bug-tracking',
					_s('Blog')					=> 'https://chevereto.com/blog',
					_s('Community')				=> 'https://chevereto.com/community',
					'GitHub' 					=> 'https://github.com/Chevereto',
				];
				$chevereto_links = [];
				foreach($chevereto_urls as $k => $v) {
					$chevereto_links[] = '<a href="'.$v.'" target="_blank">'.$k.'</a>';
				}
				
				$system_values['links']['content'] = implode(' – ', $chevereto_links);
				
				$handler::setVar('system_values', $system_values);
				$handler::setVar('totals', $totals);
				$handler::setVar('totals_display', $totals_display);
				
			break;
			
			case 'settings':
				
				$max_request_level = $handler->request[1] == 'pages' ? (in_array($handler->request[2], ['edit', 'delete']) ? 6 : 5) : 4;
				
				if($handler->isRequestLevel($max_request_level)) {
					return $handler->issue404();
				}
				
				$handler::setCond('show_submit', TRUE);
				
				$settings_sections = [
					'website'				=> _s('Website'),
					'content'				=> _s('Content'),
					'pages'					=> _s('Pages'),
					'listings'				=> _s('Listings'),
					'image-upload'			=> _s('Image upload'),
					'categories'			=> _s('Categories'),
					'users'					=> _s('Users'),
					'consent-screen'		=> _s('Consent screen'),
					'flood-protection'		=> _s('Flood protection'),
					'theme'					=> _s('Theme'),
					'homepage'				=> _s('Homepage'),
					'banners'				=> _s('Banners'),
					'system'				=> _s('System'),
					'routing'				=> _s('Routing'),
					'languages'				=> _s('Languages'),
					'external-storage'		=> _s('External storage'),
					'email'					=> _s('Email'),
					'social-networks'		=> _s('Social networks'),
					'external-services'		=> _s('External services'),
					'ip-bans'				=> _s('IP bans'),
					'api'					=> 'API',
					'additional-settings'	=> _s('Additional settings'),
                    'tools'				    => _s('Tools'),
				];
				
				foreach($settings_sections as $k => $v) {
					$current = $handler->request[1] ? ($handler->request[1] == $k) : ($k == 'website');
					$settings_sections[$k] = [
						'key'		=> $k,
						'label'		=> $v,
						'url'		=> G\get_base_url($route_prefix.'/settings/'.$k),
						'current'	=> $current
					];
					if($current) {
						$handler::setVar('settings', $settings_sections[$k]);
						if(in_array($k, ['categories', 'ip-bans', 'banners', 'external-storage', 'social-networks'])) {
							$handler::setCond('show_submit', FALSE);
						}
					}
					
				}
				
				// Reject non-existing settings sections
				if(!empty($handler->request[1]) && !array_key_exists($handler->request[1], $settings_sections)) {
					return $handler->issue404();
				}
				
				$handler::setVar('settings_menu', $settings_sections);
				//$handler::setVar('tabs', $settings_sections);
				
				switch($handler->request[1]) {
					
                    case 'homepage':
						if($_GET['action'] == 'delete-cover' && isset($_GET['cover'])) {
							$cover_index = $_GET['cover']-1;
							$homecovers = CHV\getSetting('homepage_cover_images');
							$cover_target = $homecovers[$cover_index];
							if(!G\is_integer($_GET['cover']) || $_GET['cover'] == 0 || !isset($cover_target)) {
								$is_error = TRUE;
								$error_message = _s('Request denied');
							}
							if(count($homecovers) == 1) {
								$is_error = TRUE;
								$input_errors[sprintf('homepage_cover_image_%s', $cover_index)] = _s("Can't delete all homepage cover images");
							}
							if(!$is_error) {
								// Try to delete the image (disk)
								if(!G\starts_with('default/', $cover_target['basename'])) {
									$cover_file = CHV_PATH_CONTENT_IMAGES_SYSTEM . $cover_target['basename'];
									@unlink($cover_file);
								}
								unset($homecovers[$cover_index]);
								$homecovers = array_values($homecovers);
								$homecovers_db = [];
								foreach($homecovers as $v) {
									$homecovers_db[] = $v['basename'];
								}
								CHV\Settings::update(['homepage_cover_image' => implode(',', $homecovers_db)]);
								$_SESSION['is_changed'] = TRUE;
								G\redirect('dashboard/settings/homepage');
							}
						}
						if($_SESSION['is_changed']) {
							$is_changed = TRUE;
							$changed_message = _s('Homepage cover image deleted');
							unset($_SESSION['is_changed']);
						}
					break;
					
					case 'tools':
                        $handler::setCond('show_submit', FALSE);
                    break;
                    
					case 'pages':

						// Check the sub-request
						if($handler->request[2]) {
							
							switch($handler->request[2]) {
								case 'add':
									$settings_pages['title'] = _s('Add page');
									$settings_pages['doing'] = 'add';
								break;
								case 'edit':
								case 'delete':
									if(!filter_var($handler->request[3], FILTER_VALIDATE_INT)) {
										return $handler->issue404();
									}
									$page = CHV\Page::getSingle($handler->request[3], 'id');
									if($page) {
										// Workaround for default pages
										if(G\starts_with('default/', $page['file_path'])) {
											$page['file_path'] = NULL;
										}
									} else {
										return $handler->issue404();
									}
									$handler::setvar('page', $page);
									if($handler->request[2] == 'edit') {
										$settings_pages['title'] = _s('Edit page ID %s', $page['id']);
										$settings_pages['doing'] = 'edit';
										if($_SESSION['dashboard_page_added']) {
											if($_SESSION['dashboard_page_added']['id'] == $page['id']) {
												$is_changed = TRUE;
												$changed_message = _s('The page has been added successfully.');
											}
											unset($_SESSION['dashboard_page_added']);
										}
									}
									if($handler->request[2] == 'delete') {
										if(!$handler::checkAuthToken($_REQUEST['auth_token'])) {
											$handler->template = 'request-denied';
											return;
										}
										CHV\Page::delete($page);
										$_SESSION['dashboard_page_deleted'] = [
											'id' => $page['id']
										];
										G\redirect('dashboard/settings/pages');
									}
								break;
								default:
									return $handler->issue404();
								break;
							}
							
						} else {
							$pages = CHV\Page::getAll([], ['field' => 'sort_display', 'order' => 'asc']);
							$handler::setVar('pages', $pages ?: []);
							$settings_pages['doing'] = 'listing';
							if($_SESSION['dashboard_page_deleted']) {
								$is_changed = TRUE;
								$changed_message = _s('The page has been deleted.');
								unset($_SESSION['dashboard_page_deleted']);
							}
							$handler::setCond('show_submit', FALSE);
						}
						
						$handler::setvar('settings_pages', $settings_pages);
						
					break;
				}
				
				if($_POST) {
					
					if(!headers_sent()) {
						header('X-XSS-Protection: 0');
					}
					
					/*** Do some cleaning... ***/

					// Remove bad formatting and duplicates
					if($_POST['theme_home_uids']) {
						$_POST['theme_home_uids'] = implode(',', array_keys(array_flip(explode(',', trim(preg_replace(['/\s+/', '/,+/'], ['', ','], $_POST['theme_home_uids']), ',')))));
					}
					
					// Personal mode stuff
					if($_POST['website_mode'] == 'personal') {
						$_POST['website_mode_personal_routing'] = G\get_regex_match(CHV\getSetting('routing_regex'), '#', $_POST['website_mode_personal_routing'], 1);
						
						if(!G\check_value($_POST['website_mode_personal_routing'])) {
							$_POST['website_mode_personal_routing'] = '/';
						}
					}
					
					if(isset($_POST['homepage_cta_fn_extra'])) {
						$_POST['homepage_cta_fn_extra'] = trim($_POST['homepage_cta_fn_extra']);
					}
					
					// Columns number
					foreach(['phone', 'phablet', 'laptop', 'desktop'] as $k) {
						if($_POST['listing_columns_' . $k]) {
							$key = 'listing_columns_' . $k;
							$val = $_POST[$key];
							$_POST[$key] = (filter_var($val, FILTER_VALIDATE_INT) and $val > 0) ? $val : CHV\get_chv_default_setting($key);
						}
					}
					
					// HEX color
					if($_POST['theme_main_color']) {
						$_POST['theme_main_color'] = '#' . ltrim($_POST['theme_main_color'], '#');
					}
					
					// Pages related cleaning
					if($handler->request[1] == 'pages') {
						
						$page_file_path_clean = trim(G\sanitize_relative_path($_POST['page_file_path']), '/');
												
						// Disable PHP pages here
						if(G\get_app_setting('disable_php_pages')) {
							$page_extension = G\get_file_extension($page_file_path_clean);
							if($page_extension == 'php') {
								$page_file_path_clean = G\str_replace_last($page_extension, 'html', $page_file_path_clean);
							}
						}
						
						$_POST['page_file_path'] = str_replace('default/', NULL, $page_file_path_clean);
						$_POST['page_file_path_absolute'] = CHV\Page::getPath($_POST['page_file_path']);
						
                        // Invalid page sort display
                        if(!filter_var($_POST['page_sort_display'], FILTER_VALIDATE_INT)) {
                            $_POST['page_sort_display'] = NULL;
                        }
                        
						// Do some fixing..
						if($_POST['page_type'] == 'internal') {
							if(!$_POST['page_is_active']) {
								$_POST['page_is_link_visible'] = FALSE;
							}
						} else {
							$_POST['page_is_link_visible'] = $_POST['page_is_active'];
						}
						$handler::updateVar('safe_post', [
							'page_is_active'			=> $_POST['page_is_active'],
							'page_is_link_visible'		=> $_POST['page_is_link_visible'],
							'page_file_path_absolute'	=> $_POST['page_file_path_absolute'],
						]);
					}
					
					// Validations
					$validations = [
						'website_name'	=>
							[
								'validate'	=> $_POST['website_name'] ? true : false,
								'error_msg'	=> _s('Invalid website name')
							],
						'default_language'	=>
							[
								'validate'	=> CHV\get_available_languages()[$_POST['default_language']] ? true : false,
								'error_msg'	=> _s('Invalid language')
							],
						'default_timezone'	=>
							[
								'validate'	=> in_array($_POST['default_timezone'], timezone_identifiers_list()),
								'error_msg'	=> _s('Invalid timezone')
							],
						'listing_items_per_page' =>
							[
								'validate'	=> is_numeric($_POST['listing_items_per_page']) and $_POST['listing_items_per_page'] > 0,
								'error_msg'	=> _s('Invalid value')
							],
						'upload_threads' =>
							[
								'validate'	=> filter_var($_POST['upload_threads'], FILTER_VALIDATE_INT) && $_POST['upload_threads'] > 0 && $_POST['upload_threads'] <= 5,
								'error_msg'	=> _s('Invalid value')
							],
						'upload_storage_mode'	=>
							[
								'validate'	=> in_array($_POST['upload_storage_mode'], ['datefolder', 'direct']),
								'error_msg'	=> _s('Invalid upload storage mode')
							],
						'upload_filenaming'	=>
							[
								'validate'	=> in_array($_POST['upload_filenaming'], ['original', 'random', 'mixed', 'id']),
								'error_msg'	=> _s('Invalid upload filenaming')
							],
						'upload_thumb_width'=>
							[
								'validate'	=> filter_var($_POST['upload_thumb_width'], FILTER_VALIDATE_INT),
								'error_msg'	=> _s('Invalid thumb width')
							],
						'upload_thumb_height'=>
							[
								'validate'	=> filter_var($_POST['upload_thumb_height'], FILTER_VALIDATE_INT),
								'error_msg'	=> _s('Invalid thumb height')
							],
						'upload_medium_size'=>
							[
								'validate'	=> filter_var($_POST['upload_medium_size'], FILTER_VALIDATE_INT),
								'error_msg'	=> _s('Invalid medium size')
							],
						'watermark_percentage' =>
							[
								'validate' 	=> filter_var($_POST['watermark_percentage'], FILTER_VALIDATE_INT) and (1 <= $_POST['watermark_percentage'] && $_POST['watermark_percentage'] <= 100),
								'error_msg'	=> _s('Invalid watermark percentage')
							],
						'watermark_opacity' =>
							[
								'validate' 	=> filter_var($_POST['watermark_opacity'], FILTER_VALIDATE_INT) and (1 <= $_POST['watermark_opacity'] && $_POST['watermark_opacity'] <= 100),
								'error_msg'	=> _s('Invalid watermark opacity')
							],
						'theme'	=>
							[
								'validate'	=> file_exists(G_APP_PATH_THEMES . $_POST['theme']),
								'error_msg'	=> _s('Invalid theme')
							],
						'theme_logo_height' =>
							[
								'validate'	=> G\check_value($_POST['theme_logo_height']) ? filter_var($_POST['theme_logo_height'], FILTER_VALIDATE_INT) : true,
								'error_msg'	=> _s('Invalid value')
							],
						'theme_tone' =>
							[
								'validate'	=> in_array($_POST['theme_tone'], ['light', 'dark']),
								'error_msg'	=> _s('Invalid theme tone')
							],
						'theme_main_color' =>
							[
								'validate'	=> G\check_value($_POST['theme_main_color']) ? G\is_valid_hex_color($_POST['theme_main_color']) : true,
								'error_msg'	=> _s('Invalid theme main color')
							],
						'theme_top_bar_color' =>
							[
								'validate'	=> in_array($_POST['theme_top_bar_color'], ['black', 'white']),
								'error_msg'	=> _s('Invalid theme top bar color')
							],
						'theme_top_bar_button_color' =>
							[
								'validate'	=> in_array($_POST['theme_top_bar_button_color'], CHV\getSetting('available_button_colors')),
								'error_msg'	=> _s('Invalid theme top bar button color')
							],
						'theme_image_listing_sizing' =>
							[
								'validate'	=> in_array($_POST['theme_image_listing_sizing'], ['fluid', 'fixed']),
								'error_msg'	=> _s('Invalid theme image listing size')
							],
						'theme_home_uids' =>
							[
								'validate'	=> !empty($_POST['theme_home_uids']) ? preg_match('/^[0-9]+(,[0-9]+)*$/', $_POST['theme_home_uids']) : true,
								'error_msg'	=> _s('Invalid user id')
							],
						'email_mode'		=>
							[
								'validate'	=> in_array($_POST['email_mode'], ['smtp', 'mail']),
								'error_msg'	=> _s('Invalid email mode')
							],
						'email_smtp_server_port' =>
							[
								'validate'	=> in_array($_POST['email_smtp_server_port'], [25, 80, 465, 587]),
								'error_msg'	=> _s('Invalid SMTP port')
							],
						'email_smtp_server_security'	=>
							[
								'validate'	=> in_array($_POST['email_smtp_server_security'], ['tls', 'ssl', 'unsecured']),
								'error_msg'	=> _s('Invalid SMTP security')
							],
						'website_mode' =>
							[
								'validate'	=> in_array($_POST['website_mode'], ['community', 'personal']),
								'error_msg'	=> _s('Invalid website mode')
							],
						'website_mode_personal_uid' =>
							[
								'validate'	=> $_POST['website_mode'] == 'personal' ? filter_var($_POST['website_mode_personal_uid'], FILTER_VALIDATE_INT) : TRUE,
								'error_msg'	=> _s('Invalid personal mode user ID')
							],
						'website_mode_personal_routing' =>
							[
								'validate'	=> $_POST['website_mode'] == 'personal' ? !G\is_route_available($_POST['website_mode_personal_routing']) : TRUE,
								'error_msg'	=> _s('Invalid or reserved route')
							],	
						'website_privacy_mode' =>
							[
								'validate'	=> in_array($_POST['website_privacy_mode'], ['public', 'private']),
								'error_msg'	=> _s('Invalid website privacy mode')
							],
						'website_content_privacy_mode'	=>
							[
								'validate'	=> in_array($_POST['website_content_privacy_mode'], ['default', 'private', 'private_but_link']),
								'error_msg'	=> _s('Invalid website content privacy mode')
							],
						'homepage_style' =>
							[
								'validate'	=> in_array($_POST['homepage_style'], ['landing', 'split', 'route_explore']),
								'error_msg'	=> _s('Invalid homepage style')
							],
						'homepage_cta_color' =>
							[
								'validate'	=> in_array($_POST['homepage_cta_color'], CHV\getSetting('available_button_colors')),
								'error_msg'	=> _s('Invalid homepage call to action button color')
							],
						'homepage_cta_fn' =>
							[
								'validate'	=> $_POST['homepage_style'] == 'route_explore' ? TRUE : in_array($_POST['homepage_cta_fn'], ['cta-upload', 'cta-link']),
								'error_msg'	=> _s('Invalid homepage call to action functionality')
							],
						// PAGES
						'page_title' =>
							[
								'validate'	=> !empty($_POST['page_title']),
								'error_msg'	=> _s('Invalid title')
							],
						'page_is_active' =>
							[
								'validate'	=> in_array($_POST['page_is_active'], ['1', '0']),
								'error_msg'	=> _s('Invalid status')
							],
						'page_type' =>
							[
								'validate'	=> in_array($_POST['page_type'], ['internal', 'link']),
								'error_msg'	=> _s('Invalid type')
							],
						'page_is_link_visible' =>
							[
								'validate'	=> $_POST['page_type'] == 'internal' ? in_array($_POST['page_is_link_visible'], ['1', '0']) : TRUE,
								'error_msg'	=> _s('Invalid visibility')
							],
						'page_attr_target' =>
							[
								'validate'	=> in_array($_POST['page_attr_target'], ['_self', '_blank']),
								'error_msg'	=> _s('Invalid target attribute')
							],
						'page_attr_rel' =>
							[
								'validate'	=> !empty($_POST['page_attr_rel']) ? preg_match('/^[\w\s\-]+$/', $_POST['page_attr_rel']) : TRUE,
								'error_msg'	=> _s('Invalid rel attribute')
							],
						'page_icon' =>
							[
								'validate'	=> !empty($_POST['page_icon']) ? preg_match('/^[\w\s\-]+$/', $_POST['page_icon']) : TRUE,
								'error_msg'	=> _s('Invalid icon')
							],
						'page_url_key' =>
							[
								'validate'	=> $_POST['page_type'] == 'internal' ? preg_match('/^[\w\-\_\/]+$/', $_POST['page_url_key']) : TRUE,
								'error_msg'	=> _s('Invalid URL key')
							],
						'page_file_path' =>
							[
								'validate'	=> $_POST['page_type'] == 'internal' ? preg_match('/^[\w\-\_\/]+\.('.(G\get_app_setting('disable_php_pages') ? 'html' : 'html|php').')$/', $_POST['page_file_path']) : TRUE,
								'error_msg'	=> _s('Invalid file path')
							],
						'page_link_url' =>
							[
								'validate'	=> $_POST['page_type'] == 'link' ? filter_var($_POST['page_link_url'], FILTER_VALIDATE_URL) : TRUE,
								'error_msg'	=> _s('Invalid link URL')
							],
						'user_minimum_age' =>
							[
								'validate'	=> filter_var($_POST['user_minimum_age'], FILTER_VALIDATE_INT) || $_POST['user_minimum_age'] == 0,
								'error_msg'	=> _s('Invalid user minimum age')
							],
						'route_image' =>
							[
								'validate'	=> preg_match('/^[\w\d-_]+$/', $_POST['route_image']),
								'error_msg'	=> _s('Only alphanumeric, hyphen and underscore characters are allowed')
							],
						'route_album' =>
							[
								'validate'	=> preg_match('/^[\w\d-_]+$/', $_POST['route_album']),
								'error_msg'	=> _s('Only alphanumeric, hyphen and underscore characters are allowed')
							],
					];
					
					// Detect funny stuff					
					if(isset($_POST['route_image'], $_POST['route_album']) && $_POST['route_image'] == $_POST['route_album']) {
						$validations['route_image'] = [
							'validate'	=> FALSE,
							'error_msg'	=> _s("Routes can't be the same")
						];
						$validations['route_album'] = $validations['route_image'];
					}

					// Validate image path
					if($_POST['upload_image_path']) {
						$safe_upload_image_path = rtrim(G\sanitize_relative_path($_POST['upload_image_path']), '/');
						$image_path = G_ROOT_PATH . $_POST['upload_image_path'];
						if(!file_exists($image_path)) {
							$validations['upload_image_path'] = [
								'validate'	=> false,
								'error_msg' => _s('Invalid upload image path')
							];
						}
					}
					
					// Validate CTA url
					if($_POST['homepage_style'] !== 'route_explore' and $_POST['homepage_cta_fn'] == 'cta-link' and !G\is_url($_POST['homepage_cta_fn_extra'])) {
						if(!empty($_POST['homepage_cta_fn_extra'])) {
							// Sanitize the fn_extra
							$_POST['homepage_cta_fn_extra'] = rtrim(G\sanitize_relative_path($_POST['homepage_cta_fn_extra']), '/');						
							$_POST['homepage_cta_fn_extra'] = G\get_regex_match(CHV\getSetting('routing_regex_path'), '#', $_POST['homepage_cta_fn_extra'], 1);
						} else {
							$validations['homepage_cta_fn_extra'] = [
								'validate'	=> false,
								'error_msg' => _s('Invalid call to action URL')
							];
						}
						
					}
					
					// Validate max size
					foreach(['upload_max_filesize_mb', 'user_image_avatar_max_filesize_mb', 'user_image_background_max_filesize_mb'] as $k) {
						unset($error_max_filesize);
						if(isset($_POST[$k])) {
							if(!is_numeric($_POST[$k]) or $_POST[$k] == 0) {
								$error_max_filesize = _s('Invalid value');
							} else {
								if(G\get_bytes($_POST[$k].'mb') > G\get_ini_bytes(ini_get('upload_max_filesize'))) {
									$error_max_filesize = _s('Max. allowed %s', G\format_bytes(G\get_ini_bytes(ini_get('upload_max_filesize'))));
								}
							}
							$validations[$k] = ['validate' => isset($error_max_filesize) ? false : true, 'error_msg' => $error_max_filesize];
						}
					}
					
					// Validate virtual routes
					$validate_routes = [];
					foreach(['image', 'album'] as $k) {
						$route = 'route_' . $k;
						if(file_exists(G_ROOT_PATH . $_POST[$route])) {
							$validations[$route] = [
								'validate'	=> FALSE,
								'error_msg' => _s("Can't map %m to an existing folder (%f)", ['%m' => '/' . $k, '%f' => '/' . $_POST[$route]])
							];
							continue;
						}
						if(isset($_POST[$route]) && $_POST[$route] !== $k && $validations[$route]['validate']) {
							if(G\is_route_available($_POST[$route])) {
								$validations[$route] = [
									'validate'	=> FALSE,
									'error_msg' => _s("Can't map %m to an existing route (%r)", ['%m' => '/' . $k, '%r' => '/' . $_POST[$route]])
								];
							} else {
								// Check username collision
								$user_exists = CHV\User::getSingle($_POST[$route], 'username', FALSE);
								if($user_exists) {
									$validations[$route] = [
										'validate'	=> FALSE,
										'error_msg' => _s("Can't map %m to %r (username collision)", ['%m' => '/' . $k, '%r' => '/' . $_POST[$route]])
									];
								}
							}
						}
					}
					
					// 1. No pueden mappear una ruta ya existente, excepto self (no puden mapear /dashboard, pero si /image)
					// 2. No pueden mapear a un username
					
					// Handle disabled image formats
					if($_POST['image_format_enable'] && is_array($_POST['image_format_enable'])) {
						// Validate each entry
						$image_format_enable = [];
						foreach($_POST['image_format_enable'] as $v) {
							if(in_array($v, CHV\Upload::getAvailableImageFormats())) {
								$image_format_enable[] = $v;
							}
						}
						$_POST['upload_enabled_image_formats'] = implode(',', $image_format_enable);
					}
					
					// Handle disabled languages
					if($_POST['languages_enable'] && is_array($_POST['image_format_enable'])) {
						
						// Push default language
						if(!in_array($_POST['default_language'], $_POST['languages_enable'])) {
							$_POST['languages_enable'][] = $_POST['default_language'];
						}
						
						$enabled_languages = [];
						$disabled_languages = CHV\get_available_languages();
						$_POST['languages_disable'] = [];
						foreach($_POST['languages_enable'] as $k) {
							if(!array_key_exists($k, CHV\get_available_languages())) continue;
							$enabled_languages[$k] = CHV\get_available_languages()[$k];
							unset($disabled_languages[$k]);
						}
						CHV\l10n::setStatic('disabled_languages', $disabled_languages);
						CHV\l10n::setStatic('enabled_languages', $enabled_languages);
						unset($_POST['languages_enable']);
						foreach($disabled_languages as $k => $v) {
							$_POST['languages_disable'][] = $k;
						}
						$_POST['languages_disable'] = implode(',', $_POST['languages_disable']);
					}
					
					// Handle personal mode change
					if($_POST['website_mode'] == 'personal' and $_POST['website_mode_personal_routing']) {
						if($logged_user['id'] == $_POST['website_mode_personal_uid']) {
							$new_user_url =  G\get_base_url($_POST['website_mode_personal_routing'] !== '/' ? $_POST['website_mode_personal_routing'] : NULL);
							CHV\Login::setUser('url', G\get_base_url($_POST['website_mode_personal_routing'] !== '/' ? $_POST['website_mode_personal_routing'] : NULL));
							CHV\Login::setUser('url_albums', CHV\User::getUrlAlbums(CHV\Login::getUser()['url']));
						} else if(!CHV\User::getSingle($_POST['website_mode_personal_uid'])) { // Is a valid user id anyway?
							$validations['website_mode_personal_uid'] = [
								'validate' => FALSE,
								'error_msg'=> _s('Invalid personal mode user ID')
							];
						}
					}
					
					// Validate image upload
					$content_image_props = [];
					foreach(CHV\getSetting('homepage_cover_images') as $k => $v) {
						$content_image_props[] = sprintf('homepage_cover_image_%s', $k);
					}
					$content_image_props = array_merge($content_image_props, ['logo_vector', 'logo_image', 'logo_vector_homepage', 'logo_image_homepage', 'favicon_image', 'watermark_image', 'consent_screen_cover_image', 'homepage_cover_image_add']);
					foreach($content_image_props as $k) {
						if($_FILES[$k]['tmp_name']) {
							try {
								CHV\upload_to_content_images($_FILES[$k], $k);
							} catch(Exception $e) {
								$validations[$k] = [
									'validate' => FALSE,
									'error_msg' => $e->getMessage()
								];
							}
						}
					}
					
					// Validate SMTP credentials
					if($_POST['email_mode'] == 'smtp') {
						$email_smtp_validate = [
							'email_smtp_server' 			=> _s('Invalid SMTP server'),
							'email_smtp_server_username'	=> _s('Invalid SMTP username'),
							//'email_smtp_server_password'	=> _s('Invalid SMTP password')
						];
						foreach($email_smtp_validate as $k => $v) {
							$validations[$k] = ['validate' => $_POST[$k] ? true : false, 'error_msg' => $v];
						}
						
						$email_validate = ['email_smtp_server', 'email_smtp_server_port', 'email_smtp_server_username', /*'email_smtp_server_password',*/ 'email_smtp_server_security'];
						$email_error = false;
						foreach($email_validate as $k) {
							if(!$validations[$k]['validate']) {
								$email_error = true;
							}
						}

						if(!$email_error) {
							try {
								$mail = new Mailer(true);
								$mail->SMTPAuth = true;
								$mail->SMTPSecure = $_POST['email_smtp_server_security'];
								$mail->Username = $_POST['email_smtp_server_username'];
								$mail->Password = $_POST['email_smtp_server_password'];
								$mail->Host = $_POST['email_smtp_server'];
								$mail->Port = $_POST['email_smtp_server_port'];
                                if(CHV\getSetting('error_reporting') or G\get_app_setting('debug_level') !== 0) {
                                    $mail->SMTPDebug = 2;
									$GLOBALS['SMTPDebug'] = '';
                                    $mail->Debugoutput = function($str) {
										$GLOBALS['SMTPDebug'] .= "$str\n";
									};
									if(strlen($GLOBALS['SMTPDebug']) > 0) {
										$GLOBALS['SMTPDebug'] = "SMTP Debug>>\n" . $GLOBALS['SMTPDebug'];
									}
                                }
								$valid_mail_credentials = $mail->SmtpConnect();
							} catch(Exception $e) {
								$GLOBALS['SMTPDebug'] = "SMTP Exception>>\n" . ($mail->ErrorInfo ?: $e->getMessage());
							}
							if(!$valid_mail_credentials) {
								foreach($email_smtp_validate as $k => $v) {
									$validations[$k]['validate'] = false;
								}
							}
						}
                        
					}
					
					// Validate social networks
					$social_validate = [
						'facebook'	=> ['facebook_app_id', 'facebook_app_secret'],
						'twitter'	=> ['twitter_api_key', 'twitter_api_secret'],
						'google'	=> ['google_client_id', 'google_client_secret'],
					];
					foreach($social_validate as $k => $v) {
						if($_POST[$k] == 1) {
							foreach($v as $vv) {
								$validations[$vv] = ['validate' => $_POST[$vv] ? true : false];
							}
						}
					}
					
					// Validate CDN
					if($_POST['cdn'] == 1) {
						$cdn_url = trim($_POST['cdn_url'], '/') . '/';
						if(!G\is_url($cdn_url)) {
							$cdn_url = 'http://' . $cdn_url;
						}
						if(!G\is_url($cdn_url) and !G\is_valid_url($cdn_url)) {
							$validations['cdn_url'] = [
								'validate' => false,
								'error_msg' => _s('Invalid URL')
							];
						} else {
							$_POST['cdn_url'] = $cdn_url;
							$handler::updateVar('safe_post', ['cdn_url' => $cdn_url]);
						}
					}
					
					// Validate recaptcha
					if($_POST['recaptcha'] == 1) {
						foreach(['recaptcha_public_key', 'recaptcha_private_key'] as $v) {
							$validations[$v] = ['validate' => $_POST[$v] ? true : false];
						}
					}
					
					// Run the thing
					foreach($_POST + $_FILES as $k => $v) {
						if(isset($validations[$k]) and !$validations[$k]['validate']) {
							$input_errors[$k] = $validations[$k]['error_msg'] ?: _s('Invalid value');
						}
					}
					
					// Test target page path and URL key
					if($handler->request[1] == 'pages' and in_array($handler->request[2], ['edit', 'add']) and $_POST['page_type'] == 'internal') {
						if($page) {
							$try_page_db = ($page['url_key'] !== $_POST['url_key']) or ($page['file_path'] !== $_POST['page_file_path']);
						} else {
							$try_page_db = TRUE;
						}
						if($try_page_db) {
							$db = CHV\DB::getInstance();
							$db->query('SELECT * FROM ' . CHV\DB::getTable('pages') . ' WHERE page_url_key = :page_url_key OR page_file_path = :page_file_path');
							$db->bind(':page_url_key', $_POST['page_url_key']);
							$db->bind(':page_file_path', $_POST['page_file_path']);
							$page_fetch_db = $db->fetchAll();
							if($page_fetch_db) {
								foreach($page_fetch_db as $k => $v) {
									foreach([
										'page_url_key'		=> _s('This URL key is already being used by another page (ID %s)'),
										'page_file_path'	=> _s('This file path is already being used by another page (ID %s)')
									] as $kk => $vv) {
										if($page and $page['id'] == $v['page_id']) {
											continue; // Skip on same thing
										}
										if(G\timing_safe_compare($v[$kk], $_POST[$kk])) {
											$input_errors[$kk] = sprintf($vv, $v['page_id']);
										}
									}
								}
							}
						}
					}
					
					
					// Input data looks fine
					if(count($input_errors) == 0) {
						
						if($handler->request[1] == 'pages') {
							
							// Try to edit / add a page
							if(in_array($handler->request[2], ['edit', 'add']) and $_POST['page_type'] == 'internal') {
								// Try to write page source code
								$page_write_contents = (array_key_exists('page_code', $_POST)) ? (!empty($_POST['page_code']) ? html_entity_decode($_POST['page_code']) : NULL) : NULL;
								try {
									CHV\Page::writePage(['file_path' => $_POST['page_file_path'], 'contents' => $page_write_contents]);
									// Delete old file if we are editing, file_path isn't null (default) and file path changed
									if($handler->request[2] == 'edit' and !is_null($page['file_path']) and !G\timing_safe_compare($page['file_path'], $_POST['page_file_path'])) {
										unlink(CHV\Page::getPath($page['file_path']));
									}
								} catch(Exception $e) {
									$input_errors['page_code'] = _s("Can't save page contents: %s.", $e->getMessage());
								}
							}
							
							$page_fields = CHV\Page::getFields();
							
							$page_values = [];
							foreach($page_fields as $v) {
								$_post = $_POST['page_' . $v];
								if($handler->request[2] == 'edit') {
									if(G\timing_safe_compare($page[$v], $_post)) continue; // Skip not updated values
								}
								$page_values[$v] = $_post;
							}
							
							if($page_values) {
								if($handler->request[2] == 'add') {
									$page_inserted = CHV\Page::insert($page_values);
									$_SESSION['dashboard_page_added'] = ['id' => $page_inserted];
									G\redirect(G\get_base_url('dashboard/settings/pages/edit/' . $page_inserted));
								} else {
									
									CHV\Page::update($page['id'], $page_values);
									
									$is_changed = TRUE;
									$pages_sort_changed = FALSE;
									foreach(['sort_display', 'is_active', 'is_link_visible'] as $k) {
										if($page[$k] !== $page_values[$k]) {
											$pages_sort_changed = TRUE;
											break;
										}
									}
									
									// Update 'page' var
									$page = array_merge($handler::getVar('page'), $page_values);
									CHV\Page::fill($page);
									$handler::updateVar('page', $page);
									
									// Update pages_link_visible (menu)
									$pages_link_visible = $handler::getVar('pages_link_visible');
									
									$pages_link_visible[$page['id']] = $page; // Either update or append

									if(!$page['is_active'] or !$page['is_link_visible']) {
										unset($pages_link_visible[$page['id']]);
									} elseif($pages_sort_changed) { // Need to update the sort display?
										uasort($pages_link_visible, function($a, $b) {
											return $a['sort_display'] - $b['sort_display'];
										});
									}
									$handler::setVar('pages_link_visible', $pages_link_visible);
									
								}
							}

						} else { // Settings
							
							$update_settings = [];
							foreach(CHV\getSettings() as $k => $v) {
								if(isset($_POST[$k]) and $_POST[$k] != (is_bool(CHV\getSetting($k)) ? (CHV\getSetting($k) ? 1 : 0) : CHV\getSetting($k))) {
									$update_settings[] = ['name' => $k, 'value' => $_POST[$k]];
								}
							}
							$db = CHV\DB::getInstance();
							$db->beginTransaction();
							$db->query('UPDATE ' . CHV\DB::getTable('settings') . ' SET setting_value = :value WHERE setting_name = :name');
							foreach($update_settings as $k => $v) {
								$db->bind(':name', $v['name']);
								$db->bind(':value', $v['value']);
								$db->exec();
							}
							if($db->endTransaction()) {
								$is_changed = TRUE;
								$reset_notices = FALSE;
								$settings_to_vars = [
									'website_doctitle' => 'doctitle',
									'website_description' => 'meta_description',
									'website_keywords'=> 'meta_keywords'
								];
								foreach($update_settings as $k => $v) {
									CHV\Settings::setValue($v['name'], $v['value']);
									if($v['name'] == 'maintenance') {
										$reset_notices = true;
									}
									if(array_key_exists($v['name'], $settings_to_vars)) {
										$handler::setVar($settings_to_vars[$v['name']], CHV\getSetting($v['name']));
									}
								}
								if($reset_notices) {
									$system_notices = CHV\getSystemNotices();
									$handler::setVar('system_notices', $system_notices);
								}
							}
							
						}
						
					} else {
						$is_error = TRUE;
					}

				}
				
			break;
			
			case 'images':
			case 'albums':
			case 'users':
				switch($doing) {
					case 'images':
						$tabs = [
							[
								'list'		=> true,
								'tools'		=> true,
								'label'		=> _s('Most recent'),
								'id'		=> 'list-most-recent',
								'params'	=> 'list=images&sort=date_desc&page=1',
								'current'	=> $_REQUEST['sort'] == 'date_desc' or !$_REQUEST['sort'] ? true : false,
							],
							[
								'list'		=> true,
								'tools'		=> true,
								'label'		=> _s('Oldest'),
								'id'		=> 'list-most-oldest',
								'params'	=> 'list=images&sort=date_asc&page=1',
								'current'	=> $_REQUEST['sort'] == 'date_asc',
							],
							[
								'list'		=> true,
								'tools'		=> true,
								'label'		=> _s('Most viewed'),
								'id'		=> 'list-most-viewed',
								'params'	=> 'list=images&sort=views_desc&page=1',
								'current'	=> $_REQUEST['sort'] == 'views_desc',
							],
						];
					break;
					
					case 'albums':
						$tabs = [
							[
								'list'		=> true,
								'tools'		=> true,
								'label'		=> _s('Most recent'),
								'id'		=> 'list-most-recent',
								'params'	=> 'list=albums&sort=date_desc&page=1',
								'current'	=> $_REQUEST['sort'] == 'date_desc' or !$_REQUEST['sort'] ? true : false,
							],
							[
								'list'		=> true,
								'tools'		=> true,
								'label'		=> _s('Oldest'),
								'id'		=> 'list-most-oldest',
								'params'	=> 'list=albums&sort=date_asc&page=1',
								'current'	=> $_REQUEST['sort'] == 'date_asc',
							]
						];
					break;
					
					case 'users':
						$tabs = [
							[
								'list'		=> true,
								'tools'		=> false,
								'label'		=> _s('Top users'),
								'id'		=> 'list-top-users',
								'params'	=> 'list=users&sort=image_count_desc&page=1',
								'current'	=> $_REQUEST['sort'] == 'image_count_desc' or !$_REQUEST['sort'] ? true : false,
							],
							[
								'list'		=> true,
								'tools'		=> false,
								'label'		=> _s('Most recent'),
								'id'		=> 'list-most-recent',
								'params'	=> 'list=users&sort=date_desc&page=1',
								'current'	=> $_REQUEST['sort'] == 'date_desc',
							],
							[
								'list'		=> true,
								'tools'		=> false,
								'label'		=> _s('Oldest'),
								'id'		=> 'list-most-oldest',
								'params'	=> 'list=users&sort=date_asc&page=1',
								'current'	=> $_REQUEST['sort'] == 'date_asc',
							]
						];
					break;
				}
				
				$type = $doing;
				$current = false;
				foreach($tabs as $k => $v) {
					if($v['current']) {
						$current = $k;
					}
					$tabs[$k]['type'] = $type;
					$tabs[$k]['url'] = G\get_base_url('dashboard/'.$type.'/?' . $tabs[$k]['params']);
				}
				if(!$current) {
					$current = 0;
					$tabs[0]['current'] = true;
				}
				
				// Use CHV magic params
				$list_params = CHV\Listing::getParams();
				parse_str($tabs[$current]['params'], $tab_params);		
				preg_match('/(.*)_(asc|desc)/', !empty($_REQUEST['sort']) ? $_REQUEST['sort'] : $tab_params['sort'], $sort_matches);
				$list_params['sort'] = array_slice($sort_matches, 1);
				
				$list = new CHV\Listing;
				$list->setType($type); // images | users | albums
				$list->setOffset($list_params['offset']);
				$list->setLimit($list_params['limit']); // how many results?
				$list->setItemsPerPage($list_params['items_per_page']); // must
				$list->setSortType($list_params['sort'][0]); // date | size | views
				$list->setSortOrder($list_params['sort'][1]); // asc | desc
				$list->setRequester($logged_user );
				$list->output_tpl = $type;
				$list->exec();
				
			break;
			
		}
		
		$handler::setVar('pre_doctitle', _s('Dashboard'));
		
		$handler::setCond('error', $is_error);
		$handler::setCond('changed', $is_changed);
		
		$handler::setVar('error_message', $error_message);
		$handler::setVar('input_errors', $input_errors);
		$handler::setVar('changed_message', $changed_message);
		
		if($tabs) {
			$handler::setVar('sub_tabs', $tabs);
		}
		if($list) {
			$handler::setVar('list', $list);
		}
		
	} catch(Exception $e) {
		G\exception_to_error($e);
	}
};