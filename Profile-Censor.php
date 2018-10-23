<?php

function PWC_Profile(&$profile_areas)
{
	global $txt;

	$profile_areas['edit_profile']['areas']['censor'] = array(
		'label' => $txt['personal_censored_words'],
		'file' => 'Profile-Censor.php',
		'function' => 'PWC_SetCensor',
		'permission' => array(
			'own' => 'profile_view_own',
			'any' => 'profile_view_any',
		),
	);
}

// Set the censored words.
function PWC_SetCensor()
{
	global $txt, $user_info, $context, $smcFunc;

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

		// Sanantize the input, then update the member record with the new info....
		$user_info['censor_vulgar'] = $smcFunc['htmlspecialchars'](implode("\n", $censored_vulgar));
		$user_info['censor_proper'] = $smcFunc['htmlspecialchars'](implode("\n", $censored_proper));
		$fields = array(
			'censor_vulgar' => $user_info['censor_vulgar'] ,
			'censor_proper' => $user_info['censor_proper'],
		);
		updateMemberData($user_info['id'], $fields);
	}

	if (isset($_POST['censortest']))
	{
		$censorText = htmlspecialchars($_POST['censortest'], ENT_QUOTES);
		$context['censor_test'] = strtr(censorText($censorText), array('"' => '&quot;'));
	}

	// Set everything up for the template to do its thang.
	$censor_vulgar = explode("\n", $user_info['censor_vulgar']);
	$censor_proper = explode("\n", $user_info['censor_proper']);

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
	$context['sub_template'] = 'edit_censored';
	$txt['admin_censored_words'] = $txt['personal_censored_words'];
	$context['page_title'] = $txt['admin_censored_words'];

	// We need to edit the admin-level form before it gets to the user:
	add_integration_function('integrate_buffer', 'PWC_Buffer', false);
}

function PWC_Buffer($buffer)
{
	global $txt;
	$buffer = str_replace('?action=admin;area=postsettings;sa=censor', '?action=profile;area=censor' . (!empty($_GET['u']) ? ';u=' . $_GET['u'] : ''), $buffer);
	$buffer = preg_replace('@<hr[^>]*?/>@siu', '<br class="clear" />', $buffer);
	$buffer = preg_replace('@<dl class="settings">.*?</dl\>@siu', '', $buffer);
	return $buffer;
}

?>