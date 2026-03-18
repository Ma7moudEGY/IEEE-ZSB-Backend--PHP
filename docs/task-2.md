# IEEE ZSB Backend Phase 2 — PHP Project Documentation

---

## Section 4: Notes App Development & Security Fundamentals

> **Objective:** Build a practical note-taking application while introducing relational databases, routing for dynamic pages, user authorization, form handling, and critical security concepts (XSS prevention and server-side validation).

---

## 1. Database Relations & Foreign Keys

When building a notes app, notes don't exist in isolation; they belong to specific users. This requires a relational database structure using **Foreign Keys**.

**Users Table:** Contains user data (`id`, `name`, `email`). The `email` column uses a Unique Index to guarantee no two users can register with the same email.

**Notes Table:** Contains the note data (`id`, `body`) and a `user_id` column. The `user_id` is a Foreign Key that references the `id` column on the `users` table. We also apply a **Cascade on Delete** constraint. If a user is deleted from the database, all of their associated notes are automatically deleted, preventing "orphaned" records and maintaining database consistency.

---

## 2. Dynamic Routing & The `$_GET` Superglobal

To view a specific note, we pass its identifier through the URL's query string, like `/note?id=1`. PHP captures this data in the **`$_GET`** superglobal array.

```php
<?php
// Extracting the ID from the query string
$id = $_GET['id'];

// Using a prepared statement to safely fetch the specific note
$note = $db->query('SELECT * FROM notes WHERE id = :id', [
    'id' => $id
])->fetch(); // Use fetch() instead of fetchAll() for a single record
?>
```

> **Note:** Always use `fetch()` when retrieving a single record, as it returns a flat associative array rather than an array of arrays (like `fetchAll()` does).

---

## 3. Authorization & Status Codes

**Authentication** verifies who you are, but **Authorization** verifies what you are allowed to do.

If User A tries to change the URL to `/note?id=2` (a note owned by User B), the database will successfully find the note. Without authorization, User A could read User B's private data.

```php
<?php
// Check if the current user owns the note
if ($note['user_id'] !== $currentUserId) {
    abort(Response::FORBIDDEN); // 403
}
?>
```

### 3.1 Status Codes Explained

| Code | Description                                                               |
| ---- | ------------------------------------------------------------------------- |
| 404  | **Not Found** — The record does not exist in the database (e.g., `/note?id=999999`) |
| 403  | **Forbidden** — The record exists, but the current user lacks permission to access it |

### 3.2 Refactoring for Cleaner Controllers

To avoid rewriting these checks in every controller, we extract them into reusable helpers:

```php
<?php
// Database.php - Encapsulates the 404 logic
public function findOrFail() {
    $result = $this->find();
    if (! $result) {
        abort(); // Defaults to 404
    }
    return $result;
}

// functions.php - Encapsulates the 403 logic
function authorize($condition, $status = Response::FORBIDDEN) {
    if (! $condition) {
        abort($status);
    }
}
?>
```

Now, the controller logic becomes highly readable:

```php
<?php
$note = $db->query('SELECT * FROM notes WHERE id = :id', ['id' => $_GET['id']])->findOrFail();
authorize($note['user_id'] === $currentUserId);
?>
```

---

## 4. Forms & The `$_POST` Superglobal

When submitting data to the server (like creating a new note), we must use a POST request rather than GET. GET requests should be **idempotent** — they don't change server state. Creating a note alters the database, so `<form method="POST">` is required.

```html
<form method="POST" action="/notes/create">
    <textarea name="body" required></textarea>
    <button type="submit">Create Note</button>
</form>
```

When submitted, the data is not placed in the URL. Instead, it is sent in the request body and accessed in PHP via the **`$_POST`** superglobal.

```php
<?php
// Checking if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = $_POST['body'];
    // ... insert into database
}
?>
```

> **Crucial Rule:** HTML inputs must have a `name` attribute (e.g., `name="body"`), otherwise their data will not be included in the `$_POST` array.

---

## 5. Security: Preventing XSS Attacks

You must assume **all user input is malicious**. If a user types HTML or JavaScript into your form (e.g., `<script>alert('Hacked!');</script>`), and you output it directly to the page, that script will execute for every visitor. This is called **Cross-Site Scripting (XSS)**.

To prevent this, always escape user-provided data before rendering it in HTML using `htmlspecialchars()`.

```php
<!-- DANGEROUS: Executes scripts -->
<li> <?= $note['body'] ?> </li>

<!-- SAFE: Converts special characters to HTML entities -->
<li> <?= htmlspecialchars($note['body']) ?> </li>
```

`htmlspecialchars()` converts characters like `<` and `>` into safe HTML entities (`&lt;` and `&gt;`), rendering them as harmless text instead of executable code.

---

## 6. Server-Side Data Validation

While you can add a `required` attribute to an HTML input (**Client-Side Validation**), a malicious user can easily bypass this using browser developer tools or terminal commands (like `cURL`). **Server-side validation is non-negotiable.**

```php
<?php
$errors = [];

// Check if the body is empty
if (strlen(trim($_POST['body'])) === 0) {
    $errors['body'] = 'A body is required.';
}

// Check for a maximum character limit
if (strlen($_POST['body']) > 1000) {
    $errors['body'] = 'The body cannot be more than 1,000 characters.';
}

// If errors exist, return them to the view. If not, insert into the DB.
if (! empty($errors)) {
    return view('notes/create.view.php', ['errors' => $errors]);
}
?>
```

### 6.1 Refactoring into a Validator Class

To prevent duplication across multiple forms, validation logic should be extracted into a dedicated **Validator class** utilizing pure functions (static methods that do not rely on outside state).

```php
<?php
// core/Validator.php

class Validator {
    public static function string($value, $min = 1, $max = INF) {
        $value = trim($value);
        return strlen($value) >= $min && strlen($value) <= $max;
    }
    
    public static function email($value) {
        return filter_var($value, FILTER_VALIDATE_EMAIL);
    }
}
?>
```

Now, the controller can use this cleanly:

```php
<?php
if (! Validator::string($_POST['body'], 1, 1000)) {
    $errors['body'] = 'A body of no more than 1,000 characters is required.';
}
?>
```
