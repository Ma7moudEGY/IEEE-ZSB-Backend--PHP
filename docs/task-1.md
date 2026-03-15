# IEEE ZSB Backend Phase 2 — PHP Project Documentation

---

## Section 0: PHP Fundamentals

> **Objective:** Build a working understanding of PHP basics before moving into the project structure. Topics include variables, output, strings, booleans, conditionals, arrays, loops, and functions.

---

## 1. Variables & Output

PHP variables start with a `$` sign and follow camelCase naming. Output is done with either `echo` or `print`.

**The difference:**

- `echo` can take multiple comma-separated values, returns nothing, and is marginally faster.
- `print` takes a single argument and returns `1`, so it can appear inside expressions.

```php
<?php
$message = "Hello, World!";

echo $message;         // Using echo
print $message;        // Using print

echo "Hello", ", ", "World!";  // echo with multiple values
?>

<?= $message ?>        // Shorthand — equivalent to <?php echo $message; ?>
```

> `<?= $message ?>` is a cleaner way to embed PHP output in HTML templates, avoiding the full `echo` syntax.

---

## 2. String Concatenation

The dot (`.`) operator joins strings together in PHP.

```php
<?php
$name     = "Ahmed";
$greeting = "Hello, " . $name . "!";

echo $greeting; // Hello, Ahmed!
?>
```

---

## 3. Booleans & Conditionals

PHP has standard `true` / `false` values and supports the typical conditional structures.

```php
<?php
$isLoggedIn = true;

if ($isLoggedIn) {
    echo "Welcome back!";
} else {
    echo "Please log in.";
}

// Ternary shorthand
$message = $isLoggedIn ? "Welcome back!" : "Please log in.";
echo $message;
?>
```

---

## 4. Arrays

### 4.1 Indexed Arrays

Values are stored in order and accessed by numeric index, starting from `0`.

```php
<?php
$books = [
    ["Design Patterns", 1994, "Gang of Four"],
    ["You Don't Know JS", 2015, "Kyle Simpson"],
    ["The Linux Command Line", 2012, "William Shotts"],
];

echo $books[0][0]; // Design Patterns
echo $books[0][1]; // 1994
echo $books[0][2]; // Gang of Four
?>
```

### 4.2 Associative Arrays

Instead of numeric indexes, each value is accessed by a named string key — similar to maps in other languages.

```php
<?php
$books = [
    [
        "title"       => "Design Patterns",
        "releaseYear" => 1994,
        "author"      => "Gang of Four",
    ],
    [
        "title"       => "You Don't Know JS",
        "releaseYear" => 2015,
        "author"      => "Kyle Simpson",
    ],
    [
        "title"       => "The Linux Command Line",
        "releaseYear" => 2012,
        "author"      => "William Shotts",
    ],
];

echo $books[0]["title"];  // Design Patterns
echo $books[1]["author"]; // Kyle Simpson
?>
```

---

## 5. Loops

### 5.1 `foreach` Loop

The standard way to iterate over arrays in PHP.

```php
<?php
foreach ($books as $book) {
    echo $book["title"] . " by " . $book["author"] . " (" . $book["releaseYear"] . ")\n";
}
?>
```

**Output:**

```
Design Patterns by Gang of Four (1994)
You Don't Know JS by Kyle Simpson (2015)
The Linux Command Line by William Shotts (2012)
```

### 5.2 `foreach` in HTML Templates

The alternative `foreach` syntax pairs cleanly with `<?= ?>` for readable, logic-free templates.

```php
<?php foreach ($books as $book) : ?>
    <li><?= $book["title"] ?> — <?= $book["author"] ?></li>
<?php endforeach; ?>
```

> This style keeps PHP logic and HTML markup clearly separated, which is especially valuable in view templates.

---

## 6. Functions

### 6.1 Named Function

Declared with the `function` keyword and callable from anywhere in scope.

```php
<?php
function filterByAuthor(array $books, string $author): array
{
    $result = [];

    foreach ($books as $book) {
        if ($book["author"] === $author) {
            $result[] = $book;
        }
    }

    return $result;
}

$simpsonBooks = filterByAuthor($books, "Kyle Simpson");
echo $simpsonBooks[0]["title"]; // You Don't Know JS
?>
```

### 6.2 Anonymous Function

Has no name and is assigned to a variable. Useful for inline or one-off logic.

```php
<?php
$filterByYear = function (array $books, int $year): array {
    $result = [];

    foreach ($books as $book) {
        if ($book["releaseYear"] > $year) {
            $result[] = $book;
        }
    }

    return $result;
};

$recentBooks = $filterByYear($books, 2000);
?>
```

### 6.3 `array_filter` with a Callback

`array_filter` takes an array and a callback, returning only elements for which the callback returns `true`. This makes it easy to build flexible, reusable filters.

```php
<?php
$filteredBooks = array_filter($books, function($book) {
    return $book['releaseYear'] > 2000;
});

foreach ($filteredBooks as $book) {
    echo $book["title"] . "\n";
}
?>
```

---

## Section 1: Project Structure — Views, Partials & Routing Helpers

> **Objective:** Introduce a clean separation between page logic and HTML templates.

---

## 1. The Problem This Solves

Without any structure, every page would repeat the same HTML boilerplate — `<head>`, navigation, footer — mixed in with its own logic. A change to the navbar would mean editing every file.

The structure here solves this with two principles:

1. **Logic vs. template** — each page has a logic file (`index.php`) and a view file (`index.view.php`).
2. **Shared vs. unique** — repeated HTML fragments are extracted into `partials/` and included wherever needed.

---

## 2. Project File Structure (Initial)

```
project/
│
├── index.php           ← Sets $heading, requires view
├── about.php
├── contact.php
│
├── functions.php       ← Shared helpers (e.g. urlIs)
│
└── views/
    ├── index.view.php  ← Assembles partials + unique content
    ├── about.view.php
    ├── contact.view.php
    │
    └── partials/
        ├── header.php  ← <head>, meta tags, CSS links
        ├── nav.php     ← Navigation bar
        ├── banner.php  ← Page heading banner
        └── footer.php  ← Closing scripts, </body>, </html>
```

---

## 3. Page Logic File — `index.php`

Each logic file has one job: prepare data for the view, then hand off using `require`.

```php
<?php

$heading = 'Home';

require 'views/index.view.php';
```

- `$heading` is declared here and becomes available inside the view, because `require` shares the same variable scope.
- The same pattern repeats for `about.php` and `contact.php`, keeping each file minimal.

> `require` vs `include`: both insert a file at the call site, but `require` throws a fatal error if the file is missing while `include` only warns. For essential files, always use `require`.

---

## 4. View File — `views/index.view.php`

The view is a pure template. It assembles the page from partials and adds only the content unique to that page.

```php
<?php require('partials/header.php'); ?>
<?php require('partials/nav.php'); ?>
<?php require('partials/banner.php'); ?>

<main>
    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <p>Welcome to the Homepage</p>
    </div>
</main>

<?php require('partials/footer.php'); ?>
```

- No business logic lives here — only structure.
- `$heading` set in `index.php` is already in scope when `banner.php` runs.

---

## 5. Partials

### 5.1 `banner.php` — Page Heading

Uses `$heading` to show the correct title on every page without duplication.

```php
<header class="relative bg-white shadow-sm">
    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold tracking-tight text-gray-900">
            <?= $heading ?>
        </h1>
    </div>
</header>
```

### 5.2 `nav.php` — Active State with `urlIs()`

The nav uses `urlIs()` to apply an active CSS class to the current page's link.

```php
<a href='/'
    class="rounded-md px-3 py-2 text-sm font-medium
        <?= urlIs('/') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-white/5 hover:text-white' ?>">
    Home
</a>

<a href='/about'
    class="rounded-md px-3 py-2 text-sm font-medium
        <?= urlIs('/about') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-white/5 hover:text-white' ?>">
    About
</a>

<a href='/contact'
    class="rounded-md px-3 py-2 text-sm font-medium
        <?= urlIs('/contact') ? 'bg-gray-900 text-white' : 'text-gray-300 hover:bg-white/5 hover:text-white' ?>">
    Contact
</a>
```

The ternary inside `<?= ?>` outputs one of two Tailwind class strings based on whether the current URL matches.

---

## 6. The `$_SERVER` Superglobal & `urlIs()`

### 6.1 What is `$_SERVER`?

`$_SERVER` is a **superglobal** — a built-in PHP array available in every scope without being declared or passed. PHP fills it with server and request information automatically.

Relevant keys:

| Key                          | Description                                | Example value    |
| ---------------------------- | ------------------------------------------ | ---------------- |
| `$_SERVER['REQUEST_URI']`    | Full path + query string of the request    | `/about?ref=nav` |
| `$_SERVER['PHP_SELF']`       | Path to the currently executing script     | `/index.php`     |
| `$_SERVER['HTTP_HOST']`      | Domain name from the request               | `localhost`      |
| `$_SERVER['REQUEST_METHOD']` | HTTP method used                           | `GET`, `POST`    |

For active nav links, `REQUEST_URI` is the right key — it reflects exactly what the user navigated to.

### 6.2 `urlIs()` — `functions.php`

```php
<?php

function urlIs(string $value): bool
{
    return $_SERVER['REQUEST_URI'] === $value;
}
```

Compares the current URI against an expected path. Returns `true` on match, `false` otherwise. The result is used directly in the ternary expressions in `nav.php`.

### 6.3 Query Strings Consideration

`REQUEST_URI` includes the full query string, so `/about?ref=email` would **not** match `urlIs('/about')`. For this project, clean URLs are used and this is not an issue. For a more robust implementation:

```php
function urlIs(string $value): bool
{
    return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === $value;
}
```

---

## 7. How the Pieces Connect

```
Browser requests /about
        │
        ▼
about.php
  └─ sets $heading = 'About'
  └─ require 'views/about.view.php'
            │
            ├─ require 'partials/header.php'
            ├─ require 'partials/nav.php'    ← urlIs('/about') → true → active class applied
            ├─ require 'partials/banner.php' ← outputs $heading = 'About'
            ├─ <main> ... unique content ... </main>
            └─ require 'partials/footer.php'
```

---

## Section 2: Building a Router

> **Objective:** Replace direct file access with a centralized routing system.

---

## 1. The Problem This Solves

Previously, pages were reached by navigating directly to PHP files — `/about.php`, `/contact.php`, etc. This has real problems:

- The file structure is exposed to the browser.
- There is no central place to intercept or validate requests.
- Unmatched URLs produce uncontrolled server errors.

A router fixes this by directing **every request through one entry point**, which decides what to load.

---

## 2. Project File Structure (Updated)

```
project/
│
├── index.php           ← Single entry point — requires router.php
├── router.php          ← Maps URIs to controllers, handles 404
├── functions.php
│
├── controllers/        ← Page logic files (moved from root)
│   ├── index.php
│   ├── about.php
│   └── contact.php
│
└── views/
    ├── index.view.php
    ├── about.view.php
    ├── contact.view.php
    ├── 404.php         ← Error page for unmatched routes
    │
    └── partials/
        ├── header.php
        ├── nav.php
        ├── banner.php
        └── footer.php
```

---

## 3. Entry Point — Root `index.php`

The root file now does one thing: boot the application by loading helpers and the router.

```php
<?php
require 'functions.php';
require 'router.php';
```

Every request hits this file first. The router takes over from here.

---

## 4. The Router — `router.php`

```php
<?php

$uri = parse_url($_SERVER['REQUEST_URI'])['path'];

$routes = [
    '/'        => 'controllers/index.php',
    '/about'   => 'controllers/about.php',
    '/contact' => 'controllers/contact.php',
];

function routeToController($uri, $routes) {
    if (array_key_exists($uri, $routes))
        require $routes[$uri];
    else
        abort();
}

function abort($code = 404) {
    http_response_code($code);
    require "views/{$code}.php";
    die();
}

routeToController($uri, $routes);
```

### 4.1 Extracting the Clean Path with `parse_url()`

```php
$uri = parse_url($_SERVER['REQUEST_URI'])['path'];
```

`parse_url()` breaks a URL into its components. Extracting only `'path'` strips the query string, giving a clean URI like `/about` regardless of any appended parameters.

| `parse_url()` key | Value for `/about?ref=email` |
| ----------------- | ---------------------------- |
| `'path'`          | `/about`                     |
| `'query'`         | `ref=email`                  |

This also resolves the edge case identified in `urlIs()` from Section 1.

### 4.2 The Routes Table

```php
$routes = [
    '/'        => 'controllers/index.php',
    '/about'   => 'controllers/about.php',
    '/contact' => 'controllers/contact.php',
];
```

A simple associative array mapping URI paths to controller files. Adding a new page means adding one line here and creating the controller — nothing else changes.

### 4.3 `routeToController()` — Dispatching the Request

```php
function routeToController($uri, $routes) {
    if (array_key_exists($uri, $routes))
        require $routes[$uri];
    else
        abort();
}
```

`array_key_exists()` checks whether the URI has a registered route. If yes, the controller is loaded. If not, `abort()` is called with the default `404` code.

### 4.4 `abort()` — Controlled Error Responses

```php
function abort($code = 404) {
    http_response_code($code);
    require "views/{$code}.php";
    die();
}
```

Three things happen in order:

1. **`http_response_code($code)`** — Sets the HTTP status code. Without this, even error pages would be sent with a `200 OK`, which is semantically wrong and bad for search engines and API clients.
2. **`require "views/{$code}.php"`** — Loads the matching error view. Double quotes are used intentionally here — PHP interpolates variables inside `{}` in double-quoted strings, so `$code = 404` resolves to `"views/404.php"`. A single-quoted string would treat `{$code}` as a literal and fail to find the file.
3. **`die()`** — Stops execution immediately so nothing else runs after the error page.

> `$code = 404` is a **default argument**. `abort()` sends a 404; `abort(500)` sends a 500 — making the function reusable for any HTTP error, as long as the corresponding view exists.

---

## 5. How a Request Flows Through the Router

```
Browser requests /about
        │
        ▼
index.php (entry point)
  └─ require 'router.php'
            │
            ├─ parse_url($_SERVER['REQUEST_URI'])['path']  →  '/about'
            ├─ array_key_exists('/about', $routes)  →  true
            └─ require 'controllers/about.php'
                        ├─ $heading = 'About'
                        └─ require 'views/about.view.php'
                                    └─ (assembles partials + content)


Browser requests /unknown
        │
        ▼
index.php → router.php
  └─ array_key_exists('/unknown', $routes)  →  false
  └─ abort()
        ├─ http_response_code(404)
        ├─ require 'views/404.php'
        └─ die()
```

---

## Section 3: Database Connection with PDO

> **Objective:** Build a `Database` class that wraps PDO, separates configuration into its own file, constructs the connection string dynamically, and uses prepared statements to prevent SQL injection.

---

## 1. Project File Structure (Updated)

```
project/
│
├── index.php       ← Now requires Database.php and config.php
├── router.php
├── functions.php
├── Database.php    ← NEW: PDO wrapper class
├── config.php      ← NEW: Application configuration
│
├── controllers/
└── views/
```

---

## 2. PDO

**PDO (PHP Data Objects)** is PHP's built-in database abstraction layer. It provides a single, consistent API that works across MySQL, PostgreSQL, SQLite, and others — switching database engines only requires changing the connection string, not the query code.

| Feature             | Description                                            |
| ------------------- | ------------------------------------------------------ |
| Database-agnostic   | One API for MySQL, PostgreSQL, SQLite, and more        |
| Prepared statements | Built-in protection against SQL injection              |
| Fetch modes         | Control how results are returned (array, object, etc.) |
| Error handling      | Configurable exception-based error reporting           |

---

## 3. Configuration File — `config.php`

```php
<?php

return [
    'database' => [
        'host'    => 'localhost',
        'port'    => 3306,
        'dbname'  => 'myapp',
        'charset' => 'utf8mb4',
    ]

    // Room for additional service configurations
];
```

`config.php` uses `return` to expose its data as a plain PHP array — no output, no global variables. It simply hands its value back to whoever required it:

```php
$config = require 'config.php';
// $config['database'] is the database-specific slice
```

Centralizing config here means changing the host, database name, or charset only ever requires editing this one file.

---

## 4. The `Database` Class — `Database.php`

```php
<?php

class Database
{
    public $connection;

    public function __construct($config, $username = 'root', $password = '')
    {
        $dsn = 'mysql:' . http_build_query($config, '', ';');

        $this->connection = new PDO($dsn, $username, $password, [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }

    public function query($query, $params = [])
    {
        $statement = $this->connection->prepare($query);

        $statement->execute($params);

        return $statement;
    }
}
```

### 4.1 Building the DSN with `http_build_query()`

A **DSN (Data Source Name)** is the connection string PDO uses to locate the database:

```
mysql:host=localhost;port=3306;dbname=myapp;charset=utf8mb4
```

Rather than hardcoding this, `http_build_query()` builds it dynamically from the config array:

```php
$dsn = 'mysql:' . http_build_query($config, '', ';');
```

Adding or changing a DSN parameter (e.g. `unix_socket`) only requires updating `config.php` — the `Database` class stays untouched.

### 4.2 PDO Constructor Options

```php
$this->connection = new PDO($dsn, $username, $password, [
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);
```

The fourth argument is an options array. `PDO::FETCH_ASSOC` makes every query return rows as associative arrays (keyed by column name) rather than the default, which returns both numeric and associative indexes — doubling memory usage for no benefit.

### 4.3 The `query()` Method & Prepared Statements

```php
public function query($query, $params = [])
{
    $statement = $this->connection->prepare($query);

    $statement->execute($params);

    return $statement;
}
```

Instead of executing a query string directly, this uses a **prepare → execute** pattern:

1. **`prepare($query)`** — Sends the query template to the database. The server parses and compiles it. Placeholders (`?`) mark where values will go.
2. **`execute($params)`** — Sends the actual values separately. The server substitutes them into the already-compiled query.

The method returns a `PDOStatement`, letting the caller fetch results with `->fetchAll()` for all rows or `->fetch()` for one.

---

## 5. SQL Injection

### 5.1 The Attack

SQL injection happens when user input is embedded directly into a query string:

```php
// DANGEROUS — never do this
$id = $_GET['id'];
$posts = $db->connection->query("SELECT * FROM posts WHERE id = $id");
```

A normal request to `/posts?id=1` produces a valid query. But `/posts?id=1 OR 1=1` becomes:

```sql
SELECT * FROM posts WHERE id = 1 OR 1=1
```

This returns every row. A payload like `1; DROP TABLE posts--` could delete the table entirely. Because the input is concatenated directly into the query string, the database has no way to distinguish intended SQL from injected SQL.

### 5.2 How Prepared Statements Prevent It

```php
// SAFE — using prepared statements
$id = $_GET['id'];
$posts = $db->query('SELECT * FROM posts WHERE id = ?', [$id])->fetchAll();
```

The `?` placeholder is never replaced by string concatenation. The query template and the values are sent to the server **separately**. By the time the value arrives, the server has already parsed the query structure — it treats `?` as a data slot, not executable SQL. No matter what the user submits, it cannot alter the query's structure.

> The rule is simple: **never concatenate user input into a SQL string**. Always use `?` placeholders and pass values through `execute()`.

---

## 6. Wiring It Together — Root `index.php`

```php
<?php

require 'functions.php';
require 'Database.php';
require 'router.php';

$config = require 'config.php';

$db = new Database($config['database']);
```

The load order matters:

1. `functions.php` — helpers needed throughout the app.
2. `Database.php` — the class must be defined before it can be instantiated.
3. `router.php` — the application's core routing logic.
4. `config.php` — returns the config array into `$config`.
5. `new Database($config['database'])` — only the `'database'` slice is passed; other config sections stay private to their own components.

The `$db` instance is available to any controller loaded by the router, since `require` shares scope.

---