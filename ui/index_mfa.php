<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


require_once __DIR__.'/include/classes/user/CWebUser.php';
require_once __DIR__.'/include/config.inc.php';

CMessageHelper::clear();

// VAR	TYPE	OPTIONAL	FLAGS	VALIDATION	EXCEPTION
$fields = [
	'enter' =>				[T_ZBX_STR, O_OPT, P_SYS,	null,	null],
	'request' =>			[T_ZBX_STR, O_OPT, null,	null,	null],
	'totp_secret' =>		[T_ZBX_STR, O_OPT, null,	null,	null],
	'hash_function' =>		[T_ZBX_STR, O_OPT, null,	null,	null],
	'verification_code' =>	[T_ZBX_INT, O_OPT, null,	null,	null],
	'qr_code_url' =>		[T_ZBX_STR, O_OPT, null,	null,	null],
	'duo_code' =>			[T_ZBX_STR, O_OPT, null,	null,	null],
	'state' =>				[T_ZBX_STR, O_OPT, null,	null,	null]
];
check_fields($fields);

$redirect_to = (new CUrl('index.php'))->setArgument('form', 'default');
$request = getRequest('request', '');

if ($request != '' && !CHtmlUrlValidator::validateSameSite($request)) {
	$request = '';
}

if ($request != '') {
	$redirect_to->setArgument('request', $request);
}

$session_data = json_decode(base64_decode(CCookieHelper::get(ZBX_SESSION_NAME)), true);

// If MFA is not required - redirect to the main login page.
if (!array_key_exists('mfaid', $session_data) || $session_data['mfaid'] == 0) {
	redirect($redirect_to->toString());
}

if ($request != '') {
	CSessionHelper::set('request', $request);
}

$duo_redirect_uri = ((new CUrl($_SERVER['REQUEST_URI']))
	->removeArgument('state')
	->removeArgument('duo_code'))
	->setArgument('request', $request)
	->toString();

$full_duo_redirect_url = implode('', [HTTPS ? 'https://' : 'http://', $_SERVER['HTTP_HOST'], $duo_redirect_uri]);
$session_data_required = array_intersect_key($session_data, array_flip(['sessionid', 'mfaid']));

$error = null;

if (!CSessionHelper::has('state') && !hasRequest('enter')) {
	try {
		$data = CUser::getConfirmData($session_data_required + ['redirect_uri' => $full_duo_redirect_url]);

		if ($data['mfa']['type'] == MFA_TYPE_TOTP) {
			echo (new CView('mfa.login', $data))->getOutput();
			exit;
		}

		if ($data['mfa']['type'] == MFA_TYPE_DUO) {
			CSessionHelper::set('state', $data['state']);
			CSessionHelper::set('username', $data['username']);
			CSessionHelper::set('sessionid', $data['sessionid']);

			redirect($data['prompt_uri']);
		}
	}
	catch (Exception $e) {
		$error['error']['message'] = $e->getMessage();
	}
}
else {
	$data = $session_data_required;
	$data['redirect_uri'] = $full_duo_redirect_url;
	$data['mfa_response_data'] = [
		'verification_code' => getRequest('verification_code', ''),
		'totp_secret' => getRequest('totp_secret'),
		'duo_code' => getRequest('duo_code'),
		'duo_state' => getRequest('state'),
		'state' => array_key_exists('state', $session_data) ? $session_data['state'] : '',
		'username' => array_key_exists('username', $session_data) ? $session_data['username'] : ''
	];

	try {
		$confirm = CUser::confirm($data);

		if ($confirm) {
			CWebUser::checkAuthentication($confirm['sessionid']);
			CSessionHelper::set('sessionid', CWebUser::$data['sessionid']);
			CSessionHelper::unset(['mfaid', 'state', 'username']);

			API::getWrapper()->auth = [
				'type' => CJsonRpc::AUTH_TYPE_FRONTEND,
				'auth' => CWebUser::$data['sessionid']
			];

			$redirect = array_filter([$request, CWebUser::$data['url'], CMenuHelper::getFirstUrl()]);
			redirect(reset($redirect));
		}
	}
	catch (Exception $e) {
		$error['error']['message'] = $e->getMessage();

		CMessageHelper::clear();

		if ($error['error'] && $error['error']['message'] ==
			_('The verification code was incorrect, please try again.')
		) {
			$data['qr_code_url'] = getRequest('qr_code_url');
			$data['totp_secret'] = getRequest('totp_secret');
			$data['mfa']['hash_function'] = getRequest('hash_function');

			echo (new CView('mfa.login', $data + $error))->getOutput();
			exit;
		}
	}
}

echo (new CView('general.warning', [
	'header' => _('You are not logged in'),
	'messages' => $error,
	'buttons' => [
		(new CButton('login', _('Login')))
			->setAttribute('data-url', $redirect_to->getUrl())
			->onClick('document.location = this.dataset.url;')
	],
	'theme' => getUserTheme(CWebUser::$data)
]))->getOutput();

session_write_close();
