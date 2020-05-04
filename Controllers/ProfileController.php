<?php

class ProfileController
{
	protected $data;
	protected $user_id;
	protected $user;
	protected $pag_count = 20;

	function __construct($data)
	{
		$this->data = $data;
		$db = new DB();

		$this->user_id = $db::getValue("SELECT `user_id` FROM `user_tokens` WHERE `id` = ?", [ $data['token'] ]);

		if($this->user_id !== false){
			$query = 'SELECT *, X(`last_coords`) as lat, Y(`last_coords`) as lon FROM `users` WHERE `id` = ?';
			$this->user = $db::getRow($query, [ $this->user_id ]);
			unset($this->user['last_coords']);
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
		$query = 'SELECT * FROM `user_interests` WHERE `user_id` = ?';
		$user_interests = $db::getRows($query, [ $this->user_id ]);

		$response = [
			'status' => 'success',
			'user' => $this->user,
			'interests' => $interests,
			'user_interests' => $user_interests,
			'age_groups' => $age_groups
		];

		return $response;
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
		// Новые записи
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
		// На удаление
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
		// На обновление
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
		// Возрастная группа пользователя
		$sql = 'SELECT `id` FROM `age_groups` WHERE (`from` IS NULL AND `to` > :age) OR (`to` IS NULL AND `from` < :age) OR (`from` < :age AND `to` > :age)';
		$user_age_group = $db::getValue($sql, [ 'age' => $this->user['age'] ]);

		$location_type = $this->data['location_type']; // world, country, nearby

		$select = 'u.id, u.name, u.email, u.photo, u.age, u.country, u.created_at ';
		$from_and_join = 'FROM `users` as u
			LEFT JOIN `user_interests` as ui ON(ui.user_id = u.id) ';

		$where = "WHERE u.id <> {$this->user_id} ";
		// не показывать профиль возрастной группе
		$where .= "AND (`dont_show_age_group` IS NULL OR `dont_show_age_group` NOT LIKE '%{$user_age_group}%') ";
		// фильтр по возрасту
		$this->set_ages($where);
		// Фильтрация по интересам
		$this->set_interests($where);

		$group = 'GROUP BY u.id ORDER BY u.created_at DESC ';

		if($location_type == 'world')
		{
			$sql = 'SELECT ' . $select . $from_and_join . $where . $group;
		}
		else if($location_type == 'country')
		{
			$sql ='SELECT * FROM ' . '(' .
			'(SELECT 1 as rank, ' . $select . $from_and_join . $where . "AND u.country = '{$this->user['country']}' $group) UNION " .
			'(SELECT 2 as rank, ' . $select . $from_and_join . $where . $group . ')' .
			') AS a ' . 'GROUP BY id ORDER BY rank ASC';
		}
		else if($location_type == 'nearby')
		{
			$lat = $this->user['lat'];
			$lon = $this->user['lon'];
			$max_distance = 50;
			// Выборка дистанции
			$sql_select_coords = ", ((ACOS(
				SIN($lat * PI() / 180) * SIN(X(`last_coords`) * PI() / 180) +
				COS($lat * PI() / 180) * COS(X(`last_coords`) * PI() / 180) *
				COS(($lon - Y(`last_coords`)) * PI() / 180)) * 180 / PI()
			) * 60 * 1.1515 * 1.61) AS `distance` ";

			$group_dop = "GROUP BY u.id HAVING `distance` <= $max_distance ORDER BY `distance` ASC, u.created_at DESC ";

			$sql ='SELECT * FROM ' . '(' .
			'(SELECT 1 as rank, ' . $select . $sql_select_coords . $from_and_join . $where . "$group_dop) UNION " .
			'(SELECT 2 as rank, ' . $select . ', NULL AS `distance` '  . $from_and_join . $where . "AND u.country = '{$this->user['country']}' $group) UNION " .
			'(SELECT 3 as rank, ' . $select . ', NULL AS `distance` ' . $from_and_join . $where . $group . ')' .
			') AS a ' . 'GROUP BY id ORDER BY rank ASC';
		}
		// Пагинация, использующаяся при прокрутке
		if(!empty($this->data['pagination']))
		{
			$pag = $this->data['pagination'] - 1;
			$pag_from = $pag * $this->pag_count;
			$pag_to = $pag_from + $this->pag_count;
			$sql .= " LIMIT $pag_from, $pag_to";
		}

		$users_list = $db::getRows($sql);

		return [
			'status' => 'success',
			'sql' => $sql,
			'users_list' => $users_list
		];
	}

	protected function set_ages(&$where)
	{
		$age_from = $this->data['age_from'];
		$age_to = $this->data['age_to'];

		if (!empty($age_from) && !empty($age_to)) {
			$where .= "AND (`age` BETWEEN $age_from AND $age_to) ";
		}
		else if (!empty($age_from)) {
			$where .= "AND `age` >= $age_from ";
		}
		else if (!empty($age_to)) {
			$where .= "AND `age` <= $age_to ";
		}
	}

	protected function set_interests(&$sql)
	{
		$db = new DB();
		//фильтр показывать профили с интересами
		// [{"id":1,"value":1},{"id":2,"value":0},{"id":3,"value":1}]
		$interests_on = json_decode($this->data['interests_on']);
		if (!empty($interests_on))
		{
			// Выбираем id пользователей с соответств. интересами
			$sql .= 'AND ' . '(';
			$c = 0;
			$count_on = count($interests_on);
			foreach ($interests_on as $row) {
				$sql .= "(ui.interest_id = {$row->id} AND ui.value = {$row->value}) ";
				if($count_on != $c + 1){
					$sql .= 'OR ';
				}
				$c++;
			}
			$sql .= ') ';
		}

		//фильтр не показывать профили с интересами
		// [{"id":1,"value":1},{"id":2,"value":0},{"id":3,"value":1}]
		$interests_off = json_decode($this->data['interests_off']);
		if (!empty($interests_off))
		{
			// Выбираем id пользователей с соответств. интересами
			$sql .= 'AND ' . '(';
			$c = 0;
			$count_off = count($interests_off);
			foreach ($interests_off as $row) {
				$sql .= "(ui.interest_id IS NULL OR (ui.interest_id = {$row->id} AND ui.value <> {$row->value})) ";
				if($count_off != $c + 1){
					$sql .= 'OR ';
				}
				$c++;
			}
			$sql .= ') ';
		}
	}
	// 2й вариант не используемый
	protected function set_interests2(&$sql)
	{
		$db = new DB();
		//фильтр показывать профили с интересами
		// [{"id":1,"value":1},{"id":2,"value":0},{"id":3,"value":1}]
		$interests_on = json_decode($this->data['interests_on']);
		if (!empty($interests_on))
		{
			// Выбираем id пользователей с соответств. интересами
			$inter_sql_on = 'SELECT `user_id` FROM `user_interests` WHERE ';
			$c = 0;
			foreach ($interests_on as $row) {
				$inter_sql_on .= "(`interest_id` = {$row->id} AND `value` = {$row->value}) ";
				if(count($interests_on) != $c + 1){
					$inter_sql_on .= 'OR ';
				}
				$c++;
			}

			$interests_user_ids = $db::getColumn($inter_sql_on);
			$interests_user_ids_str = implode(',', $interests_user_ids);
   			if(!empty($interests_user_ids_str)){
				$sql .= "AND `id` IN ($interests_user_ids_str) ";
			}
		}

		//фильтр не показывать профили с интересами
		// [{"id":1,"value":1},{"id":2,"value":0},{"id":3,"value":1}]
		$interests_off = json_decode($this->data['interests_off']);
		if (!empty($interests_off))
		{
			// Выбираем id пользователей с соответств. интересами
			$inter_sql_off = 'SELECT `user_id` FROM `user_interests` WHERE 1 ';
			foreach ($interests_off as $row) {
				$inter_sql_off .= "AND (`id` = {$row->id} AND `value` = {$row->value}) ";
			}
			$interests_user_ids = $db::getColumn($inter_sql_off);
			$interests_user_ids_str = implode(',', $interests_user_ids);

			if(!empty($interests_user_ids_str)){
				$sql .= "AND `id` NOT IN ($interests_user_ids_str) ";
			}
		}
	}
}
