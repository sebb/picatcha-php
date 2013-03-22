<?php

namespace Picatcha;

/**
 * Class PicatchaResponse
 *
 * @package Picatcha
 */
class PicatchaResponse {

	/**
	 * @var bool
	 */
	private $isValid;

	/**
	 * @var string
	 */
	private $error;

	/**
	 * @param bool   $isValid
	 * @param string $error
	 */
	function __construct($isValid, $error = '') {

		$this->isValid = $isValid;
		$this->error   = $error;
	}

	/**
	 * @param $error
	 */
	public function setError($error) {

		$this->error = $error;
	}

	/**
	 * @return mixed
	 */
	public function getError() {

		return $this->error;
	}

	/**
	 * @param $isValid
	 */
	public function setIsValid($isValid) {

		$this->isValid = $isValid;
	}

	/**
	 * @return mixed
	 */
	public function getIsValid() {

		return $this->isValid;
	}
}
