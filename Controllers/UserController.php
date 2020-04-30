<?php

class UserController
{
	public function gen_token($user_id)
	{
		$bytes = openssl_random_pseudo_bytes(20, $cstrong);
		$token = bin2hex($bytes);

		$query = "INSERT INTO `user_tokens` (`id`,`user_id`) VALUES (:id, :user_id)";
		$args = [
			'id' => $token,
			'user_id' => $user_id
		];

		$db = new DB();
		$db::sql($query, $args);

		return $token;
	}
}
