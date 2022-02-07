<?php declare(strict_types=1);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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


class CControllerTokenDelete extends CController {

	protected function checkInput() {
		$fields = [
			'tokenids'   => 'required|array_db token.tokenid',
			'admin_mode' => 'required|in 0,1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (CWebUser::isGuest()) {
			return false;
		}

		return $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_API_TOKENS);
	}

	protected function doAction() {
		$tokenids = $this->getInput('tokenids');

		$result = API::Token()->delete($tokenids);

		$deleted = count($tokenids);

		$output = [];

		if ($result) {
			$tokenids = API::Token()->get([
				'output' => [],
				'tokenids' => $tokenids
			]);

			$output['keepids'] = array_column($tokenids, 'tokenid');

			if ($deleted > 1) {
				$success = ['title' => _('API tokens deleted')];
			} else {
				$success = ['title' => _('API token deleted')];
			}


			if ($messages = get_and_clear_messages()) {
				$success['messages'] = array_column($messages, 'message');
			}

			$output['success'] = $success;
		}
		else {
			CMessageHelper::setErrorTitle(_n('Cannot delete API token', 'Cannot delete API tokens', $deleted));
			$output['error'] = [
				'title' => CMessageHelper::getTitle(),
				'messages' => array_column(get_and_clear_messages(), 'message')
			];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output)]));
	}
}
