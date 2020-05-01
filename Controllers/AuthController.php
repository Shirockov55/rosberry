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

		$query = "INSERT INTO `users` (`email`,`name`,`password`,`last_coords`) VALUES (:email, :name, :password, POINT(:lat, :long))";
		$args = [
			'email' => $data['email'],
			'name' => $data['name'] ?? '',
			'password' => md5($data['password']),
			'lat' => $data['lat'],
			'long' => $data['long'],
		];

		$db::sql($query, $args);
		$user_id = $db::lastInsertId();

		$userC = new UserController($user_id);
		//$userC->set_coords($data);
		$token = $userC->get_token();

		return [$user_id, $token];
	}

	public function login($data)
	{
		$db = new DB();

		$user = $db::getRow("SELECT * FROM `users` WHERE `email` = ? AND `password` = ?", [ $data['email'], md5($data['password']) ]);

		if ($user !== false) {
			$userC = new UserController($user['id']);
			$userC->set_coords($data);
			$token = $userC->get_token();

			return [
				'status' => 'success',
				'token' => $token
			];
		}
		else{
			return [
				'status' => 'error',
				'message' => 'wrong login or password',
				//'data' => $data
			];
		}
	}
}
