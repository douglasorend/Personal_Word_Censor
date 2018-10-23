<?php
/**********************************************************************************
* Profile-Censor.php - Subs of the Personal Word Censor mod
***********************************************************************************
* This mod is licensed under the 2-clause BSD License, which can be found here:
*	http://opensource.org/licenses/BSD-2-Clause
***********************************************************************************
* This program is distributed in the hope that it is and will be useful, but      *
* WITHOUT ANY WARRANTIES; without even any implied warranty of MERCHANTABILITY    *
* or FITNESS FOR A PARTICULAR PURPOSE.                                            *
**********************************************************************************/
if (!defined('SMF')) 
	die('Hacking attempt...');

/**********************************************************************************
// Profile hook that adds the Personal Word Censor area:
**********************************************************************************/
function PWC_Profile(&$profile_areas)
{
	global $txt, $context;

	$profile_areas['edit_profile']['areas']['censor'] = array(
		'label' => $txt['personal_censored_words'],
		'file' => 'Profile-Censor.php',
		'enabled' => $context['user']['is_owner'] || $context['user']['is_admin'],
		'function' => 'PWC_SetCensor',
		'permission' => array(
			'own' => 'profile_view_own',
			'any' => 'profile_view_any',
		),
	);
}

/**********************************************************************************
// Function that sets the personal word censor list:
**********************************************************************************/
function PWC_SetCensor()
{
	global $txt, $context, $smcFunc, $cur_profile, $user_info;

	if (!empty($_POST['save_censor']))
	{
		// Make sure censoring is something they can do.
		checkSession();

		$censored_vulgar = array();
		$censored_proper = array();

		// Rip it apart, then split it into two arrays.
		if (isset($_POST['censortext']))
		{
			$_POST['censortext'] = explode("\n", strtr($_POST['censortext'], array("\r" => '')));

			foreach ($_POST['censortext'] as $c)
				list ($censored_vulgar[], $censored_proper[]) = array_pad(explode('=', trim($c)), 2, '');
		}
		elseif (isset($_POST['censor_vulgar'], $_POST['censor_proper']))
		{
			if (is_array($_POST['censor_vulgar']))
			{
				foreach ($_POST['censor_vulgar'] as $i => $value)
				{
					if (trim(strtr($value, '*', ' ')) == '')
						unset($_POST['censor_vulgar'][$i], $_POST['censor_proper'][$i]);
				}

				$censored_vulgar = $_POST['censor_vulgar'];
				$censored_proper = $_POST['censor_proper'];
			}
			else
			{
				$censored_vulgar = explode("\n", strtr($_POST['censor_vulgar'], array("\r" => '')));
				$censored_proper = explode("\n", strtr($_POST['censor_proper'], array("\r" => '')));
			}
		}

		// Sanitize the input, then update the member record with the new info....
		$cur_profile['censor_vulgar'] = $smcFunc['htmlspecialchars'](implode("\n", $censored_vulgar));
		$cur_profile['censor_proper'] = $smcFunc['htmlspecialchars'](implode("\n", $censored_proper));
		$fields = array(
			'censor_vulgar' => $cur_profile['censor_vulgar'] ,
			'censor_proper' => $cur_profile['censor_proper'],
		);
		updateMemberData($context['id_member'], $fields);
	}

	if (isset($_POST['censortest']))
	{
		$censorText = htmlspecialchars($_POST['censortest'], ENT_QUOTES);
		$context['censor_test'] = strtr(PWC_censorText($censorText), array('"' => '&quot;'));
	}

	// Set everything up for the template to do its thang.
	$censor_vulgar = explode("\n", $cur_profile['censor_vulgar']);
	$censor_proper = explode("\n", $cur_profile['censor_proper']);

	$context['censored_words'] = array();
	for ($i = 0, $n = count($censor_vulgar); $i < $n; $i++)
	{
		if (empty($censor_vulgar[$i]) || empty($censor_proper[$i]))
			continue;

		// Skip it, it's either spaces or stars only.
		if (trim(strtr($censor_vulgar[$i], '*', ' ')) == '')
			continue;

		$context['censored_words'][htmlspecialchars(trim($censor_vulgar[$i]))] = isset($censor_proper[$i]) ? htmlspecialchars($censor_proper[$i]) : '';
	}

	loadTemplate('Admin');
	loadLanguage('Admin');
	$context['sub_template'] = 'edit_censored';
	$context['page_title'] = $txt['admin_censored_words'] = $txt['personal_censored_words'];
	$context['PWC_settings'] = true;

	// We need to edit the admin-level form before it gets to the user:
	add_integration_function('integrate_buffer', 'PWC_Buffer', false);
}

/**********************************************************************************
// Hook function that alters the admin template for this mod:
**********************************************************************************/
function PWC_Buffer($buffer)
{
	global $context;
	return str_replace('?action=admin;area=postsettings;sa=censor', '?action=profile;area=censor' . (!empty($_GET['u']) ? ';u=' . $_GET['u'] : ''), $buffer);
}

/**********************************************************************************
// Internal function that replaces all vulgar words with respective proper words
// for user editing their personal censor words. (substring or whole words..)
**********************************************************************************/
function &PWC_censorText(&$text, $force = false)
{
	global $modSettings, $options, $settings, $txt, $cur_profile;

	if ((!empty($options['show_no_censored']) && $settings['allow_no_censored'] && !$force) || empty($cur_profile['censor_vulgar']))
		return $text;

	// If they haven't yet been loaded, load them.
	$censor_vulgar = explode("\n", $modSettings['censor_vulgar'] . (!empty($cur_profile['censor_vulgar']) ? "\n" . $cur_profile['censor_vulgar'] : ''));
	$censor_proper = explode("\n", $modSettings['censor_proper'] . (!empty($cur_profile['censor_proper']) ? "\n" . $cur_profile['censor_proper'] : ''));

	// Quote them for use in regular expressions.
	for ($i = 0, $n = count($censor_vulgar); $i < $n; $i++)
	{
		$censor_vulgar[$i] = strtr(preg_quote($censor_vulgar[$i], '/'), array('\\\\\\*' => '[*]', '\\*' => '[^\s]*?', '&' => '&amp;'));
		$censor_vulgar[$i] = (empty($modSettings['censorWholeWord']) ? '/' . $censor_vulgar[$i] . '/' : '/(?<=^|\W)' . $censor_vulgar[$i] . '(?=$|\W)/') . (empty($modSettings['censorIgnoreCase']) ? '' : 'i') . ((empty($modSettings['global_character_set']) ? $txt['lang_character_set'] : $modSettings['global_character_set']) === 'UTF-8' ? 'u' : '');

		if (strpos($censor_vulgar[$i], '\'') !== false)
		{
			$censor_proper[count($censor_vulgar)] = $censor_proper[$i];
			$censor_vulgar[count($censor_vulgar)] = strtr($censor_vulgar[$i], array('\'' => '&#039;'));
		}
	}

	// Censoring isn't so very complicated :P.
	$text = preg_replace($censor_vulgar, $censor_proper, $text);
	return $text;
}

?>