<?php

use Core\Database;
use Core\Validator;

require base_path("Validator.php");

$config = require base_path("config.php");

$db = new Database($config['database']);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (! Validator::string($_POST['body'], 1, 1000)) {
        $errors['body'] = 'A body of no more than 1,000 is required';
    }

    if (strlen($_POST['body']) > 1000) {
        $errors['body'] = "The body can't be more than 1,000 characters";
    }

    if (empty($errors)) {
        $db->query('insert into notes (body, user_id) values (?, ?)', [$_POST['body'], 1]);
    }

}

view("notes/create.view.php", [
    'heading' => 'Create a note!',
    'errors' => $errors
]);