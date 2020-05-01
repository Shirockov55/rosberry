<?php

class UserController
{
	public $user_id;

	public function __construct($user_id)
	{
		$this->user_id = $user_id;
	}

	public function get_token()
	{
		$token = $this->create_token();

		$db = new DB();
		$db::sql("DELETE FROM `user_tokens` WHERE `user_id` = ?", [$this->user_id]);

		$query = "INSERT INTO `user_tokens` (`id`,`user_id`) VALUES (:id, :user_id)";
		$db::sql($query, [
			'id' => $token,
			'user_id' => $this->user_id
		]);

		return $token;
	}

	public function create_token()
	{
		$bytes = openssl_random_pseudo_bytes(20, $cstrong);
		$token = bin2hex($bytes);
		return $token;
	}

	public function set_coords($data)
	{
		if(!empty($data['lat']) && !empty($data['long']))
		{
			$db = new DB();
			$query = "UPDATE `users` SET `last_coords` = POINT(:lat, :long) WHERE `id` = :id";
			$db::sql($query, [
				//'coords' => $data['lat'] . ' ' . $data['long'],
				'lat' => $data['lat'],
				'long' => $data['long'],
				'id' => $this->user_id
			]);
		}
	}
}
