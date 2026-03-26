# IEEE ZSB Backend Phase 2 — PHP Project Documentation

---

## Section 1: Organizing Notes Files & Conventional Naming

> **Objective:** Introduce resource-based directory organization and RESTful naming conventions for controllers and views.

---

## 1. The Problem This Solves

With a flat directory structure, controllers and views for every resource (notes, users, posts, etc.) live side by side in the same folder. As the application grows, this becomes difficult to navigate and reason about. Grouping files by resource and adopting consistent naming conventions solves both problems.

---

## 2. Organizing by Resource

Move all notes-related controllers into `controllers/notes/` and all notes views into `views/notes/`.

```
controllers/
└── notes/
    ├── index.php    ← show all notes
    ├── show.php     ← show a single note
    └── create.php   ← display the create form

views/
└── notes/
    ├── index.view.php
    ├── show.view.php
    └── create.view.php
```

---

## 3. RESTful Controller Naming Conventions

| Controller name | Responsibility                              |
| --------------- | ------------------------------------------- |
| `index`         | Display all (or a paginated list) of a resource |
| `show`          | Display a single resource                   |
| `create`        | Display the form to create a new resource   |
| `store`         | Handle the POST and persist the new resource |
| `edit`          | Display the form to edit an existing resource |
| `update`        | Handle the PATCH/PUT and persist the changes |
| `destroy`       | Delete the resource                         |

Following this convention across every resource makes it possible to navigate the codebase without consulting the routes file — `users/create` will always be the form for a new user, on any project that follows these conventions.

---

## 4. Fixing Partial Paths After Moving Views

When a view is moved into a subdirectory, any `require` paths to shared partials break. Two approaches fix this:

**Option 1 — relative path using `__DIR__`:**

```php
require __DIR__ . '/../../partials/nav.php';
```

`__DIR__` resolves to the directory of the current file at runtime, so the relative `../../` navigates up to where the partials live.

**Option 2 — absolute path from the project root (preferred):**

```php
require basePath('views/partials/nav.php');
```

Uses the `basePath()` helper (introduced in Section 2) to build a full path from the project root, eliminating the fragility of relative paths.

---

## Section 2: Project Roots, `basePath`, and Autoloading

> **Objective:** Lock down the document root for security, introduce a `basePath` constant and helpers, add a `view()` helper, and implement `spl_autoload_register` to replace manual `require` calls for classes.

---

## 1. The Security Problem — Direct File Access

By default, PHP's built-in server serves any file in the project root. This means a user can navigate directly to `router.php`, `config.php`, or any other file and execute it — a significant security risk.

**The fix:** move `index.php` to a `public/` directory and set that as the document root:

```bash
php -S localhost:8888 -t public
```

Now only files inside `public/` are directly accessible. Everything else — controllers, views, config, core classes — lives outside the webroot and can only be reached through `index.php`.

---

## 2. Updated Project Structure

```
project/
│
├── public/
│   └── index.php       ← Entry point (document root)
│
├── controllers/
├── views/
├── core/               ← Infrastructure/framework classes
│   ├── Database.php
│   ├── Router.php
│   ├── Validator.php
│   ├── Response.php
│   └── functions.php
│
├── config.php
└── routes.php
```

---

## 3. The `basePath` Constant & Helper

Because `index.php` is now inside `public/`, all paths to project files must go up one level. Rather than hardcoding `../` throughout the codebase, a constant is declared once:

```php
// public/index.php
define('BASE_PATH', dirname(__DIR__));
```

`dirname(__DIR__)` gives the parent of `public/` — the project root. A helper function wraps it for convenience:

```php
// core/functions.php
function basePath(string $path): string
{
    return BASE_PATH . '/' . $path;
}
```

Usage anywhere in the project:

```php
require basePath('config.php');
require basePath('views/notes/index.view.php');
```

---

## 4. The `view()` Helper

Loading a view is done constantly. A dedicated helper makes the intent explicit and removes the repetitive `basePath('views/...')` pattern:

```php
function view(string $path, array $attributes = []): void
{
    extract($attributes);

    require basePath("views/{$path}");
}
```

**`extract()`** converts an associative array into individual variables in the current scope. Passing `['heading' => 'Home', 'notes' => $notes]` makes `$heading` and `$notes` available inside the required view file.

Usage in a controller:

```php
view('notes/index.view.php', [
    'heading' => 'My Notes',
    'notes'   => $notes,
]);
```

Only the variables explicitly passed are available in the view — no accidental leakage of `$db`, `$config`, or other controller-level variables.

---

## 5. Autoloading Classes with `spl_autoload_register`

Manually requiring every class file at the top of `index.php` doesn't scale. PHP provides `spl_autoload_register` to load classes on demand — only when they are first instantiated.

```php
spl_autoload_register(function ($class) {
    $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    require basePath("{$class}.php");
});
```

When PHP encounters `new Database(...)` and the class hasn't been loaded yet, it calls this function automatically with `$class = 'Core\Database'`. The backslash namespace separator is replaced with the OS directory separator, producing `Core/Database`, and then `basePath` resolves the full path to `core/Database.php`.

---

## 6. Extracting the `core/` Directory

Generic infrastructure classes — `Database`, `Router`, `Validator`, `Response` — are not specific to the application being built. Separating them into a `core/` directory distinguishes application code from framework-level plumbing and makes the project easier to reason about.

```
core/
├── Database.php
├── Router.php
├── Validator.php
├── Response.php
└── functions.php
```

---

## Section 3: Namespaces

> **Objective:** Apply PHP namespaces to the `core/` classes to prevent name collisions and align the autoloader with the directory structure.

---

## 1. Declaring a Namespace

Each class in `core/` is given a namespace that matches its directory:

```php
// core/Database.php
namespace Core;

class Database { ... }
```

Once a namespace is applied, all other class references within that file assume the same namespace unless told otherwise.

---

## 2. Referencing Namespaced Classes

**Option 1 — Fully qualified name inline:**

```php
$db = new \Core\Database($config);
```

**Option 2 — `use` statement at the top of the file (preferred):**

```php
use Core\Database;

$db = new Database($config);
```

The `use` statement acts as an alias for the full namespace path, keeping the rest of the file clean.

---

## 3. Global Classes Inside a Namespace

PHP built-in classes like `PDO` have no namespace. Inside a namespaced file, PHP looks for them within the current namespace first — `Core\PDO` — and fails. Fix this with a `use` statement at the top:

```php
namespace Core;

use PDO;

class Database
{
    public function __construct(...)
    {
        $this->connection = new PDO($dsn, ...); // resolved correctly
    }
}
```

---

## 4. Updating the Autoloader for Namespaces

With namespaces, the class string passed to the autoloader changes from `Database` to `Core\Database`. The autoloader translates the namespace separator `\` to a directory separator:

```php
spl_autoload_register(function ($class) {
    $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    require basePath("{$class}.php");
});
```

`Core\Database` → `Core/Database` → `basePath('Core/Database.php')` → `/project/core/Database.php`.

---

## Section 4: HTTP Methods, RESTful Routing & the Router Class

> **Objective:** Upgrade the router from a flat array to a class that registers routes by HTTP method, supports `DELETE`/`PATCH` via a hidden `_method` field, and dispatches requests cleanly.

---

## 1. The Problem — No Method Routing

The original router matched only URIs. A controller handling both GET (show note) and POST (delete note) had to branch internally with conditionals — messy and hard to extend.

---

## 2. Designing the Router API

```php
// routes.php
$router->get('/', 'controllers/index.php');
$router->get('/notes', 'controllers/notes/index.php');
$router->get('/notes/create', 'controllers/notes/create.php');
$router->post('/notes', 'controllers/notes/store.php');
$router->delete('/notes', 'controllers/notes/destroy.php');
$router->patch('/notes', 'controllers/notes/update.php');
```

Each method (`get`, `post`, `delete`, `patch`, `put`) stores a route entry with a URI, controller path, and the HTTP method.

---

## 3. The `Router` Class — `core/Router.php`

```php
namespace Core;

class Router
{
    protected $routes = [];

    public function add(string $method, string $uri, string $controller): static
    {
        $this->routes[] = [
            'uri'        => $uri,
            'controller' => $controller,
            'method'     => strtoupper($method),
            'middleware' => null,
        ];

        return $this;
    }

    public function get(string $uri, string $controller): static
    {
        return $this->add('GET', $uri, $controller);
    }

    public function post(string $uri, string $controller): static
    {
        return $this->add('POST', $uri, $controller);
    }

    public function delete(string $uri, string $controller): static
    {
        return $this->add('DELETE', $uri, $controller);
    }

    public function patch(string $uri, string $controller): static
    {
        return $this->add('PATCH', $uri, $controller);
    }

    public function put(string $uri, string $controller): static
    {
        return $this->add('PUT', $uri, $controller);
    }

    public function route(string $uri, string $method): void
    {
        foreach ($this->routes as $route) {
            if ($route['uri'] === $uri && $route['method'] === strtoupper($method)) {
                Middleware::resolve($route['middleware']);
                require basePath($route['controller']);
                return;
            }
        }

        $this->abort();
    }

    protected function abort(int $code = 404): void
    {
        http_response_code($code);
        require basePath("views/{$code}.php");
        die();
    }
}
```

The `routes` property is `protected` — there is no reason for external code to access the raw array directly.

---

## 4. Supporting `DELETE` and `PATCH` via `_method`

HTML forms only support `GET` and `POST`. To submit a `DELETE` or `PATCH` request, add a hidden input to the form:

```html
<form method="POST" action="/notes">
    <input type="hidden" name="_method" value="DELETE">
    <input type="hidden" name="id" value="<?= $note['id'] ?>">
    <button type="submit">Delete</button>
</form>
```

In `index.php`, check for this field and override the method before routing:

```php
$method = $_POST['_method'] ?? $_SERVER['REQUEST_METHOD'];
$router->route($uri, $method);
```

The `??` null coalescing operator returns `$_POST['_method']` if it is set and not null, otherwise falls back to the real request method.

---

## 5. Splitting Controllers by Method

With method-aware routing, each controller handles exactly one action:

| Route                        | Controller              |
| ---------------------------- | ----------------------- |
| `GET /notes`                 | `notes/index.php`       |
| `GET /notes/create`          | `notes/create.php`      |
| `POST /notes`                | `notes/store.php`       |
| `GET /notes/show`            | `notes/show.php`        |
| `DELETE /notes`              | `notes/destroy.php`     |
| `GET /notes/edit`            | `notes/edit.php`        |
| `PATCH /notes`               | `notes/update.php`      |

No more conditionals inside controllers — each file does exactly one thing.

---

## Section 5: Full Notes CRUD

> **Objective:** Complete the notes resource with create, read, update, and delete operations following RESTful conventions.

---

## 1. Delete — `destroy.php`

```php
use Core\App;
use Core\Database;

$db   = App::resolve(Database::class);
$id   = $_POST['id'];
$currentUserId = 1; // hardcoded until authentication is added

$note = $db->query('SELECT * FROM notes WHERE id = ?', [$id])->fetch();

if (! $note) {
    abort(404);
}

// Authorization — only the owner can delete
if ($note['user_id'] !== $currentUserId) {
    abort(403);
}

$db->query('DELETE FROM notes WHERE id = ?', [$id]);

header('Location: /notes');
die();
```

After deleting, the user is redirected to the notes list. `header()` sets the `Location` response header and `die()` ensures nothing executes after the redirect.

---

## 2. Create Form — `create.php`

```php
view('notes/create.view.php', [
    'heading' => 'Create Note',
    'errors'  => [],
]);
```

---

## 3. Store — `store.php`

```php
use Core\App;
use Core\Database;
use Core\Validator;

$db    = App::resolve(Database::class);
$body  = $_POST['body'];
$errors = [];

if (! Validator::string($body, 1, 1000)) {
    $errors['body'] = 'A note must be between 1 and 1000 characters.';
}

if (! empty($errors)) {
    return view('notes/create.view.php', [
        'heading' => 'Create Note',
        'errors'  => $errors,
    ]);
}

$db->query(
    'INSERT INTO notes (body, user_id) VALUES (?, ?)',
    [$body, 1]
);

header('Location: /notes');
die();
```

---

## 4. Edit Form — `edit.php`

```php
use Core\App;
use Core\Database;

$db   = App::resolve(Database::class);
$id   = $_GET['id'];
$currentUserId = 1;

$note = $db->query('SELECT * FROM notes WHERE id = ?', [$id])->fetch();

if (! $note) abort(404);
if ($note['user_id'] !== $currentUserId) abort(403);

view('notes/edit.view.php', [
    'heading' => 'Edit Note',
    'errors'  => [],
    'note'    => $note,
]);
```

The edit form view pre-fills the textarea with `$note['body']` and includes a hidden `id` input:

```html
<textarea name="body"><?= $note['body'] ?></textarea>
<input type="hidden" name="id" value="<?= $note['id'] ?>">
<input type="hidden" name="_method" value="PATCH">
```

---

## 5. Update — `update.php`

```php
use Core\App;
use Core\Database;
use Core\Validator;

$db    = App::resolve(Database::class);
$id    = $_POST['id'];
$body  = $_POST['body'];
$currentUserId = 1;

$note = $db->query('SELECT * FROM notes WHERE id = ?', [$id])->fetch();

if (! $note) abort(404);
if ($note['user_id'] !== $currentUserId) abort(403);

$errors = [];

if (! Validator::string($body, 1, 1000)) {
    $errors['body'] = 'A note must be between 1 and 1000 characters.';
}

if (! empty($errors)) {
    return view('notes/edit.view.php', [
        'heading' => 'Edit Note',
        'errors'  => $errors,
        'note'    => $note,
    ]);
}

$db->query(
    'UPDATE notes SET body = ? WHERE id = ?',
    [$body, $id]
);

header('Location: /notes');
die();
```

---

## 6. CRUD Summary

| Action  | Method   | URI            | Controller      | Description                  |
| ------- | -------- | -------------- | --------------- | ---------------------------- |
| Index   | GET      | `/notes`       | `notes/index`   | List all notes               |
| Show    | GET      | `/notes/show`  | `notes/show`    | Display a single note        |
| Create  | GET      | `/notes/create`| `notes/create`  | Show the create form         |
| Store   | POST     | `/notes`       | `notes/store`   | Persist the new note         |
| Edit    | GET      | `/notes/edit`  | `notes/edit`    | Show the edit form           |
| Update  | PATCH    | `/notes`       | `notes/update`  | Persist the updated note     |
| Destroy | DELETE   | `/notes`       | `notes/destroy` | Delete the note              |

---

## Section 6: Container & Dependency Injection

> **Objective:** Build a `Container` class that stores and resolves object bindings, eliminating repeated database instantiation across controllers.

---

## 1. The Problem

Every controller that needs a database connection builds it from scratch:

```php
$config = require basePath('config.php');
$db = new Database($config['database']);
```

This is duplicated in every controller. A container allows binding how to build an object once, then resolving it anywhere.

---

## 2. The `Container` Class — `core/Container.php`

```php
namespace Core;

class Container
{
    protected array $bindings = [];

    public function bind(string $key, callable $resolver): void
    {
        $this->bindings[$key] = $resolver;
    }

    public function resolve(string $key): mixed
    {
        if (! array_key_exists($key, $this->bindings)) {
            throw new \Exception("No matching binding found for key {$key}.");
        }

        return call_user_func($this->bindings[$key]);
    }
}
```

- **`bind()`** — stores a factory callable under a string key.
- **`resolve()`** — calls the factory and returns the built object. Throws an exception for unknown keys.

---

## 3. The `App` Singleton — `core/App.php`

```php
namespace Core;

class App
{
    protected static Container $container;

    public static function setContainer(Container $container): void
    {
        static::$container = $container;
    }

    public static function container(): Container
    {
        return static::$container;
    }

    public static function bind(string $key, callable $resolver): void
    {
        static::container()->bind($key, $resolver);
    }

    public static function resolve(string $key): mixed
    {
        return static::container()->resolve($key);
    }
}
```

`App` provides a globally accessible facade over the container. Static methods delegate to the container instance, so any controller can call `App::resolve(Database::class)` without needing access to a `$container` variable.

---

## 4. Wiring It Up — `bootstrap.php`

```php
<?php

use Core\App;
use Core\Container;
use Core\Database;

$container = new Container();

$container->bind(Database::class, function () {
    $config = require basePath('config.php');
    return new Database($config['database']);
});

App::setContainer($container);
```

`index.php` requires `bootstrap.php` after declaring `basePath`. From this point, any controller resolves the database with:

```php
$db = App::resolve(Database::class);
```

---

## Section 7: Route-Level Middleware

> **Objective:** Add middleware support to the router so that individual routes can restrict access to guests only or authenticated users only.

---

## 1. Registering Middleware on a Route

The `only()` method chains onto any route registration and stores a middleware key:

```php
$router->get('/register', 'controllers/registration/create.php')->only('guest');
$router->get('/notes', 'controllers/notes/index.php')->only('auth');
```

`only()` grabs the last entry in the routes array and sets its `middleware` key:

```php
public function only(string $key): static
{
    $this->routes[array_key_last($this->routes)]['middleware'] = $key;
    return $this;
}
```

---

## 2. The `Middleware` Class — `core/Middleware.php`

```php
namespace Core;

class Middleware
{
    const MAP = [
        'guest' => \Core\Middleware\Guest::class,
        'auth'  => \Core\Middleware\Auth::class,
    ];

    public static function resolve(?string $key): void
    {
        if (! $key) return;

        if (! array_key_exists($key, static::MAP)) {
            throw new \Exception("No matching middleware found for key '{$key}'.");
        }

        $middleware = new (static::MAP[$key])();
        $middleware->handle();
    }
}
```

---

## 3. Guest & Auth Middleware

**`core/Middleware/Guest.php`** — redirects signed-in users away from guest-only pages:

```php
namespace Core\Middleware;

class Guest
{
    public function handle(): void
    {
        if ($_SESSION['user'] ?? false) {
            header('Location: /');
            exit();
        }
    }
}
```

**`core/Middleware/Auth.php`** — redirects unauthenticated users away from protected pages:

```php
namespace Core\Middleware;

class Auth
{
    public function handle(): void
    {
        if (! ($_SESSION['user'] ?? false)) {
            header('Location: /login');
            exit();
        }
    }
}
```

---

## Section 8: Sessions & Authentication

> **Objective:** Introduce PHP sessions, build a registration and login system with hashed passwords, and add a logout flow.

---

## 1. Sessions

PHP sessions store data on the server and associate it with a browser via a cookie. The `$_SESSION` superglobal is the interface for reading and writing session data.

`session_start()` must be called before any interaction with `$_SESSION`. Call it early in `index.php`:

```php
session_start();
```

```php
// Write to the session
$_SESSION['user'] = ['email' => $user['email']];

// Read from the session
$email = $_SESSION['user']['email'] ?? 'guest';

// Session data persists across page requests for the lifetime of the browser session.
// Closing the browser destroys the session cookie (and effectively the session).
```

---

## 2. Displaying Session Data in Views

```php
// views/partials/nav.php
<?php if ($_SESSION['user'] ?? false) : ?>
    <!-- show avatar / account links -->
<?php else : ?>
    <a href="/register">Register</a>
    <a href="/login">Login</a>
<?php endif; ?>
```

---

## 3. Displaying the Logged-In User

To greet the user on the homepage, read their email from the session and fall back to `'guest'`:

```php
Hello, <?= $_SESSION['user']['email'] ?? 'guest' ?>
```

---

## 4. Password Hashing

**Never store passwords in plain text.** Use `password_hash()` when creating an account:

```php
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);
// Store $hashedPassword in the database
```

`PASSWORD_BCRYPT` uses the bcrypt algorithm, which is slow by design — making brute-force attacks impractical. `PASSWORD_DEFAULT` currently also resolves to bcrypt but may change in future PHP versions; use `PASSWORD_BCRYPT` to guarantee the algorithm.

---

## 5. Registration — `controllers/registration/store.php`

```php
use Core\App;
use Core\Database;
use Core\Validator;

$db       = App::resolve(Database::class);
$email    = $_POST['email'];
$password = $_POST['password'];
$errors   = [];

if (! Validator::email($email)) {
    $errors['email'] = 'Please provide a valid email address.';
}

if (! Validator::string($password, 7, 255)) {
    $errors['password'] = 'Password must be between 7 and 255 characters.';
}

if (! empty($errors)) {
    return view('registration/create.view.php', ['errors' => $errors]);
}

// Check for duplicate account
$existing = $db->query('SELECT * FROM users WHERE email = ?', [$email])->fetch();

if ($existing) {
    header('Location: /login');
    exit();
}

// Insert new user
$db->query(
    'INSERT INTO users (email, password) VALUES (?, ?)',
    [$email, password_hash($password, PASSWORD_BCRYPT)]
);

// Log them in immediately
login(['email' => $email]);

header('Location: /');
exit();
```

---

## 6. The `login()` Helper — `core/functions.php`

```php
function login(array $user): void
{
    $_SESSION['user'] = [
        'email' => $user['email'],
    ];
}
```

Centralizing session writes here means the login form and registration flow both call the same function. Only the email is stored — not the password or any other sensitive field.

---

## 7. Login — `controllers/sessions/store.php`

```php
use Core\App;
use Core\Database;
use Core\Validator;

$db       = App::resolve(Database::class);
$email    = $_POST['email'];
$password = $_POST['password'];
$errors   = [];

if (! Validator::email($email)) {
    $errors['email'] = 'Please provide a valid email address.';
}

if (! Validator::string($password, 1, 255)) {
    $errors['password'] = 'Please provide your password.';
}

if (! empty($errors)) {
    return view('sessions/create.view.php', ['errors' => $errors]);
}

$user = $db->query('SELECT * FROM users WHERE email = ?', [$email])->fetch();

// Intentionally vague error — don't reveal whether the email exists
if (! $user || ! password_verify($password, $user['password'])) {
    $errors['email'] = 'No matching account found for those credentials.';
    return view('sessions/create.view.php', ['errors' => $errors]);
}

login($user);

header('Location: /');
exit();
```

`password_verify($plain, $hash)` compares the submitted password against the stored bcrypt hash. Returning the same error message whether the email or password is wrong prevents attackers from probing which emails exist in the database.

---

## 8. Logout — `controllers/sessions/destroy.php`

```php
// 1. Clear the session data
$_SESSION = [];

// 2. Delete the server-side session file
session_destroy();

// 3. Expire the session cookie in the browser
$params = session_get_cookie_params();
setcookie(
    'PHPSESSID',
    '',
    time() - 3600,
    $params['path'],
    $params['domain'],
    $params['secure'],
    $params['httponly']
);

// 4. Redirect
header('Location: /');
exit();
```

All four steps are needed for a complete logout. Skipping step 3 leaves the browser cookie pointing to a destroyed session file, which can cause subtle bugs.

> **Security tip:** Call `session_regenerate_id(true)` after login to generate a new session ID and delete the old session file. This prevents session fixation attacks where a malicious actor pre-sets a known session ID.

---