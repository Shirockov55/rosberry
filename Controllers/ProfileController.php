<?php

class ProfileController
{
	protected $data;
	protected $user_id;
	protected $user;
	protected $pag_count = 25;

	function __construct($data)
	{
		$this->data = $data;
		$db = new DB();
		$this->user_id = $db::getValue("SELECT `user_id` FROM `user_tokens` WHERE `id` = ?", [ $data['token'] ]);
		if($this->user_id !== false){
			$this->user = $db::getRow("SELECT * FROM `users` WHERE `id` = ? ", [ $this->user_id ]);
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
			case 'save':
				return $this->save();
				break;
			case 'users_list':
				return $this->users_list();
				break;
		}
	}

	protected function view()
	{
		$db = new DB();
		$age_groups = $db::getRows('SELECT * FROM `age_groups`');
		$interests = $db::getRows('SELECT * FROM `interests`');
		$user_interests = $db::getRows('SELECT * FROM `user_interests` WHERE `user_id` = ?', [ $this->user_id ]);
		return [
			'status' => 'success',
			'user' => $this->user,
			'interests' => $interests,
			'user_interests' => $user_interests,
			'age_groups' => $age_groups
		];
	}

	protected function save()
	{
		$db = new DB();

		$query = 'UPDATE `users` SET
			`name` = :name,
			`email` = :email,
			`photo` = :photo,
			`age` = :age,
			`country` = :country,
			`dont_show_age_group` = :dont_show_age_group,
			`updated_at` = :updated_at
			WHERE `id` = :user_id';

		$arg = [
			'name' => $this->data['name'],
			'email' => $this->data['email'],
			'photo' => $this->data['photo'] ?? '',
			'age' => $this->data['age'] ?? '',
			'country' => $this->data['country'] ?? '',
			'dont_show_age_group' => $this->data['dont_show_age_group'], // 1,3
			'updated_at' => date('Y-m-d H:i:s'),
			'user_id' => $this->user_id
		];

		$db::sql($query, $arg);

		$interests = json_decode($this->data['interests']); // [{"id":1,"value":1},{"id":2,"value":0},{"id":3,"value":1}]
		$interests_values = array_column($interests, 'value', 'id');
		$interests_ids = array_keys($interests_values);

		$old_interests_ids = $db::getColumn('SELECT `interest_id` FROM `user_interests` WHERE `user_id` = ?', [ $this->user_id ]);

		$new_arr = array_diff($interests_ids, $old_interests_ids);
		$delete_arr = array_diff($old_interests_ids, $interests_ids);
		$update_arr = array_diff($interests_ids, $new_arr);

		if(!empty($new_arr)) {
			foreach ($new_arr as $id)
			{
				$query = 'INSERT INTO `user_interests` (`user_id`,`interest_id`,`value`)
					VALUES (:user_id, :interest_id, :value)';
				$db::sql($query, [
					'user_id' => $this->user_id,
					'interest_id' => $id,
					'value' => $interests_values[$id]
				]);
			}
		}

		if (!empty($delete_arr)) {
			foreach ($delete_arr as $id)
			{
				$query = 'DELETE FROM `user_interests` WHERE `user_id` = :user_id AND `interest_id` = :interest_id';
				$db::sql($query, [
					'user_id' => $this->user_id,
					'interest_id' => $id
				]);
			}
		}

		if (!empty($update_arr)) {
			foreach ($update_arr as $id)
			{
				$query = 'UPDATE `user_interests` SET `value` = :value WHERE `user_id` = :user_id AND `interest_id` = :interest_id';
				$db::sql($query, [
					'user_id' => $this->user_id,
					'interest_id' => $id,
					'value' => $interests_values[$id]
				]);
			}
		}

		return [
			'status' => 'success'
		];
	}

	protected function users_list()
	{
		$db = new DB();
		$sql = 'SELECT `id` FROM `age_groups` WHERE (`from` IS NULL AND `to` > :age) OR (`to` IS NULL AND `from` < :age) OR (`from` < :age AND `to` > :age)';
		//die(print_r($this->user['age']));
		$user_age_group = $db::getValue($sql, [ 'age' => $this->user['age'] ]);

		$age_from = $this->data['age_from'];
		$age_to = $this->data['age_to'];

		$sql = 'SELECT * FROM `users` WHERE 1 ';

		// фильтр по возрасту
		if (!empty($age_from) && !empty($age_to)) {
			$sql .= "AND `age` BETWEEN $age_from AND $age_to ";
		}
		else if (!empty($age_from)) {
			$sql .= "AND `age` > $age_from ";
		}
		else if (!empty($age_to)) {
			$sql .= "AND `age` < $age_to ";
		}

		$sql .= "AND `dont_show_age_group` NOT LIKE '%{$user_age_group}%' ";

		//фильтр по интересам
		// [{"id":1,"value":1},{"id":2,"value":0},{"id":3,"value":1}]
		$interests_on = json_decode($this->$data['interests_on']);

		if (!empty($interests_on))
		{
			$inter_sql_on = 'SELECT `user_id` FROM `user_interests` WHERE 1 ';
			foreach ($interests_on as $row) {
				$inter_sql_on .= "AND (`id` = {$row['id']} AND `value` = {$row['value']}) ";
			}
			$interests_user_ids = $db::getColumn($inter_sql_on);
			$interests_user_ids_str = implode(',', $interests_user_ids);

			$sql .= "AND `id` IN ($interests_user_ids_str) ";
		}

		// [{"id":1,"value":1},{"id":2,"value":0},{"id":3,"value":1}]
		$interests_off = json_decode($this->$data['interests_off']);
		if (!empty($interests_off))
		{
			$inter_sql_off = 'SELECT `user_id` FROM `user_interests` WHERE 1 ';
			foreach ($interests_off as $row) {
				$inter_sql_off .= "AND (`id` = {$row['id']} AND `value` = {$row['value']}) ";
			}
			$interests_user_ids = $db::getColumn($inter_sql_off);
			$interests_user_ids_str = implode(',', $interests_user_ids);

			$sql .= "AND `id` NOT IN ($interests_user_ids_str) ";
		}

		$users_list = $db::getRows($sql);

		return [
			'status' => 'success',
			'sql' => $sql,
			'users_list' => $users_list
		];
	}
}
