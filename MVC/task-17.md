# PHP MVC Framework Fundamentals

---

## 1. The MVC Pattern: What Does MVC Stand For?

**Analogy: A Restaurant Kitchen**

Think of a restaurant. Customers sit in the dining room and never walk into the kitchen — they just tell a waiter what they want. The kitchen prepares the food using ingredients from the pantry. The waiter then brings the finished dish back to the customer. Nobody in the dining room touches the raw ingredients, and the pantry has no idea what the customer looks like.

That is **MVC** — Model, View, Controller.

- **The Model** = the pantry and kitchen — it holds the data and the rules for working with it
- **The View** = the dining room — it is what the customer (user) sees and interacts with
- **The Controller** = the waiter — it receives requests, talks to the Model, and hands results to the View

```
User Request
     ↓
[Controller]  ←→  [Model]  ←→  Database
     ↓
  [View]
     ↓
User Response (HTML page)
```

### The primary responsibility of each part

**Model** — The Model is solely responsible for data. It talks to the database, holds validation rules, and represents real-world concepts like a `User` or an `Order`. In the framework built in the course, a `RegisterModel` class handles the data for a registration form — it defines properties like `firstName`, `lastName`, `email`, and `password`, and it contains the rules for validating them. The Model has absolutely no knowledge of how things look on screen.

**View** — The View is solely responsible for presentation. It takes data that the Controller hands it and renders it as HTML. In the course framework, view files are plain PHP templates. A view never queries a database directly — it only displays what it is given. Views are also organized inside reusable **layouts**, so the navigation bar and footer only need to be written once.

**Controller** — The Controller is the traffic manager between the other two. When a user submits a registration form, the Controller receives that HTTP request, creates a `RegisterModel`, tells it to validate the submitted data, and then either saves the record or re-renders the form with error messages. The Controller itself does neither the database work nor the display work — it just coordinates.

```php
<?php
// A simplified Controller action — the waiter at work
class AuthController extends Controller
{
    public function register(Request $request, Response $response): string
    {
        $model = new RegisterModel();

        if ($request->isPost()) {
            // Hand the submitted data to the Model
            $model->loadData($request->getBody());

            if ($model->validate() && $model->register()) {
                // Success — redirect elsewhere
                $response->redirect('/');
            }
        }

        // Re-render the form View, passing the Model (with any errors) to it
        return $this->render('auth/register', ['model' => $model]);
    }
}
?>
```

### Why does this separation matter?

Without MVC, a single PHP file would contain SQL queries, business logic, and HTML all tangled together. Change the database table structure and you break the HTML. Change the design and you risk breaking the SQL. MVC makes each concern independent — you can redesign the View without ever touching the Model, and you can swap the database without touching the HTML.

---

## 2. Routing: What Is a Router?

**Analogy: A Traffic Cop at a Busy Intersection**

Imagine a busy city intersection where cars arrive from all directions. A traffic cop stands in the middle and directs every car: *"You, heading to Main Street — go left. You, going to the hospital — go right."* The cop does not drive the cars, and the cars do not decide where to go themselves. The cop reads the destination written on each car and dispatches it to the correct road.

A **Router** is that traffic cop for your web application.

When a user visits a URL like `/register` using a POST request, the Router reads that request — the URL and the HTTP method — and dispatches it to the correct Controller action. It does not process the request itself. It simply matches the incoming signal to the pre-configured map of routes.

```php
<?php
// In index.php — configuring the traffic cop's rulebook
$app->router->get('/', [SiteController::class, 'home']);
$app->router->get('/contact', [SiteController::class, 'contact']);
$app->router->get('/register', [AuthController::class, 'register']);
$app->router->post('/register', [AuthController::class, 'register']); // same URL, different HTTP method
?>
```

```php
<?php
// Inside the Router class — the cop reading each incoming car's destination
class Router
{
    private array $routes = [];

    public function get(string $path, array $callback): void
    {
        $this->routes['get'][$path] = $callback;
    }

    public function post(string $path, array $callback): void
    {
        $this->routes['post'][$path] = $callback;
    }

    public function resolve(Request $request): mixed
    {
        $path   = $request->getPath();   // e.g. "/register"
        $method = $request->getMethod(); // e.g. "post"

        $callback = $this->routes[$method][$path] ?? false;

        if ($callback === false) {
            // No rule matches this car — send a 404
            http_response_code(404);
            return $this->renderView('_404');
        }

        // Dispatch to the correct Controller action
        return call_user_func($callback, $request, $response);
    }
}
?>
```

### Why does the Router check the HTTP method too?

The same URL `/register` serves two completely different purposes depending on whether the request is a GET (show the empty form) or a POST (process the submitted data). The Router distinguishes between them, so the correct action runs every time. Without this, you would need entirely separate URLs for showing and submitting a form.

---

## 3. The Front Controller: What Is It?

**Analogy: A Single Reception Desk vs. Dozens of Side Entrances**

Imagine an office building the old way: every department has its own door opening directly onto the street — `accounting-door.php`, `hr-door.php`, `contact-door.php`. Anyone can walk directly into any department without going through security. Now imagine the modern way: there is **one** reception desk at the front of the building. Every visitor — no matter where they want to go — must come through reception first. Reception checks them in, decides where they should go, and directs them.

`index.php` as the **Front Controller** is that single reception desk.

### The old way — dozens of separate files

```
/about.php       ← direct access, its own logic, its own HTML
/contact.php     ← direct access, its own logic, its own HTML
/users.php       ← direct access, its own logic, its own HTML
/register.php    ← direct access, its own logic, its own HTML
```

Every file is a separate entry point. There is no central place to set up database connections, check authentication, handle errors, or apply shared configuration. Every file has to repeat that setup code, or it simply skips it.

### The modern way — one Front Controller

```
/public/index.php   ← the ONLY entry point for every request
```

```php
<?php
// public/index.php — the single front door of the entire application

require_once __DIR__ . '/../vendor/autoload.php';

$app = new \app\core\Application(__DIR__ . '/..');

// All routes configured in one place
$app->router->get('/', [SiteController::class, 'home']);
$app->router->get('/about', [SiteController::class, 'about']);
$app->router->get('/contact', [SiteController::class, 'contact']);
$app->router->get('/register', [AuthController::class, 'register']);
$app->router->post('/register', [AuthController::class, 'register']);

// One call handles everything — routing, controllers, views
$app->run();
?>
```

The web server is configured (via `.htaccess` or Nginx rules) so that every URL — whether it is `/about`, `/users/profile`, or `/register` — is silently redirected to this single `index.php`. The URL stays clean in the browser, but every request is handled by reception first.

### What does this buy you?

A Front Controller gives you a single place to bootstrap the entire application — load the autoloader, open a database connection, start the session, register middleware, set error handlers. You write that setup code exactly once. In the old way, move a file or forget to paste the setup code somewhere, and entire sections of the site break silently.

---

## 4. Clean URLs: Why Do Websites Prefer Them?

**Analogy: A Street Address vs. GPS Coordinates**

If someone asks you to meet them at *"52 Baker Street, London"* — you understand immediately. If they send you `51.5237° N, 0.1585° W` — it is technically accurate but completely unreadable to a human. Both describe the same place; one is for machines, one is for people.

Clean URLs are the street address version of web navigation.

| Ugly URL | Clean URL |
|---|---|
| `example.com/index.php?page=users&action=profile&id=42` | `example.com/users/42/profile` |
| `example.com/index.php?page=blog&cat=news&post=17` | `example.com/blog/news/17` |
| `example.com/register.php` | `example.com/register` |

### The reasons clean URLs matter

**Readability.** A user glancing at `example.com/users/profile` immediately understands where they are. `example.com/index.php?page=users&action=profile` tells them nothing and exposes internal implementation details.

**Security.** Query-string URLs like `?page=register.php` reveal the underlying file structure of your server. An attacker can probe that structure. With a Front Controller and a Router, there are no real files mapped to those paths — the Router handles everything in memory, so there is nothing to probe.

**SEO.** Search engines treat clean URLs as more trustworthy and easier to index. A URL like `/blog/how-to-build-a-router` tells a search engine what the page is about. `?id=17&cat=4` tells it nothing.

**Shareability.** A clean URL can be read aloud, written on a business card, or shared in a message without confusion. Query strings full of `&`, `=`, and `?` characters are fragile and easy to mistype.

```apache
# .htaccess — the rule that makes clean URLs possible
# Every request that is NOT a real file or directory
# gets redirected silently to index.php

RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.+)$ /index.php [QSA,L]
```

This Apache rewrite rule is the invisible foundation. The user types `example.com/users/profile` — Apache checks that no actual file called `users/profile` exists, and quietly forwards the request to `index.php`. The Router then reads `/users/profile` and dispatches to the correct controller. The user never sees `index.php` at all.

---

## 5. Separation of Concerns: Why Not Put SQL Directly in HTML?

**Analogy: A Chef Who Also Serves, Cleans, and Does the Accounting**

Imagine a restaurant where the chef cooks the food, then runs out to serve it to the customer, then runs back to wash the dishes, then sits down to do the monthly accounts — all by themselves. Every time a customer makes a special request, the chef has to stop cooking, handle the request, and start over. The kitchen becomes chaos. Nothing gets done properly because one person is trying to do five jobs at once.

Mixing SQL directly into HTML files is exactly that chaos.

### What it looks like in practice — the wrong way

```php
<!-- products.php — this file is doing five jobs at once -->
<html>
<body>
  <h1>Our Products</h1>

  <?php
    // Database connection logic — inside an HTML file?
    $pdo = new PDO('mysql:host=localhost;dbname=shop', 'root', 'password');

    // Raw SQL query — inside an HTML file?
    $stmt = $pdo->prepare("SELECT * FROM products WHERE active = 1");
    $stmt->execute();
    $products = $stmt->fetchAll();
  ?>

  <?php foreach ($products as $product): ?>
    <div class="product">
      <h2><?= $product['name'] ?></h2>
      <p><?= $product['price'] ?></p>
    </div>
  <?php endforeach; ?>
</body>
</html>
```

### Why this is a serious problem

**Impossible to maintain.** Your database credentials (`root`, `password`) are scattered across dozens of HTML files. Change the database host and you have to find and update every single file that contains that connection string.

**Impossible to reuse.** You cannot use this query anywhere else without copying and pasting the entire block — SQL, connection logic, and all. Every copy becomes a maintenance liability.

**Impossible to test.** You cannot test the data-fetching logic independently of the HTML rendering. To run a test you have to render an entire web page.

**Security nightmare.** If a developer forgets to sanitize user input in one of those dozens of files, the raw SQL is right there, directly injectable. With a Model layer in between, you have one place to enforce sanitization for the entire application.

**Designer cannot touch the View.** A front-end designer who should only be adjusting HTML and CSS now has to navigate around PHP database code that they do not understand and must not accidentally delete.

### The correct separation

```php
<?php
// app/models/Product.php — the Model's only job is data
class Product extends Model
{
    public static function getAllActive(): array
    {
        return static::findAll(['active' => 1]);
    }
}
?>
```

```php
<?php
// app/controllers/SiteController.php — the Controller coordinates
class SiteController extends Controller
{
    public function products(): string
    {
        $products = Product::getAllActive(); // ask the Model for data
        return $this->render('products', ['products' => $products]); // hand it to the View
    }
}
?>
```

```php
<!-- views/products.php — the View's only job is display -->
<h1>Our Products</h1>

<?php foreach ($products as $product): ?>
  <div class="product">
    <h2><?= htmlspecialchars($product->name) ?></h2>
    <p><?= htmlspecialchars($product->price) ?></p>
  </div>
<?php endforeach; ?>
```

Now each file has **one job**. The Model does not know what the HTML looks like. The View does not know where the data came from. The Controller does not know either detail — it just passes a message between them. Change the database table structure and you fix it in the Model alone. Redesign the page and you edit the View alone. The course framework enforces this separation from the very first line of code, which is the central lesson of building an MVC framework from scratch.
