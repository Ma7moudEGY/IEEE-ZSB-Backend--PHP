# PHP Website Security & Hacking Protection — Notes

---

## Section 1: Introduction & Overview

> **Key Takeaway:** Never trust user-supplied data. Most security vulnerabilities stem from failing to validate or sanitize input from users.

---

## 1. What Is Security?

Security is the state of being protected or safe. In the web context, it means defending a publicly accessible system against those who would use it in ways it was not intended.

A website is like a house open to the general public. Anyone can walk in, so you must make it as difficult as possible for visitors to take what they are not allowed to have.

Security requires both **knowledge and action**. You cannot act on a threat you do not know about. Equally, knowledge without action is useless. It also takes time — expertise in security comes from real-world experience, and hackers are not static. They evolve, so the process of learning and patching is continuous.

Because of this, always update to the latest stable version of your software. Previous versions often have known vulnerabilities that have already been fixed upstream.

**The balance to strike:** adding too many security measures can block legitimate users, frustrating them into abandoning your service. A denial-of-service attack is a good example — locking an account after too many failed login attempts sounds reasonable, but a malicious competitor on an auction site could intentionally lock a rival's account by repeatedly entering wrong passwords. Banning the IP address temporarily is safer than locking the account itself.

---

## 2. What Is a Hacker?

A hacker is anyone who uses something in a way it was not intended. In the web security context, hackers attempt to break into websites or computer systems.

**Types of hackers:**

- **White Hat** — hired security professionals who test systems, report vulnerabilities, and get paid either way. These are the good guys.
- **Black Hat** — attackers who exploit vulnerabilities for personal gain, theft of processing power, or to build a botnet for later use.

**Black hat sub-types (by motivation):**

| Type | Description | Threat Level |
|------|-------------|--------------|
| Curious Users | Explore URLs and file structures out of curiosity, may stumble on secrets | Medium |
| Script Kiddies | Download and run pre-written exploit scripts without understanding them | Low–Medium |
| Thrill Seekers | Hack for fun; usually target large, high-profile systems | Low (for small sites) |
| Activists | Politically motivated; target governments and large organisations | Low |
| Trophy Hunters | Want the bragging rights of breaching a notable target | Low |
| Professionals | Hack for money; range from low-skill to highly advanced | **High** |

For most developers, curious users, script kiddies, and professionals are the most relevant threats to defend against.

---

## Section 2: Social Engineering

> **Objective:** Understand that hacking often begins outside the computer — through human manipulation rather than technical exploits.

---

## 1. What Is Social Engineering?

Social engineering is the act of persuading someone to voluntarily hand over confidential information. It is frequently easier to trick a person than to brute-force a system. The most famous hacker in history reportedly said his most successful method was simply picking up the phone and pretending to be a trusted authority figure.

---

## 2. Common Social Engineering Vectors

**Weak password hygiene** — people write passwords on sticky notes near their machines. No matter how secure the server is, a visible password is a breach.

**Dumpster diving** — physical trash from an office can contain phone bills, purchase receipts, email addresses, and even printed credentials. Shred sensitive documents.

**Keyloggers** — malicious software installed on a machine (via physical access or a malicious email attachment) that silently records every keystroke and sends the log to the attacker. Be careful what emails you open and who you give physical access to your machine.

**Social media reconnaissance** — security questions like "What is your dog's name?" or "What city were you born in?" are often answered publicly on social media profiles. An attacker can use this information to pass a password-reset flow and take over an account.

**Phishing** — sending a fake email that contains a link to a cloned version of a legitimate site. The user enters their credentials into the fake form, which captures the data and forwards it to the attacker before redirecting the user to the real site. The user may not notice they were asked to log in twice.

---

## 3. Defences

- Enable **two-step verification** (SMS code) on all sensitive systems.
- Train users to check the URL bar before entering credentials — a fake domain may differ by a single character.
- Treat any unsolicited email from a generic address (e.g. `gmail.com`) claiming to be a company with suspicion. Legitimate companies use their own domain for email.
- Never store credentials in plaintext near your workstation or in unshredded documents.

---

## Section 3: Keeping Code Private

> **Objective:** Separate sensitive PHP code from publicly accessible files and prevent directory listings from exposing the file structure.

---

## 1. Separate Private and Public Folders

All functions, classes, and configuration files should live in a folder that is not directly accessible from the web. The recommended structure is:

```
project/
├── private/          ← functions, classes, config (not web-accessible)
│   ├── functions.php
│   └── index.php     ← empty, prevents directory listing
└── public/           ← web root, pointed to by the server
    ├── index.php      ← entry point
    └── images/
        └── index.php  ← empty, prevents directory listing
```

When you deploy, point the server's document root at the `public/` folder. This means a browser cannot navigate to `../private/` — only the server-side PHP code can traverse upward in the file system to include private files.

From `public/index.php`, include private files using a relative path:

```php
include('../private/functions.php');
```

---

## 2. Preventing Directory Listings

By default, Apache (and most servers) will display a directory listing when no index file is present in a folder. This exposes your entire file structure.

**Solution 1 — add an empty `index.php` to every folder:**

```php
<?php // intentionally empty
```

When the server sees an `index.php`, it runs it instead of showing the listing. An empty file produces a blank page — which is exactly what you want.

**Solution 2 — use `.htaccess` to forbid indexes:**

```apache
Options -Indexes
```

This returns a 403 Forbidden response when someone tries to browse a directory directly. Combine both methods for maximum protection.

---

## 3. Always Use PHP Extensions for Sensitive Data

Never store sensitive configuration data (passwords, API keys, database credentials) in `.txt`, `.json`, or other formats that the server will serve as plain text.

```
# Bad — readable in any browser
config.json  →  { "db_password": "secret123" }

# Good — processed by PHP, never sent to the client
config.php   →  <?php $db_password = 'secret123';
```

If a user somehow obtains the URL to a `.json` file, they can read its contents directly. A `.php` file is executed by the server — the source is never transmitted.

---

## Section 4: Secure File Includes

> **Objective:** Prevent PHP code injection via the `include`/`require` functions, especially when the included filename is derived from user input.

---

## 1. The Vulnerability

A common pattern is to use a GET parameter to load different page files:

```php
$page = $_GET['page'];
include($page);
```

This is dangerous for two reasons:

**Path traversal** — an attacker can pass `../../etc/passwd` or `../../apache/logs/error.log` as the page parameter, potentially reading sensitive files from anywhere on the server.

**Arbitrary code execution** — `include` and `require` process any PHP tags they find inside the file, regardless of the file extension. A `.txt` or even a `.jpg` file containing `<?php phpinfo(); ?>` will execute if included.

---

## 2. Hiding PHP in an Image

A valid image file can have PHP code appended to its binary data using a hex editor or Notepad++. When uploaded as a profile photo and then included via a vulnerable include statement, the PHP executes:

```
image.jpg contents:
[binary image data...] <?php phpinfo(); ?>
```

The image still displays normally when loaded as an `<img>` tag, but if included with `include('image.jpg')`, the PHP runs.

---

## 3. Defences

**Do not pass the file extension from the URL.** Concatenate `.php` on the server side so a user cannot specify an arbitrary extension:

```php
$page = $_GET['page'];
include($page . '.php');
```

This alone is not sufficient if users can upload files.

**Crop all uploaded images.** Resizing an image rewrites every pixel. The appended PHP code is destroyed in the process.

**Whitelist permitted files using `glob()`.** Before including any file, verify it exists in an approved list of files from a controlled directory:

```php
$folder  = './includes/';
$files   = glob($folder . '*.php');   // collect all .php files in folder
$page    = $_GET['page'];
$target  = $folder . $page . '.php';

if (in_array($target, $files)) {
    require($target);
} else {
    echo 'File not found.';
}
```

This approach uses **whitelisting** — only files that already exist in the expected directory can be loaded. It is far more robust than trying to blacklist dangerous filenames.

**Use `file_get_contents()` instead of `include` when PHP execution is not needed.** This reads the file as a plain string and will not run any PHP tags inside it:

```php
echo file_get_contents($target);
```

---

## Section 5: Single Page Loading

> **Objective:** Reduce attack surface by routing all traffic through a single entry point (`index.php`) and including subpages dynamically.

---

## 1. The Problem with Multiple Entry Points

Every independent PHP file accessible from the browser is a potential entry point for an attacker. A site with `index.php`, `posts.php`, `login.php`, and `signup.php` has four separate entry points — each one must be independently secured.

Think of it like a house: the more windows and doors it has, the harder it is to secure. A house with one door is much easier to protect than one with many.

---

## 2. The Single Entry Point Pattern

Move all pages into an `includes/` directory and route everything through one `index.php`:

```
project/
├── public/
│   └── index.php      ← single entry point
└── private/
    └── includes/
        ├── index.php  ← empty (prevents directory listing)
        ├── home.php
        ├── posts.php
        ├── login.php
        ├── signup.php
        └── 404.php
```

The `index.php` entry point selects and includes the appropriate subpage:

```php
<?php

$folder   = '../private/includes/';
$files    = glob($folder . '*.php');
$page     = $_GET['page'] ?? 'home';
$filename = $folder . $page . '.php';

if (in_array($filename, $files)) {
    include($filename);
} else {
    include($folder . '404.php');
}
```

**Key advantage:** if a security issue is discovered in how files are included or the database is connected to, fixing it in one place fixes the entire site.

---

## 3. Using `.htaccess` for Clean URLs

The `index.php?page=login` URL format is both ugly and revealing. Using `.htaccess` rewrite rules, you can expose clean URLs like `/login` while the server still routes everything through `index.php`:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?url=$1 [QSA,L]
```

This rewrites any request that does not match an actual file or directory to `index.php`, passing the path as the `url` GET variable. Update `index.php` to read from `$_GET['url']` instead of `$_GET['page']`:

```php
$page = $_GET['url'] ?? 'home';
```

Now `/posts` in the browser maps to `includes/posts.php` on the server, and your entry point remains a single file.

---

## Section 6: Refactoring — Centralised Functions

> **Objective:** Extract repeated database connection and query logic into a shared `functions.php` file to reduce duplication and create a single point of change.

---

## 1. The Problem with Repeated Code

When every page connects to the database independently, a change to the connection logic (e.g. a new password, a different host, or a security patch) requires editing every single file. Similarly, if there is a vulnerability in the query logic, it exists in multiple places.

---

## 2. Creating `functions.php`

Extract the database connection into a `connect()` function and the query execution into a `db_read()` function inside `private/includes/functions.php`:

```php
<?php

function connect(): PDO
{
    $host   = 'localhost';
    $db     = 'security_db';
    $user   = 'root';
    $pass   = '';

    return new PDO("mysql:host={$host};dbname={$db}", $user, $pass);
}

function db_read(string $query): array|false
{
    $con  = connect();
    $stmt = $con->prepare($query);
    $stmt->execute();

    $data = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $data[] = $row;
    }

    return empty($data) ? false : $data;
}
```

**Key design decisions:**

- `connect()` returns a `PDO` instance. Every function that needs the database calls `connect()` internally — no caller needs to manage the connection object.
- `db_read()` accepts a query string and returns an array of rows or `false` if nothing was found.
- A separate `db_write()` function can be added for `INSERT`/`UPDATE`/`DELETE` operations, keeping read and write paths distinct and easy to audit.

---

## 3. Using the Functions

Include `functions.php` from the main `index.php` so it is available throughout the entire application:

```php
// public/index.php
require('../private/includes/functions.php');
```

Individual page files become much simpler:

```php
// private/includes/posts.php
$result = db_read('SELECT * FROM posts');

if ($result) {
    foreach ($result as $row) {
        echo '<h2>' . $row['title'] . '</h2>';
        echo '<p>'  . $row['body']  . '</p>';
    }
}
```

When a database problem arises, there is now exactly one function to inspect — not every page file on the site.

---

## 4. Why This Improves Security

Centralising database logic makes the application more resistant to attack because:

- There is one place to add input sanitisation, prepared statement enforcement, or connection error handling.
- Individual page files contain no raw database credentials or connection code.
- The overall code is smaller and easier to audit for vulnerabilities.

The next step beyond this procedural approach is to convert these functions into a class using object-oriented programming, which naturally enforces encapsulation and reduces accidental exposure of internals.

---
