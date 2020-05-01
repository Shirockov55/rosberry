<?php

class UserController
{

	public function get_token($user_id)
	{
		$token = self::create_token();

		$db = new DB();
		$db::sql("DELETE FROM `user_tokens` WHERE `user_id` = ?", [$user_id]);

		$query = "INSERT INTO `user_tokens` (`id`,`user_id`) VALUES (:id, :user_id)";
		$db::sql($query, [
			'id' => $token,
			'user_id' => $user_id
		]);

		return $token;
	}

	public function create_token()
	{
		$bytes = openssl_random_pseudo_bytes(20, $cstrong);
		$token = bin2hex($bytes);
		return $token;
	}
}
