<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB;

class RawConnection {
	/**
	 * @var resource[] $pipes
	 *
	 * $pipes[0] holds STDIN for smbclient
	 * $pipes[1] holds STDOUT for smbclient
	 */
	private $pipes;

	/**
	 * @var resource $process
	 */
	private $process;

	public function __construct($command, $env = array()) {
		$descriptorSpec = array(
			0 => array('pipe', 'r'), // child reads from stdin
			1 => array('pipe', 'w'), // child writes to stdout
			2 => array('pipe', 'w'), // child writes to stderr
			3 => array('pipe', 'r'), // child reads from fd#3
			4 => array('pipe', 'r'), // child reads from fd#4
			5 => array('pipe', 'w') // child writes to fd#5
		);
		setlocale(LC_ALL, Server::LOCALE);
		$env = array_merge($env, array(
			'CLI_FORCE_INTERACTIVE' => 'y', // Needed or the prompt isn't displayed!!
			'LC_ALL' => Server::LOCALE
		));
		$this->process = proc_open($command, $descriptorSpec, $this->pipes, '/', $env);
		if (!$this->isValid()) {
			throw new ConnectionError();
		}
	}

	/**
	 * check if the connection is still active
	 *
	 * @return bool
	 */
	public function isValid() {
		if (is_resource($this->process)) {
			$status = proc_get_status($this->process);
			return $status['running'];
		} else {
			return false;
		}
	}

	/**
	 * send input to the process
	 *
	 * @param string $input
	 */
	public function write($input) {
		fwrite($this->getInputStream(), $input);
		fflush($this->getInputStream());
	}

	/**
	 * read a line of output
	 *
	 * @return string
	 */
	public function read() {
		return trim(fgets($this->getOutputStream()));
	}

	/**
	 * get all output until the process closes
	 *
	 * @return array
	 */
	public function readAll() {
		$output = array();
		while ($line = $this->read()) {
			$output[] = $line;
		}
		return $output;
	}

	public function getInputStream() {
		return $this->pipes[0];
	}

	public function getOutputStream() {
		return $this->pipes[1];
	}

	public function getErrorStream() {
		return $this->pipes[2];
	}

	public function getAuthStream() {
		return $this->pipes[3];
	}

	public function getFileInputStream() {
		return $this->pipes[4];
	}

	public function getFileOutputStream() {
		return $this->pipes[5];
	}

	public function writeAuthentication($user, $password) {
		$auth = ($password === false)
			? "username=$user"
			: "username=$user\npassword=$password";

		if (fwrite($this->getAuthStream(), $auth) === false) {
			fclose($this->getAuthStream());
			return false;
		}
		fclose($this->getAuthStream());
		return true;
	}

	public function close($terminate = true) {
		if (!is_resource($this->process)) {
			return;
		}
		if ($terminate) {
			proc_terminate($this->process);
		}
		proc_close($this->process);
	}

	public function __destruct() {
		$this->close();
	}
}
