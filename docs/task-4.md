# IEEE ZSB Backend Phase 2 — PHP Project Documentation

---

## Section 1: Refactoring — Extracting a `LoginForm` Class

> **Objective:** Improve readability by extracting login validation logic into a dedicated `LoginForm` class and restructuring the project into an `Http/` directory.

---

## 1. The Motivation for Refactoring

Good code communicates intent clearly — both to your future self and to teammates. When a controller handles too many concerns at once, it becomes hard to read. The practice of refactoring means restructuring existing code without changing its external behaviour, improving clarity and maintainability over time.

A useful exercise is to say out loud what a block of code does, then identify the nouns and verbs. Those nouns become classes; those verbs become methods.

---

## 2. Restructuring into `Http/`

Controllers and form classes are specific to the application — not reusable infrastructure. To keep application code separate from generic `core/` plumbing, move controllers into an `Http/controllers/` directory and form classes into `Http/forms/`:

```
project/
├── core/          ← reusable infrastructure (Router, Database, Container…)
├── Http/
│   ├── controllers/
│   │   └── sessions/
│   │       └── store.php
│   └── forms/
│       └── LoginForm.php
└── public/
    └── index.php
```

After moving controllers, update the router so it knows where to look. Rather than specifying a full path per route, adopt a convention: all controllers live inside `Http/controllers/`, and the router appends that prefix automatically:

```php
// router.php — before
$router->post('/sessions', 'controllers/sessions/store.php');

// router.php — after (path prefix removed, router resolves it)
$router->post('/sessions', 'sessions/store.php');
```

```php
// core/Router.php — dispatch method
require basePath("Http/controllers/{$route['controller']}");
```

---

## 3. Creating `LoginForm`

Extract all validation logic from the sessions store controller into `Http/forms/LoginForm.php`:

```php
namespace Http\Forms;

class LoginForm
{
    protected array $errors = [];

    public function validate(string $email, string $password): bool
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->errors['email'] = 'Please provide a valid email address.';
        }

        if (strlen($password) < 1) {
            $this->errors['password'] = 'Please provide your password.';
        }

        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function error(string $field, string $message): static
    {
        $this->errors[$field] = $message;
        return $this;
    }
}
```

**Key design decisions:**

- `validate()` returns a `bool` — `true` if no errors, `false` otherwise.
- `errors` is `protected` so it cannot be mutated from outside the class. A `errors()` getter exposes a read-only view.
- `error()` allows manually appending a single validation error (used later when authentication fails).

---

## 4. Updated Sessions Store Controller

```php
// Http/controllers/sessions/store.php
use Http\Forms\LoginForm;

$form = new LoginForm();

if (! $form->validate($_POST['email'], $_POST['password'])) {
    return view('sessions/create.view.php', ['errors' => $form->errors()]);
}

// proceed to authentication…
```

The controller now delegates all validation detail to `LoginForm`. Its own code reads as a high-level description of the process.

---

## Section 2: Refactoring — Extracting an `Authenticator` Class

> **Objective:** Move user-lookup and session-writing logic out of the controller into a dedicated `Authenticator` class, and introduce a `redirect()` helper.

---

## 1. Identifying the Next Refactor

After extracting `LoginForm`, the remaining controller code still handles two concerns: finding the user in the database and logging them in. Reading the code aloud reveals the nouns and verbs: *find the User*, *attempt to authenticate*. This suggests an `Authenticator` class with an `attempt()` method.

---

## 2. The `Authenticator` Class — `core/Authenticator.php`

```php
namespace Core;

class Authenticator
{
    public function attempt(string $email, string $password): bool
    {
        $user = App::resolve(Database::class)
            ->query('SELECT * FROM users WHERE email = ?', [$email])
            ->fetch();

        if (! $user || ! password_verify($password, $user['password'])) {
            return false;
        }

        $this->login($user);
        return true;
    }

    public function login(array $user): void
    {
        $_SESSION['user'] = ['email' => $user['email']];
        session_regenerate_id(true);
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();

        $params = session_get_cookie_params();
        setcookie('PHPSESSID', '', time() - 3600,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']);
    }
}
```

**Key design decisions:**

- `attempt()` returns a `bool`. The controller decides what to do on success or failure — the authenticator does not load views or redirect.
- `login()` and `logout()` are moved here from `core/functions.php`, since they are authentication concerns.

---

## 3. The `redirect()` Helper

Every redirect required two lines — a `header()` call and an `exit()`. Extracting a helper removes the repetition:

```php
// core/functions.php
function redirect(string $path): never
{
    header("Location: {$path}");
    exit();
}
```

Usage:

```php
redirect('/');
redirect('/login');
```

---

## 4. Updated Sessions Store Controller

```php
use Http\Forms\LoginForm;
use Core\Authenticator;

$form = new LoginForm();

if ($form->validate($_POST['email'], $_POST['password'])) {
    if ((new Authenticator)->attempt($_POST['email'], $_POST['password'])) {
        redirect('/');
    }

    $form->error('email', 'No matching account found for those credentials.');
}

return view('sessions/create.view.php', ['errors' => $form->errors()]);
```

The duplicate "return to login page" branches are now merged into one. Whether validation fails or authentication fails, the same final line returns the view with whatever errors have been accumulated.

---

## Section 3: The PRG Pattern & Flash Session Data

> **Objective:** Replace returning HTML directly from a POST controller with the Post-Redirect-Get pattern, and implement flash session data that expires after one request.

---

## 1. The Problem with Returning Views from POST

When a POST controller returns HTML directly, the browser URL stays at the POST endpoint. Pressing the browser back button or refreshing triggers a "document expired" warning and may re-submit the form. This is unexpected behaviour.

---

## 2. Post-Redirect-Get (PRG)

The PRG pattern solves this:

1. **POST** — the form submits to a controller.
2. **Redirect** — the controller always responds with a redirect, never with HTML.
3. **GET** — the browser follows the redirect and loads the page normally via GET.

The challenge: validation errors and old form data must survive the redirect. Sessions are the standard mechanism.

---

## 3. Flash Session Data

Regular session data persists indefinitely. Validation errors should only survive for one page request — they are "flashed" to the session and then expired.

A custom `_flash` key in the session groups all flash data. After the redirected GET request is served, the flash data is cleared.

---

## 4. The `Session` Helper Class — `core/Session.php`

```php
namespace Core;

class Session
{
    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        // Look in flash first, then fall back to the top-level session
        if (isset($_SESSION['_flash'][$key])) {
            return $_SESSION['_flash'][$key];
        }

        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return (bool) static::get($key);
    }

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function unflash(): void
    {
        unset($_SESSION['_flash']);
    }

    public static function flush(): void
    {
        $_SESSION = [];
    }

    public static function destroy(): void
    {
        static::flush();
        session_destroy();

        $params = session_get_cookie_params();
        setcookie('PHPSESSID', '', time() - 3600,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']);
    }
}
```

| Method | Purpose |
|--------|---------|
| `put` | Write a persistent session value |
| `get` | Read a value — checks flash first, then top-level |
| `has` | Returns `true` if the key exists |
| `flash` | Write a value that should expire after one request |
| `unflash` | Delete all flash data (called after each request is served) |
| `flush` | Clear the entire session array |
| `destroy` | Full logout: flush, destroy server session, expire cookie |

---

## 5. Expiring Flash Data After Each Request

In `public/index.php`, call `Session::unflash()` after the router has dispatched the request and the controller has run:

```php
// public/index.php
$router->dispatch($uri);

Session::unflash();
```

This ensures flash data is available during the redirected GET request, then immediately removed.

---

## 6. Updated Sessions Store Controller (PRG)

```php
use Http\Forms\LoginForm;
use Core\Authenticator;
use Core\Session;

$form = new LoginForm();

if ($form->validate($_POST['email'], $_POST['password'])) {
    if ((new Authenticator)->attempt($_POST['email'], $_POST['password'])) {
        redirect('/');
    }

    $form->error('email', 'No matching account found for those credentials.');
}

Session::flash('errors', $form->errors());
Session::flash('old', ['email' => $_POST['email']]);

redirect('/login');
```

---

## 7. Reading Flash Data in the View

`Session::get()` automatically checks the flash bucket first, so views call it with no knowledge of the internal `_flash` structure:

```php
// Http/controllers/sessions/create.php
$errors = Session::get('errors', []);

view('sessions/create.view.php', ['errors' => $errors]);
```

---

## 8. The `old()` Helper

Repopulating form fields with the previously submitted value is a common need. A dedicated helper keeps views clean:

```php
// core/functions.php
function old(string $key, mixed $default = ''): mixed
{
    return Core\Session::get('old')[$key] ?? $default;
}
```

Usage in a view:

```php
<input type="email" name="email" value="<?= old('email') ?>">
```

The password field is intentionally not repopulated — never echo a submitted password back to the page.

---

## Section 4: Validation Exceptions & Centralized Error Handling

> **Objective:** Move validation boilerplate out of every controller by throwing a `ValidationException` that is caught once in the front controller.

---

## 1. The Motivation

Every form controller repeated the same pattern: validate, flash errors, flash old data, redirect back. With many forms in a real project, this duplication is unsustainable.

---

## 2. Refactoring `LoginForm` to Throw an Exception

Move the validation call into a static constructor and throw on failure:

```php
namespace Http\Forms;

use Core\ValidationException;

class LoginForm
{
    public array $attributes;
    protected array $errors = [];

    public function __construct(array $attributes)
    {
        $this->attributes = $attributes;
    }

    public static function validate(array $attributes): static
    {
        $instance = new static($attributes);

        if (! filter_var($attributes['email'], FILTER_VALIDATE_EMAIL)) {
            $instance->errors['email'] = 'Please provide a valid email address.';
        }

        if (strlen($attributes['password']) < 1) {
            $instance->errors['password'] = 'Please provide your password.';
        }

        if ($instance->failed()) {
            ValidationException::throw($instance->errors(), $instance->attributes);
        }

        return $instance;
    }

    public function failed(): bool
    {
        return ! empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function error(string $field, string $message): static
    {
        $this->errors[$field] = $message;
        return $this;
    }

    public function throw(): never
    {
        ValidationException::throw($this->errors(), $this->attributes);
    }
}
```

---

## 3. The `ValidationException` Class — `core/ValidationException.php`

```php
namespace Core;

class ValidationException extends \Exception
{
    public readonly array $errors;
    public readonly array $old;

    public static function throw(array $errors, array $old): never
    {
        $instance         = new static();
        $instance->errors = $errors;
        $instance->old    = $old;

        throw $instance;
    }
}
```

`readonly` properties can be assigned once (inside `throw()`) and never changed afterwards — no getter method is required.

---

## 4. Catching the Exception in the Front Controller

Instead of catching `ValidationException` in every controller, catch it once in `public/index.php` after the router dispatches:

```php
// public/index.php
use Core\ValidationException;
use Core\Session;

try {
    $router->dispatch($uri);
} catch (ValidationException $e) {
    Session::flash('errors', $e->errors);
    Session::flash('old', $e->old);

    redirect($router->previousUrl());
}

Session::unflash();
```

Every form controller can now simply call `FormClass::validate(...)` and trust that failures are handled centrally.

---

## 5. `Router::previousUrl()`

The redirect target after a failed validation should be the page the user came from, not a hard-coded path. The `HTTP_REFERER` server variable provides this:

```php
// core/Router.php
public function previousUrl(): string
{
    return $_SERVER['HTTP_REFERER'];
}
```

---

## 6. Final Sessions Store Controller

```php
// Http/controllers/sessions/store.php
use Http\Forms\LoginForm;
use Core\Authenticator;

$form     = LoginForm::validate(['email' => $_POST['email'], 'password' => $_POST['password']]);
$signedIn = (new Authenticator)->attempt($_POST['email'], $_POST['password']);

if (! $signedIn) {
    $form->error('email', 'No matching account found for those credentials.')->throw();
}

redirect('/');
```

The controller now reads as a clear sequence of instructions: validate the form, attempt authentication, handle failure, redirect on success.

---

## Section 5: Composer & Autoloading

> **Objective:** Replace the manual `spl_autoload_register` function with Composer's PSR-4 autoloader, and install third-party packages.

---

## 1. What Composer Is

Composer is PHP's dependency manager. It lets you pull in third-party packages from Packagist.org and automatically generates an autoloader that maps namespaces to directories.

Install Composer globally:

```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
mv composer.phar /usr/local/bin/composer
```

Verify:

```bash
composer --version
```

---

## 2. Initialising `composer.json`

```bash
composer init
```

Answer the prompts (most can be skipped by pressing Enter). Add the vendor directory to `.gitignore` when asked — you do not commit downloaded packages to version control.

---

## 3. Configuring PSR-4 Autoloading

PSR-4 is a standard that maps a namespace prefix to a filesystem directory. Add an `autoload` block to `composer.json`:

```json
{
    "autoload": {
        "psr-4": {
            "Core\\": "core/",
            "Http\\": "Http/"
        }
    }
}
```

Each top-level namespace is declared once. Composer resolves all nested namespaces automatically — `Core\Middleware\Auth` maps to `core/Middleware/Auth.php`.

After any change to the autoload configuration, regenerate the autoload files:

```bash
composer dump-autoload
```

---

## 4. Requiring Composer's Autoloader

Replace the manual `spl_autoload_register` call in `public/index.php` with a single require:

```php
// public/index.php
require basePath('vendor/autoload.php');
```

This is the standard first line of any modern PHP application entry point.

---

## 5. Installing Packages

Search for a package:

```bash
composer search collections
```

Install a runtime dependency:

```bash
composer require illuminate/collections
```

Install a development-only dependency (not deployed to production):

```bash
composer require --dev pestphp/pest
```

The distinction is reflected in `composer.json`:

```json
{
    "require": {
        "illuminate/collections": "^10.0"
    },
    "require-dev": {
        "pestphp/pest": "^2.0"
    }
}
```

---

## 6. Using Collections

The `illuminate/collections` package wraps arrays with a fluent API:

```php
use Illuminate\Support\Collection;

$numbers = collect(range(1, 10));

$small = $numbers->filter(fn($n) => $n <= 5);
// Collection { 1, 2, 3, 4, 5 }

$doubled = $numbers->map(fn($n) => $n * 2);
// Collection { 2, 4, 6, 8, 10, 12, 14, 16, 18, 20 }

$numbers->contains(7); // true
```

Collections defer to PHP's native `array_filter`, `array_map`, etc. internally — they are a readability wrapper, not magic.

---

## Section 6: Automated Testing with Pest

> **Objective:** Introduce automated testing concepts and write basic unit tests with PestPHP.

---

## 1. Why Automated Tests

Manual testing requires re-verifying every feature by hand after every change. Automated tests run the verifications for you in milliseconds. The main benefits are:

- **Confidence when refactoring** — a failing test immediately flags a regression.
- **Living documentation** — tests describe the expected behaviour of each class.
- **Reduced fear** — untested code tends to be left alone; tested code can be improved safely.

---

## 2. Initialising Pest

```bash
composer require --dev pestphp/pest
vendor/bin/pest --init
```

This creates a `tests/` directory with `Feature/` and `Unit/` subdirectories and a `pest.php` configuration file.

---

## 3. Running Tests

```bash
vendor/bin/pest
```

All files matching `tests/**/*Test.php` are discovered and run automatically.

---

## 4. Test Anatomy

```php
// tests/Unit/ExampleTest.php
test('true is true', function () {
    expect(true)->toBe(true);
});
```

Each test has three logical stages:

| Stage | Purpose |
|-------|---------|
| **Arrange** | Set up the world — instantiate classes, build fixtures |
| **Act** | Perform the operation being tested |
| **Assert** | Confirm the result with `expect()` |

---

## 5. Example — Testing the Container

```php
// tests/Unit/ContainerTest.php
use Core\Container;

test('it can resolve a binding out of the container', function () {
    // Arrange
    $container = new Container();
    $container->bind('foo', fn() => 'bar');

    // Act
    $result = $container->resolve('foo');

    // Assert
    expect($result)->toEqual('bar');
});
```

Running this test after any change to `Container` immediately confirms the binding and resolution behaviour is still intact.

---

## 6. Common `expect()` Matchers

```php
expect($value)->toBe(true);          // strict equality (===)
expect($value)->toEqual('bar');      // loose equality (==)
expect($array)->toHaveCount(3);
expect($string)->toContain('hello');
expect($value)->toBeNull();
expect($value)->toBeInstanceOf(Foo::class);
expect($callable)->toThrow(RuntimeException::class);
```

---
