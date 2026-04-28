# PHP MVC Framework: Controllers, Views, and Data Passing

---

## 1. The Controller's Job: What Happens When a User Clicks "View Profile"?

**Analogy: A Restaurant Waiter Taking Your Order**

Imagine you are sitting at a restaurant. You tell the waiter you want the house special. The waiter does not cook the food themselves — they walk to the kitchen, give the order to the chef, wait for the plate, and bring it back to your table. You never went into the kitchen. The chef never came to your table.

That is exactly what a Controller does when a user clicks "View Profile."

When the user clicks the button, an HTTP request is sent to the application. The **Router** reads the URL and method and dispatches it to the appropriate Controller action. The Controller then:

1. **Receives the request** — it reads the incoming HTTP request and identifies what the user wants (e.g., the profile for a specific user).
2. **Asks the Model for data** — the Controller calls the appropriate Model method, such as `User::findOne()`, passing in any identifiers needed (like a user ID).
3. **Passes the data to the View** — once the Model returns the data (a user object with name, email, status, etc.), the Controller hands it to the correct View template for rendering.
4. **Returns the response** — the Controller returns the fully rendered HTML page back to the user.

The Controller itself does **none** of the actual database work and **none** of the HTML rendering. It is purely the coordinator — the waiter between the kitchen (Model) and the dining room (View).

```php
<?php
// A simplified Controller action for viewing a user profile
class UserController extends Controller
{
    public function profile(Request $request, Response $response): string
    {
        $userId = $request->getBody()['id'];

        // Step 1: Ask the Model for the data
        $user = User::findOne(['id' => $userId]);

        if (!$user) {
            // No user found — show a 404
            http_response_code(404);
            return $this->render('_404');
        }

        // Step 2: Hand the data to the View and return the result
        return $this->render('user/profile', ['user' => $user]);
    }
}
?>
```

```
User Clicks "View Profile"
          ↓
      [Router]       ← matches /users/profile via GET
          ↓
   [Controller]      ← receives the request
          ↓
      [Model]        ← fetches user data from the database
          ↓
   [Controller]      ← receives the data back
          ↓
       [View]        ← renders the profile HTML
          ↓
    User sees the finished profile page
```

### Why the Controller must not do the work itself

If the Controller contained SQL queries, it would be mixing two responsibilities into one. If the Controller contained HTML, it would be mixing three. The whole point of MVC is that each part has exactly **one job**. The Controller's job is to coordinate — nothing more.

---

## 2. Dynamic Views: Static HTML vs. a Dynamic PHP View

**Analogy: A Printed Flyer vs. a Name Badge at a Conference**

A printed flyer is the same for every single person who picks it up. It says what it says and nothing can change. A conference name badge, by contrast, has the same layout for everyone, but the name printed on it belongs specifically to you. The template is shared; the content is personal.

A **static HTML file** is the printed flyer. A **dynamic PHP View** is the name badge.

### Static HTML

A static HTML file contains only fixed content. Every visitor who loads the page sees exactly the same text and values. There is no awareness of who the user is, what is in the database, or what was submitted in a form.

```html
<!-- static profile.html — exactly the same for everyone -->
<!DOCTYPE html>
<html>
<body>
  <h1>Welcome, John Doe</h1>
  <p>Email: john@example.com</p>
</body>
</html>
```

The name "John Doe" is hardcoded. Change the user and the file must be manually rewritten.

### Dynamic PHP View

A dynamic PHP View is a template. It contains HTML structure combined with PHP expressions that are replaced with real data at the moment the page is rendered. The layout stays constant; the values come from whatever the Controller passes in.

```php
<!-- views/user/profile.php — different content for every user -->
<h1>Welcome, <?= htmlspecialchars($user->firstName) ?> <?= htmlspecialchars($user->lastName) ?></h1>
<p>Email: <?= htmlspecialchars($user->email) ?></p>
<p>Member since: <?= $user->createdAt ?></p>
```

When this file is rendered, PHP replaces every `<?= ... ?>` expression with the actual value from the `$user` object. If user A visits, their name appears. If user B visits, theirs does. The template file never changes — only the data flowing into it does.

### The key distinction

| | Static HTML | Dynamic PHP View |
|---|---|---|
| Content | Fixed, never changes | Generated fresh per request |
| Data source | Hardcoded in the file | Passed in by the Controller |
| Reusability | One file, one page | One file, unlimited pages |
| Database awareness | None | Displays whatever the Model fetched |

Dynamic Views are also organised inside **layouts** — shared wrapper templates that contain the navigation bar, the `<head>` section, and the footer. Individual view files are slotted into that layout, so shared structure is written only once.

```php
<?php
// views/layouts/main.php — the shared wrapper
?>
<!DOCTYPE html>
<html>
<head>
  <title>My App</title>
  <link rel="stylesheet" href="/assets/styles.css">
</head>
<body>
  <nav><!-- navigation bar written once --></nav>

  <!-- Each page's unique content is injected here -->
  <?php echo $content ?>

  <footer><!-- footer written once --></footer>
</body>
</html>
```

A view for a registration form is slotted into `$content` — the nav and footer appear automatically on every page without being duplicated.

---

## 3. Data Passing: How Does a Controller Get Data into a View?

**Analogy: A Waiter Carrying a Plate to Your Table**

The chef (Model) prepares a dish. The waiter (Controller) takes that dish on a tray and carries it to your table. The waiter does not eat the food and does not cook it — they simply transport it and set it in front of you. The dining room (View) then presents it to you exactly as it arrived.

This is how data flows from the Controller into a View.

### The mechanism: the `render()` method

Controllers in the course framework inherit a `render()` method from the base `Controller` class. This method accepts two arguments: the name of the view file to render, and an **associative array** of data to make available inside that view.

```php
<?php
// Inside the Controller action
public function profile(Request $request, Response $response): string
{
    // 1. Fetch the data from the Model
    $user = User::findOne(['id' => 42]);
    $posts = Post::findAll(['user_id' => 42]);

    // 2. Pass the data into the View via the render() method
    return $this->render('user/profile', [
        'user'  => $user,
        'posts' => $posts,
    ]);
}
?>
```

### What `render()` does internally

The `render()` method takes the data array, **extracts** each key as a separate variable, and then includes the view file. The PHP `extract()` function turns `['user' => $userObject]` into a `$user` variable that is available directly inside the view template.

```php
<?php
// Inside the base Controller class
public function render(string $view, array $params = []): string
{
    // extract() turns array keys into local variables
    // ['user' => $obj, 'posts' => $arr]
    // becomes $user and $posts inside the view
    extract($params);

    ob_start();
    include __DIR__ . "/../views/{$view}.php";
    $content = ob_get_clean();

    // Slot the rendered content into the layout
    include __DIR__ . "/../views/layouts/main.php";

    return '';
}
?>
```

### Inside the View

Once `extract()` has run, the view file can use those variables directly — no special syntax, just plain PHP variables:

```php
<!-- views/user/profile.php -->
<div class="profile">
  <h1><?= htmlspecialchars($user->firstName) ?> <?= htmlspecialchars($user->lastName) ?></h1>
  <p>Email: <?= htmlspecialchars($user->email) ?></p>
</div>

<h2>Recent Posts</h2>
<?php foreach ($posts as $post): ?>
  <div class="post">
    <h3><?= htmlspecialchars($post->title) ?></h3>
    <p><?= htmlspecialchars($post->body) ?></p>
  </div>
<?php endforeach; ?>
```

### Passing a Model with errors back to a form

Data passing is not only used for displaying fetched records — it is equally important when a form fails validation. The Controller passes the **Model object itself** (which already holds the validation error messages) back into the View, so the form can re-render with the errors displayed inline:

```php
<?php
public function register(Request $request, Response $response): string
{
    $model = new User();

    if ($request->isPost()) {
        $model->loadData($request->getBody());

        if ($model->validate() && $model->save()) {
            // Success — set a flash message and redirect
            Application::$app->session->setFlash('success', 'Thanks for registering!');
            $response->redirect('/');
            return '';
        }
    }

    // Validation failed (or first visit) — pass the model with its errors to the View
    return $this->render('auth/register', [
        'model' => $model,   // The View reads $model->errors to display messages
    ]);
}
?>
```

```php
<!-- views/auth/register.php — the View reads errors directly from the Model -->
<form method="POST" action="/register">
  <input type="text" name="firstName" value="<?= $model->firstName ?>">

  <?php if ($model->hasError('firstName')): ?>
    <span class="error"><?= $model->getFirstError('firstName') ?></span>
  <?php endif; ?>

  <!-- ... more fields ... -->

  <button type="submit">Register</button>
</form>
```

### The complete data flow in one picture

```
Database
   ↑
[Model]          ← fetches or receives data
   ↓
[Controller]     ← stores result in a variable
   ↓
render('view', ['user' => $user])
   ↓
extract(['user' => $user])   ← $user is now a live variable
   ↓
[View template]  ← uses $user->firstName, $user->email, etc.
   ↓
Finished HTML page sent to the browser
```

The View has **no idea** where the data came from. It simply uses the variables it finds. The Controller has **no idea** what the HTML looks like. It simply hands the data over. This clean handoff — via the `render()` method and a plain associative array — is the backbone of every page that MVC renders.
