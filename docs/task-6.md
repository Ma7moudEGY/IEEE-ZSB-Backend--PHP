# PHP Website Security & Hacking Protection — Notes

---

## Section 1: Refactoring with Object-Oriented Programming

> **Key Takeaway:** Wrapping queries inside class methods removes raw SQL from page files, makes vulnerabilities easier to find, and creates a single place to apply security fixes.

---

## 1. Why OOP Improves Security

Writing queries directly in page files means that any variable passed into them could be unsafe. The goal of the OOP refactor is to **abstract all query logic** into classes so that:

- Queries are uniform and predictable — you always know which table a class reads from.
- If unexpected data appears, you can immediately identify which function and class to inspect.
- Security patches are applied in one place and take effect everywhere.

---

## 2. The Class Structure

Create a `Database` base class that holds connection and reading logic. All other classes extend it.

```php
class Database
{
    private function connect(): PDO
    {
        // returns PDO connection
    }

    public function db_read(string $query): array|false
    {
        // executes query and returns rows
    }

    public function db_write(string $query): bool
    {
        // executes INSERT/UPDATE/DELETE, returns true/false
    }
}
```

Why separate `Database` from `Posts` and `User`? Because both `Posts` and `User` need to connect to the database. Rather than duplicating the `connect()` function in every class, each one simply extends `Database` and inherits it.

```php
class Posts extends Database
{
    // ...
}

class User extends Database
{
    // ...
}
```

**Naming convention:** Class names use capital letters to distinguish them from regular functions and variables.

---

## 3. Visibility: `private` vs `public`

| Keyword | When to use |
|---------|-------------|
| `private` | Functions used only internally, such as `connect()`. This prevents outside code from calling the connection directly. |
| `public` | Functions that page files need to call, such as `db_read()` or `get_home_posts()`. |

---

## 4. Calling Internal Methods with `$this`

Inside a class, any call to another method in the **same class** (or a parent class via `extends`) must use the `$this` keyword:

```php
public function get_home_posts(): array|false
{
    $query = 'SELECT * FROM posts ORDER BY id LIMIT 2';
    return $this->db_read($query);
}
```

Forgetting `$this` produces an "undefined function" error because PHP looks for a global function instead of a class method.

---

## 5. One Method per Query Type

Create a dedicated method for each distinct query. This keeps responsibilities narrow:

```php
class Posts extends Database
{
    public function get_home_posts(): array|false
    {
        return $this->db_read('SELECT * FROM posts ORDER BY id LIMIT 2');
    }

    public function get_all_posts(): array|false
    {
        return $this->db_read('SELECT * FROM posts ORDER BY id');
    }

    public function get_one_post(int $id): array|false
    {
        return $this->db_read("SELECT * FROM posts WHERE id = {$id} LIMIT 1");
    }
}
```

Page files become minimal — two lines replace an entire query block:

```php
$post  = new Posts();
$result = $post->get_home_posts();
```

---

## 6. Security Benefit

When code is fragmented into small, named methods:

- You know exactly which function to inspect when something goes wrong.
- There are fewer places where raw, unsanitised variables can slip into a query.
- Closing a vulnerability in one method fixes it for every page that calls it.

---

## Section 2: Login Error Messaging

> **Key Takeaway:** Never reveal which of the two login fields — email or password — is incorrect. Combine both into one generic message.

---

## 1. The Problem with Specific Error Messages

A login form that returns `"Wrong password"` when the email is correct, and `"Wrong email"` when the password is correct, hands an attacker a free enumeration tool:

1. They try a target's email with a random password.
2. If they see `"Wrong password"`, they know the email is valid — half the job is done.
3. They then focus exclusively on cracking the password.

---

## 2. The Fix

Replace every specific error with one generic message:

```php
$error = 'Wrong email or password.';
```

This message must appear regardless of which field failed — including edge cases where neither field was submitted at all. Add an `else` branch to cover the empty-submission path:

```php
if (/* login logic */) {
    // success
} else {
    $error = 'Wrong email or password.';
}
```

---

## 3. Centralising Session Start

`session_start()` is required on every page that checks login state. Rather than adding it to each file individually, move it to the single entry point (`index.php`):

```php
// public/index.php
session_start();
require('../private/includes/functions.php');
```

Remove `session_start()` from all individual page files (`login.php`, `signup.php`, etc.).

---

## 4. Validating Email Format

PHP's `filter_var()` provides a reliable server-side email check. Place it inside the login function in `functions.php`, not in the HTML form — a browser-level `type="email"` check can be bypassed by editing the page in DevTools:

```php
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    return 'Wrong email or password.';
}
```

The `!` prefix means: "if this is **not** a valid email, reject it."

---

## 5. Returning Errors from Functions

Functions should **return** error strings rather than echo them or call `header()` directly. The calling page handles redirection:

```php
// In the User class
public function login(array $post): string
{
    // validation...
    if (/* credentials match */) {
        $_SESSION['user_id'] = $row['id'];
        return ''; // empty string = success
    }
    return 'Wrong email or password.';
}

// In login.php
$user  = new User();
$error = $user->login($_POST);

if ($error === '') {
    header('Location: index.php');
    exit;
}
```

---

## Section 3: Least Privilege

> **Key Takeaway:** Give every user the minimum access they need — nothing more. This limits the damage that can be done if an account is compromised or misused.

---

## 1. What Is the Principle of Least Privilege?

If you need someone to post content on your behalf, make them an **editor** — not an admin. If they leave your organisation one day, the damage they can do is limited to what the editor role can access.

Typical access levels, from lowest to highest:

- **User** — the basic requirement for being logged in.
- **Editor** — can create and edit content.
- **Supervisor** — additional management permissions.
- **Admin** — full access to everything.

---

## 2. Adding a `rank` Column

Add a `rank` column (varchar, ~20 characters) to the `users` table in your database. Assign each user a rank: for example, `admin` for John and `editor` for Mary.

When a user logs in, store their rank in the session alongside their ID:

```php
$_SESSION['user_id']   = $row['id'];
$_SESSION['user_rank'] = $row['rank'];
```

---

## 3. The `access()` Function

Create a standalone `access()` function in `functions.php` that accepts the required rank for a piece of content and returns `true` or `false`:

```php
function access(string $needed_rank): bool
{
    $user_rank = isset($_SESSION['user_rank']) ? $_SESSION['user_rank'] : '';

    switch ($needed_rank) {
        case 'admin':
            $allowed = ['admin'];
            break;
        case 'editor':
            $allowed = ['admin', 'editor'];
            break;
        case 'user':
            $allowed = ['admin', 'editor', 'user'];
            break;
        default:
            return false;
    }

    return in_array($user_rank, $allowed);
}
```

**Key logic:** Higher ranks inherit all lower-rank permissions. An admin can access anything an editor or user can access, but not vice versa.

---

## 4. Restricting Content

Wrap any restricted content in a simple `if` block:

```php
if (access('admin')) {
    // show posts, admin panel, etc.
} else {
    echo 'Sorry, you do not have access.';
}
```

To require only a basic login, pass `'user'` as the argument. To require editorial permissions, pass `'editor'`. No query or session manipulation is needed on the page itself — the `access()` function handles everything.

---

## Section 4: POST SQL Injection

> **Key Takeaway:** Never trust data submitted via a form. An attacker can manipulate any POST variable to inject SQL into your queries.

---

## 1. How POST Injection Works

An attacker can type raw SQL fragments into a login form. Consider this query:

```sql
SELECT * FROM users WHERE email = '$email' AND password = '$password'
```

If `$email` is set to `john@yahoo.com' OR 1=1 --`, the resulting query becomes:

```sql
SELECT * FROM users WHERE email = 'john@yahoo.com' OR 1=1 --' AND password = '...'
```

Because `1=1` is always true, the `WHERE` clause is satisfied for every row. The attacker is logged in without a valid password.

---

## 2. Protection Layers

Security is multi-layered. If an attacker breaks through one layer, the next one stops them.

**Layer 1 — Validate the data type.** Use `filter_var()` to confirm the email is a legitimate email address. If the injected string is not a valid email format, it is rejected before it ever touches the database:

```php
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    return 'Wrong email or password.';
}
```

**Layer 2 — Escape special characters with `addslashes()`.** This adds a backslash before characters like single quotes, double quotes, and backslashes. A quote that is part of an injection attempt becomes a literal character rather than a SQL delimiter:

```php
$email    = addslashes($email);
$password = addslashes($password);
```

Always wrap query variables in quotes inside the SQL string so that `addslashes()` has something to work with:

```sql
WHERE email = '$email' AND password = '$password'
```

**Layer 3 — Type-cast numeric values.** For any field expected to be an integer, casting eliminates any text outright:

```php
$id = (int) $_GET['id'];
```

If `$_GET['id']` contains `4 UNION SELECT ...`, casting it to `int` strips everything after the first number and leaves only `4`.

**Layer 4 — Prepared statements** (covered in Section 12). This is the strongest protection of all.

---

## 3. What `addslashes()` Does

```php
$a = "john's name";
echo addslashes($a); // john\'s name
```

The backslash tells the database engine that the apostrophe is a **literal character**, not the end of the string. Any code after it remains part of the value, not part of the SQL structure.

---

## Section 5: GET SQL Injection

> **Key Takeaway:** URL parameters are just as dangerous as form inputs. Any value you pass into a query via `$_GET` must be sanitised.

---

## 1. How GET Injection Works

A URL like `post.php?id=4` passes `4` to a query:

```sql
SELECT * FROM posts WHERE id = 4 LIMIT 1
```

An attacker can replace `4` with any text. Adding `ORDER BY 1` to the URL still returns a result, confirming the site is vulnerable. By incrementing the column number (`ORDER BY 4`, `ORDER BY 5`, etc.) they can count the number of columns in the table.

---

## 2. Extracting Data with UNION

Once the column count is known, a `UNION SELECT` appends a second query to the first:

```
?id=4 AND 0 UNION SELECT 1,2,3,4
```

The `AND 0` makes the original query return nothing. The `UNION SELECT` fills in, returning attacker-controlled values. By replacing `3` with `user()` or `version()`, the attacker reads internal database information directly on the page.

---

## 3. Reading Server Files via SQL

MySQL's `LOAD_FILE()` function can read any server file the database user has permission to access:

```sql
UNION SELECT 1,2,LOAD_FILE('C:/xampp/php/php.ini'),4
```

If error reporting is enabled, error messages reveal full file paths, making it easier for an attacker to identify and read sensitive configuration files. It is also theoretically possible to write files to the server using `INTO OUTFILE`.

---

## 4. Defences

**Cast to integer.** For an ID parameter, this is the simplest and most effective fix:

```php
$id = (int) $_GET['id'];
```

Everything after the first digit is discarded. The entire injection string is nullified in one line.

**Use `addslashes()` with quoted variables.** If the value is not a simple integer, wrapping it in quotes inside the SQL and running `addslashes()` prevents the injection from escaping the string context:

```php
$id    = addslashes($_GET['id']);
$query = "SELECT * FROM posts WHERE id = '$id' LIMIT 1";
```

**Use prepared statements.** This is covered in the next section and is the most robust solution.

---

## Section 6: Prepared Statements

> **Key Takeaway:** Prepared statements separate the SQL query from the data that fills it. This makes SQL injection structurally impossible — values are never evaluated as SQL code.

---

## 1. What Is a Prepared Statement?

In a normal query, user-supplied values are concatenated directly into the SQL string. A prepared statement uses **placeholders** instead:

```sql
SELECT * FROM users WHERE id = :id
```

The query is sent to the database first. The database compiles it. Then the actual value for `:id` is sent separately. Because they arrive in two distinct steps, the database already knows the structure of the query — it cannot be altered by what arrives in the data step.

---

## 2. Why PDO over MySQLi

PDO (PHP Data Objects) is preferred for two reasons:

- It works with multiple database engines (MySQL, PostgreSQL, SQLite, etc.) using the same API.
- Its prepared-statement syntax is simpler and more consistent than MySQLi's.

MySQLi prepared statements do exist, but they are tied exclusively to MySQL.

---

## 3. Converting the `connect()` Function to PDO

```php
private function connect(): PDO
{
    $dsn = 'mysql:host=localhost;dbname=security_db';

    try {
        $con = new PDO($dsn, 'root', '');
        return $con;
    } catch (PDOException $e) {
        if ($_SERVER['HTTP_HOST'] === 'localhost') {
            die($e->getMessage());
        } else {
            die('Could not connect to database.');
        }
    }
}
```

The `try/catch` block separates localhost (where full error messages are safe for debugging) from live servers (where a vague message is shown to avoid leaking configuration details).

---

## 4. Updating `db_read()` to Use Prepared Statements

```php
public function db_read(string $query, array $data = []): array|false
{
    $con       = $this->connect();
    $statement = $con->prepare($query);

    if ($statement) {
        $check = $statement->execute($data);

        if ($check) {
            $result = $statement->fetchAll(PDO::FETCH_ASSOC);

            if (is_array($result) && count($result) > 0) {
                return $result;
            }
        }
    }

    return false;
}
```

Key points:
- `$data` defaults to an empty array so queries with no variables still work.
- `fetchAll(PDO::FETCH_ASSOC)` returns rows as associative arrays, matching the previous behaviour.
- If anything fails, the function returns `false`.

---

## 5. Using Placeholders in Queries

Queries that include user-supplied values must use named placeholders:

```php
public function get_one_post(int $id): array|false
{
    $query = 'SELECT * FROM posts WHERE id = :id LIMIT 1';
    $data  = [':id' => $id];
    return $this->db_read($query, $data);
}
```

The number of entries in `$data` must match the number of placeholders in the query exactly. A mismatch produces an "Invalid parameter number" error.

---

## 6. Updating `db_write()` to Use Prepared Statements

```php
public function db_write(string $query, array $data = []): bool
{
    $con       = $this->connect();
    $statement = $con->prepare($query);

    if ($statement) {
        return (bool) $statement->execute($data);
    }

    return false;
}
```

---

## 7. The Power of a Single Entry Point

Because every database interaction flows through `db_read()` and `db_write()` in the `Database` class, converting the entire website to prepared statements required changing **only those two methods**. No individual page file had to be touched. This is the core benefit of centralised, object-oriented database access.

---

## Section 7: Cross-Site Scripting (XSS)

> **Key Takeaway:** Never output data from the database (or from any user-supplied source) without sanitising it first. Even data that passed through prepared statements can contain dangerous HTML or JavaScript.

---

## 1. What Is XSS?

Cross-site scripting (XSS) is one of the most common web vulnerabilities. An attacker injects HTML or JavaScript into a field your site accepts — a name, a password, a post body — and that code runs in the browser of anyone who views it.

PHP code injected this way is usually handled safely by the server (it is not re-executed once stored), but **JavaScript is rendered directly by the browser** and will execute on page load.

---

## 2. How an Attack Unfolds

An attacker signs up with a JavaScript payload as their password:

```html
<script>alert(document.cookie)</script>
```

Prepared statements store this string safely in the database. When the user's profile page is loaded and the password is echoed without sanitisation, the browser sees a `<script>` tag and executes it. The attacker's profile page now displays the victim's session cookie — which can be sent to a remote server to hijack the session.

This is called **session hijacking**. Once an attacker has your PHP session ID, they can impersonate you without ever knowing your password.

---

## 3. Why Prepared Statements Are Not Enough

Prepared statements prevent SQL injection — they keep malicious strings from altering the database query. But they store the string **exactly as entered**. When you later echo that stored string into an HTML page, the browser interprets it. Prepared statements offer no protection at the output stage.

---

## 4. The Fix: `htmlspecialchars()`

Wrap every value from the database (or from any user-supplied source) in `htmlspecialchars()` before echoing it:

```php
echo htmlspecialchars($row['password']);
```

This converts characters like `<`, `>`, `"`, and `'` into their HTML entity equivalents (`&lt;`, `&gt;`, `&quot;`, `&#039;`). The browser displays them as literal text rather than interpreting them as markup or code.

`htmlentities()` is a similar function that converts a wider range of characters. Either works; `htmlspecialchars()` is the more commonly used choice for general output.

---

## 5. Creating a `clean()` Helper Function

Because output sanitisation is needed throughout the site, wrap `htmlspecialchars()` in a dedicated function:

```php
function clean(string $data): string
{
    return htmlspecialchars($data);
}
```

Use it wherever user-supplied data is echoed:

```php
echo clean($row['name']);
echo clean($row['email']);
echo clean($row['password']);
```

**Why a wrapper function?** If you later decide to change the sanitisation method — perhaps adding strip-tags or a different encoding — you change one function and the protection updates everywhere on the site instantly. If you had used `htmlspecialchars()` directly in dozens of places, you would have to find and update every single occurrence.

---

## 6. The Rule to Remember

> **Do not trust user-supplied data even when it is in your own database.**

Data that was stored safely via prepared statements can still be dangerous at display time. Every value that originates from user input — whether it comes from a form, a URL, a file upload, or the database — must pass through `clean()` before being echoed to the browser.

---
