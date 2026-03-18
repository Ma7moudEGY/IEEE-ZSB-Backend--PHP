<?php

$config = require("config.php");

$db = new Database($config['database']);

$heading = 'Create a note!';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db->query('insert into notes (body, user_id) values (?, ?)', [$_POST['body'], 1]);
}

require "views/notes-create.view.php";