<?php

$config = require("config.php");

$db = new Database($config['database']);

$heading = 'Create a note!';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];

    if (strlen($_POST['body']) === 0) {
        $errors['body'] = 'A body is required';
    }

    if (strlen($_POST['body']) > 1000) {
        $errors['body'] = "The body can't be more than 1,000 characters";
    }

    if (empty($errors)) {
        $db->query('insert into notes (body, user_id) values (?, ?)', [$_POST['body'], 1]);
    }

}

require "views/notes-create.view.php";