#!/usr/bin/env php
<?php


class Serve {
	public $path;
	public $config;

	public function __construct()
	{
		$this->_print(date('Y-m-d H:i:s'));
		$this->path = getcwd();
		$this->_print("path ".$this->path);
		$this->loadConfig();
	}

	public function loadConfig()
	{
		$file = $this->path.'/config.ini';
		$this->_print("config ".$file);
		if (!file_exists($file)) {
			throw new \Exception("Not config.json file found.");
		}
		$this->config = parse_ini_file($file);
	}

	public function user()
	{
		$this->_print("user");
		$this->createUser();
		$this->setPermisions();
	}

	public function createUser()
	{
		$this->_print(sprintf("create user %s", $this->config['user']));
		$this->_exec(sprintf('adduser --disabled-password --gecos "" %s', $this->config['user']));
	}

	public function setPermisions()
	{
		$this->_print("setPermisions");
		$this->_exec(sprintf("chown -R %s:%s %s", $this->config['user'], $this->config['user'], $this->path));
		$this->_exec(sprintf("find %s -type d -exec chmod 755 {} \;", $this->path));
		$this->_exec(sprintf("find %s -type f -exec chmod 644 {} \;", $this->path));
	}

	public function install()
	{
		$this->_print("Restore db backup");
		$this->_exec("rm -rf backup/");
		$this->_exec("duply serv01aws fetch libros-para-aprender.com/backup/ backup/");

		$this->_print("Create db");
		$this->_exec("mysql < db-create.sql");

		$this->_print("import db");
		$this->_exec("mysql librosparaaprender < backup/db.sql");

		$this->_print("mv apache2 conf");
		$this->_exec("cp libros-para-aprender.com.conf /etc/apache2/sites-available/");

		$this->_print("enable apache2 conf");
		$this->_exec("a2ensite libros-para-aprender.com.conf");

		$this->_print("restart apache");
		$this->_exec("systemctl restart apache2");

		$this->_print("install cerbot");
		$this->_exec("certbot");

	}

	public function duplyRestore($days = 15)
	{
		$this->_print("duplyRestore");
		$this->_exec(sprintf(
			"duply %s fetch %s/public_html temp %sD", 
			$this->config['duply'], 
			$this->config['domain'],
			$days
		));
		$this->_exec(sprintf("mv %s/public_html %s/public_html_old", $this->path, $this->path));
		$this->_exec(sprintf("mv %s/temp %s/public_html", $this->path, $this->path));

	}

	public function restore2020()
	{
		$this->duplyRestore();
		$this->user();
	}

	public function _methods()
	{
		$metodos = get_class_methods($this);
		sort($metodos);
		foreach($metodos as $metodo) {
			if (preg_match('/^_/', $metodo)) {
				continue;
			}
			echo "$metodo\n";
		}
	}

	protected function _exec($cmd)
	{
		$this->_print(sprintf("exec: %s", $cmd));
		exec($cmd);
	}

	protected function _print($msg)
	{
		echo $msg."\n";
	}
}


$serve = new Serve();
if (isset($argv[1])) {
	$serve->{$argv[1]}();
} else {
	$serve->_methods();
}
