<?php

use Core\Validator;
use Core\Database;

$config = require base_path("config.php");

$db = new Database($config['database']);

$errors = [];

if (! Validator::string($_POST['body'], 1, 1000)) {
    $errors['body'] = 'A body of no more than 1,000 is required';
}

if (strlen($_POST['body']) > 1000) {
    $errors['body'] = "The body can't be more than 1,000 characters";
}

if (!empty($errors)) {
    return view("notes/show.view.php", [
        'heading' => 'Note',
        'note' => $note
    ]);
}

$db->query('insert into notes (body, user_id) values (?, ?)', [$_POST['body'], 1]);

header('location: /notes');
die();
