# PHP MVC Research Questions

---

## 1. Database in MVC: Why Only the Model Talks to the Database

**Analogy: A Hospital's Medical Records Department**

Imagine a large hospital. Doctors, nurses, and receptionists all need patient information — but none of them walk directly into the medical records vault and pull files themselves. Instead, they submit a request to the records department, which retrieves exactly the information needed and hands it back in a controlled, standardised format. The records department is the only part of the hospital authorised to touch those files.

That is exactly how the Model works in MVC. The Controller and View never touch the database directly. They submit a request — the Model handles everything — and a clean result comes back.

In the MVC pattern, **the Model** is the only part of the application that should be allowed to talk directly to the database. It is responsible for managing the data, business rules, and logic of the application.

---

### Why This Rule Exists

**1. Security (The Model as a Security Gate)**

The Model acts as a controlled checkpoint for every database operation. By forcing all database communication through the Model, you guarantee that every query passes through the same security mechanism — PDO's prepared statements — before it ever reaches the database.

If Controllers were allowed to write their own SQL queries, a single developer making a mistake could expose the entire application to SQL injection. Centralising database access centralises the defence.

```php
<?php
// The Model enforces safe, parameterised queries for every operation
class Note extends Model
{
    public function save(): bool
    {
        $stmt = Application::$app->db->prepare(
            "INSERT INTO notes (title, body, user_id) VALUES (:title, :body, :user_id)"
        );
        $stmt->execute([
            ':title'   => $this->title,
            ':body'    => $this->body,
            ':user_id' => $this->userId,
        ]);
        return true;
    }
}
?>
```

Every save, update, and delete goes through this single class. There is no other way in.

---

**2. Abstraction (The "Need to Know" Basis)**

The Controller and View should be entirely "blind" to the type of database being used or how queries are written. Their job is not to know how data is stored — only to ask for it.

```
Controller asks:  "Give me the profile for user #10."
Model handles:    The SQL, the connection, the error checking.
Model returns:    A clean $user object.
Controller uses:  $user->firstName, $user->email — nothing more.
```

If your team later decides to switch from MySQL to PostgreSQL, the Controller and View do not change at all. Only the connection string in the Model's configuration changes. This insulation is the whole point of abstraction.

---

**3. The DRY Principle (Don't Repeat Yourself)**

If Controllers were allowed to write SQL directly, the same query would be duplicated across many files. Every time the schema changed, every duplicate would need updating individually — and one missed copy could cause a silent bug.

By keeping all queries inside the Model, there is one place to fix, one place to audit, and one place to test.

```
Without Model abstraction:       With Model abstraction:
─────────────────────────        ─────────────────────────
UserController    → raw SQL      UserController → User::findOne()
PostController    → raw SQL      PostController → User::findOne()
AdminController   → raw SQL      AdminController → User::findOne()
                                                     ↓
                                              One method.
                                              One query.
                                              One place to change.
```

---

## 2. Sensitive Information: Why Secrets Don't Belong in Source Code

**Analogy: A Master Key Hidden Under the Doormat**

A master key that opens every lock in a building is valuable — but only if it stays secret. If the building manager tapes a copy of that key under the front doormat, they have not really secured anything. Anyone who finds the mat finds the key. Sharing the code means sharing the mat, and sharing the mat means sharing the key.

Hardcoding database credentials directly into source code is the same mistake. The moment that code is committed to a repository, the secret is no longer secret.

---

### Why Separation of Secrets Matters

**1. Security and Version Control**

The most dangerous thing about hardcoding a password is that it becomes part of your code's history — permanently.

```php
<?php
// ❌ WRONG — The password is now in every git commit, forever
$pdo = new PDO("mysql:host=localhost;dbname=myapp", "root", "MyS3cr3tP@ssw0rd!");
?>
```

```php
<?php
// ✅ CORRECT — Credentials live in a file that git never sees
$pdo = new PDO(
    "mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'],
    $_ENV['DB_USER'],
    $_ENV['DB_PASS']
);
?>
```

```bash
# .gitignore — these files never leave your machine
.env
config/database.php
```

If a repository is ever made public — accidentally or deliberately — hardcoded passwords are immediately exposed to the world. A `.env` file added to `.gitignore` ensures your secrets never leave your local machine or secure server.

---

**2. Environment Flexibility**

The same application typically runs in three separate environments: development (on your laptop), staging (for testing), and production (the live site). Each environment has different database credentials.

```
# .env for development
DB_HOST=localhost
DB_NAME=myapp_dev
DB_USER=root
DB_PASS=password

# .env for production (on the server — never committed to git)
DB_HOST=prod-db.example.com
DB_NAME=myapp_prod
DB_USER=app_user
DB_PASS=Xy7$mQ2!rLpZ
```

Separating configuration from code allows the same application to run in all three environments without a single line of source code changing between them. You deploy the code; you configure the environment.

---

**3. Maintenance and Team Collaboration**

| Scenario | Hardcoded credentials | Separate config file |
|---|---|---|
| Database password changes | Must search every file for the old value | Update one line in one file |
| Junior developer joins | They can see production passwords in the code | They only get access to the dev `.env` |
| Security audit | Passwords appear in git log, pull requests, diffs | Passwords never appear in version control |
| Multiple environments | Requires editing source code between deployments | Each server has its own `.env` |

A single configuration file is both a security boundary and an operational convenience. It keeps credentials out of the hands of people who do not need them, and it makes maintenance straightforward.

---

## 3. PDO: The Database Abstraction Layer

**Analogy: A Universal Adaptor for International Travel**

When you travel between countries, your devices use the same internal components — but the wall socket is different everywhere. A universal adaptor lets you plug into any socket without rewiring the device. You change the adaptor, not the device.

PDO is PHP's universal adaptor for databases. Your application code stays exactly the same regardless of whether the database underneath is MySQL, PostgreSQL, SQLite, or Oracle.

**PDO** stands for **PHP Data Objects**. It is a database abstraction layer that provides a consistent interface for interacting with any supported database in PHP.

---

### Why PDO is Preferred Over `mysqli`

**1. Database Independence**

`mysqli` is wired specifically to MySQL. PDO supports over 12 different database drivers.

```php
<?php
// mysqli — locked to MySQL forever
$conn = new mysqli("localhost", "user", "pass", "mydb");

// PDO — change the DSN, and the rest of your code is untouched
$pdo = new PDO("mysql:host=localhost;dbname=mydb", "user", "pass");     // MySQL
$pdo = new PDO("pgsql:host=localhost;dbname=mydb", "user", "pass");     // PostgreSQL
$pdo = new PDO("sqlite:/path/to/mydb.sqlite");                          // SQLite
?>
```

If you later decide to migrate your Notes App from MySQL to PostgreSQL, you change one connection string. Every query, every Model method — untouched.

---

**2. Security via Prepared Statements**

PDO provides built-in protection against SQL injection by strictly separating SQL structure from user-supplied data.

```php
<?php
// ❌ Vulnerable — user input is concatenated directly into the query
$query = "SELECT * FROM users WHERE email = '" . $_POST['email'] . "'";

// ✅ Safe — PDO separates the command from the data
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
$stmt->execute([':email' => $_POST['email']]);
$user = $stmt->fetch();
?>
```

No matter what a user types into the email field — including malicious SQL — PDO's prepared statements treat it as plain data, never as executable code.

---

**3. Named Parameters**

PDO supports human-readable named placeholders, which make complex queries significantly easier to read and maintain. `mysqli` is limited to positional `?` markers.

```php
<?php
// mysqli — positional markers; must count carefully
$stmt = $conn->prepare("INSERT INTO notes (title, body, user_id) VALUES (?, ?, ?)");

// PDO — named parameters; self-documenting and order-independent
$stmt = $pdo->prepare(
    "INSERT INTO notes (title, body, user_id) VALUES (:title, :body, :user_id)"
);
$stmt->execute([':title' => $title, ':body' => $body, ':user_id' => $userId]);
?>
```

In a query with six or eight parameters, named placeholders prevent the kind of subtle ordering mistakes that positional markers make easy to introduce.

---

## 4. Prepared Statements: The Defence Against SQL Injection

**Analogy: A Form with Pre-Printed Fields**

When you fill in an official government form, the structure is fixed and printed in advance. There are labelled boxes where you write your name, your date of birth, your address. No matter what you write in the "Name" box — even if you write legal-sounding instructions — it is still just data in a box. The form's structure cannot be changed by what you write inside it.

Prepared statements work the same way. The SQL structure is fixed and sent to the database first. The user data is added afterwards, into labelled slots. The database has already committed to what the command does — the incoming values can only ever be data, never instructions.

---

### How Prepared Statements Work Step by Step

```
Step 1 — Send the template

Application → Database:
"SELECT * FROM users WHERE email = :email AND status = :status"

The database parses, compiles, and optimises this query structure.
It now knows exactly what command will run and where the values will go.
No data has arrived yet. Nothing executes.

─────────────────────────────────────────────────────

Step 2 — Send the data separately

Application → Database:
:email  = "user@example.com"
:status = "active"

The database plugs these values into the pre-compiled template.
Because the structure is already locked, the values cannot change the command —
they can only fill the slots.

─────────────────────────────────────────────────────

Result: The query runs safely.
Even if :email contained  "' OR 1=1 --",
the database treats it as a literal string, not as SQL.
```

```php
<?php
// Without prepared statements — a classic SQL injection target
$email = $_POST['email']; // Attacker enters: ' OR '1'='1
$query = "SELECT * FROM users WHERE email = '$email'";
// Resulting query: SELECT * FROM users WHERE email = '' OR '1'='1'
// This returns every row in the users table.

// With prepared statements — the injection attempt is harmless
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
$stmt->execute([':email' => $_POST['email']]);
// The database treats the entire input as a literal string.
// It looks for a user whose email is literally: ' OR '1'='1
// It finds none. The application is safe.
?>
```

---

## 5. Database Queries: Fetching One Row vs. Many Rows

**Analogy: Ordering at a Counter vs. Ordering a Buffet**

When you order a specific dish by name at a restaurant counter, the kitchen finds that one item and hands it to you. When you go to a buffet, you take a tray and collect multiple dishes. The two situations call for completely different approaches — even though both involve getting food from the kitchen.

Fetching data from a database works the same way. Sometimes you need exactly one row. Sometimes you need an entire collection.

---

### Fetching a Single Row

You need exactly one row when you are looking for a **unique entity** — something identified by a Primary Key or a Unique Constraint. This is handled by a `findOne()` method.

**Example: User Login**

```php
<?php
// The login Controller asks the Model for one specific user
public function login(Request $request, Response $response): string
{
    $model = new LoginForm();

    if ($request->isPost()) {
        $model->loadData($request->getBody());

        // findOne() returns a single User object, or null if not found
        $user = User::findOne(['email' => $model->email]);

        if ($user && password_verify($model->password, $user->passwordHash)) {
            Application::$app->login($user);
            $response->redirect('/');
            return '';
        }
    }

    return $this->render('auth/login', ['model' => $model]);
}
?>
```

```
User submits login form
        ↓
Controller passes the email to User::findOne()
        ↓
Model runs: SELECT * FROM users WHERE email = :email LIMIT 1
        ↓
Database returns exactly one row (or nothing)
        ↓
Controller checks the password against that single object
        ↓
Session is started for that specific person
```

Since every email address is unique, the database will return at most one row. You are not looking for a group — you are looking for one specific person.

---

### Fetching Multiple Rows

You need an array of rows when you are displaying **lists, feeds, or search results**. This is handled by a `findAll()` method, typically combined with a `WHERE` clause to filter the group.

**Example: The Notes Dashboard**

```php
<?php
// The Notes Controller asks the Model for all notes belonging to this user
public function index(Request $request, Response $response): string
{
    $currentUserId = Application::$app->user->id;

    // findAll() returns an array of Note objects — one per matching row
    $notes = Note::findAll(['user_id' => $currentUserId]);

    return $this->render('notes/index', ['notes' => $notes]);
}
?>
```

```php
<!-- views/notes/index.php — the View loops through every note in the array -->
<div class="notes-grid">
  <?php foreach ($notes as $note): ?>
    <div class="note-card">
      <h3><?= htmlspecialchars($note->title) ?></h3>
      <p><?= htmlspecialchars($note->body) ?></p>
    </div>
  <?php endforeach; ?>
</div>
```

```
User clicks "My Notes"
        ↓
Controller passes user_id to Note::findAll()
        ↓
Model runs: SELECT * FROM notes WHERE user_id = :user_id
        ↓
Database returns an array of rows — one per note
        ↓
Controller passes the array to the View
        ↓
View loops through with foreach and renders each note as a card
```

---

### Choosing the Right Method

| | `findOne()` | `findAll()` |
|---|---|---|
| Returns | A single object (or `null`) | An array of objects |
| Use when | You know the result is unique | You expect zero or more rows |
| Typical filter | Primary Key (`id`) or Unique field (`email`) | Foreign Key (`user_id`) or category |
| View behaviour | Accesses properties directly (`$user->name`) | Loops with `foreach` |
| Example use cases | Login, profile page, edit form | Dashboard, search results, activity feed |

The rule is straightforward: if you are looking for **one specific thing**, use `findOne()`. If you are looking for **a group of things**, use `findAll()`.
