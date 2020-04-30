<?php

require 'UserController.php';

class AuthController
{
	function __construct()
	{
		// code...
	}

	public function register($data)
	{
		$db = new DB();

		$query = "INSERT INTO `users` (`email`,`name`,`password`) VALUES (:email, :name, :password)";
		$args = [
			'email' => $data['email'],
			'name' => $data['name'] ?? '',
			'password' => md5($data['password'])
		];

		$db::sql($query, $args);
		$user_id = $db::lastInsertId();

		$token = UserController::gen_token($user_id);

		return [$user_id, $token];
	}

	public function login($data)
	{
		$db = new DB();

		$user = $db::getRow("SELECT * FROM `users` WHERE `email` = ? AND `password` = ?", [ $data['email'], md5($data['password']) ]);

		if($user !== false){
			$token = UserController::gen_token($user['id']);
			return [
				'status' => 'success',
				'token' => $token
			];
		}
		else{
			return [
				'status' => 'error',
				'message' => 'wrong login or password'
			];
		}
	}
}
