<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

class CController {

	const VALIDATION_OK = 0;
	const VALIDATION_ERROR = 1;
	const VALIDATION_FATAL_ERROR = 2;

	/**
	 * Action name, so that controller knows what action he is executing.
	 *
	 * @var string
	 */
	private $action;

	/**
	 * Response oject generated by controller.
	 *
	 * @var string
	 */
	private $response;

	/**
	 * Result of input validation, one of VALIDATION_OK, VALIDATION_ERROR, VALIDATION_FATAL_ERROR.
	 *
	 * @var string
	 */
	private $validationResult;

	/**
	 * Input parameters retrieved from global $_REQUEST after validation.
	 *
	 * @var string
	 */
	public $input = array();

	public function __construct() {
		session_start();
	}

	/**
	 * Set controller action name.
	 *
	 * @return var
	 */
	public function setAction($action) {
		$this->action = $action;
	}

	/**
	 * Set controller response.
	 *
	 * @return var
	 */
	public function setResponse($response) {
		$this->response = $response;
	}

	/**
	 * Return controller response object.
	 *
	 * @return var
	 */
	public function getResponse() {
		return $this->response;
	}

	/**
	 * Return controller action name.
	 *
	 * @return var
	 */
	public function getAction() {
		return $this->action;
	}

	/**
	 * Return user type.
	 *
	 * @return var
	 */
	public function getUserType() {
		return CWebUser::getType();
	}

	/**
	 * Validate input parameters.
	 *
	 * @return var
	 */
	public function validateInput($validationRules) {
		if (isset($_SESSION['formData']))
		{
			$input = array_merge($_REQUEST, $_SESSION['formData']);
			unset($_SESSION['formData']);
		}
		else {
			$input = $_REQUEST;
		}

		$validator = new CNewValidator($input, $validationRules);

		foreach ($validator->getAllErrors() as $error) {
			info($error);
		}

		if ($validator->isErrorFatal()) {
			$this->validationResult = self::VALIDATION_FATAL_ERROR;
		}
		else if ($validator->isError()) {
			$this->input = $validator->getValidInput();
			$this->validationResult = self::VALIDATION_ERROR;
		}
		else {
			$this->input = $validator->getValidInput();
			$this->validationResult = self::VALIDATION_OK;
		}

		return ($this->validationResult == self::VALIDATION_OK);
	}

	/**
	 * Return validation result.
	 *
	 * @return var
	 */
	public function getValidationError() {
		return $this->validationResult;
	}

	/**
	 * Check if input parameter exists.
	 *
	 * @return var
	 */
	public function hasInput($var) {
		return array_key_exists($var, $this->input);
	}

	/**
	 * Get single input parameter.
	 *
	 * @return var
	 */
	public function getInput($var, $default = null) {
		if ($default === null) {
			return $this->input[$var];
		}
		else {
			return array_key_exists($var, $this->input) ? $this->input[$var] : $default;
		}
	}

	/**
	 * Return all input parameters.
	 *
	 * @return var
	 */
	public function getInputAll() {
		return $this->input;
	}

	/**
	 * Check user permissions. The function must present in inherited controller.
	 *
	 * @return var
	 */
	protected function checkPermissions() {
		return false;
	}

	/**
	 * Validate input parameters. The function must present in inherited controller.
	 *
	 * @return var
	 */
	protected function checkInput() {
		access_deny(ACCESS_DENY_PAGE);
	}

	/**
	 * Execute action and generate response object. The function must present in inherited controller.
	 *
	 * @return var
	 */
	protected function doAction() {
		return null;
	}

	/**
	 * Main controller processing routine. Returns response object: data, redirect or fatal redirect.
	 *
	 * @return var
	 */
	final public function run() {
		if ($this->checkInput()) {
			if ($this->checkPermissions() !== true) {
				access_deny(ACCESS_DENY_PAGE);
			}
			$this->doAction();
		}

		if (CProfile::isModified()) {
			DBstart();
			$result = CProfile::flush();
			DBend($result);
		}

		return $this->getResponse();
	}
}
