<?php

class ProfileController
{
	protected $data;
	protected $user_id;
	protected $user;

	function __construct($data)
	{
		$this->data = $data;
		$db = new DB();
		$this->user_id = $db::getValue("SELECT `user_id` FROM `user_tokens` WHERE `id` = ?", [ $data['token'] ]);
		if($this->user_id !== false){
			$this->$user = $db::getRow("SELECT * FROM `users` WHERE `id` = ? ", [ $this->user_id ]);
		}
	}

	public function run($type)
	{
		if($this->user_id === false){
			return [
				'status' => 'error',
				'message' => 'wrong login or password'
			];
		}

		switch ($type) {
			case 'view':
				return $this->view();
				break;
		}
	}

	protected function view()
	{
		return [
			'status' => 'success',
			'user' => $this->$user
		];
	}
}
