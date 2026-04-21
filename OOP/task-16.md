# Object-Oriented PHP

---

## 1. Traits: How Do They Solve the Multiple Inheritance Problem?

**Analogy: A Skill Badge System**

Imagine a school where every student can only have one major (their parent class). A student majoring in Computer Science cannot also major in Business — they can only have one. But any student, regardless of major, can earn skill badges: a "Public Speaking" badge, a "Leadership" badge, a "Time Management" badge. These badges are not majors — they are reusable bundles of skill that any student can pick up and attach to their profile.

That is exactly what **Traits** are in PHP.

- **The Class** = the student with one major
- **The Trait** = a skill badge that any class can attach with `use`

PHP only allows a class to `extend` **one** parent. Traits solve this by letting you inject a block of reusable methods into any class — without forcing an inheritance relationship at all.

```php
<?php
trait Loggable {
    public function log(string $message): void {
        echo "[LOG] " . get_class($this) . ": {$message}\n";
    }
}

trait Timestampable {
    public function getCreatedAt(): string {
        return date("Y-m-d H:i:s");
    }
}

class Order {
    use Loggable, Timestampable; // attaches both badges ✅

    public string $id;

    public function __construct(string $id) {
        $this->id = $id;
    }
}

class User {
    use Loggable; // attaches only the Loggable badge ✅

    public string $username;

    public function __construct(string $username) {
        $this->username = $username;
    }
}

$order = new Order("ORD-001");
$order->log("Order created.");       // [LOG] Order: Order created.
echo $order->getCreatedAt() . "\n";  // 2025-08-01 14:00:00

$user = new User("john_doe");
$user->log("User logged in.");       // [LOG] User: User logged in.
?>
```

### When should you use a Trait?

Use a Trait when you have functionality that needs to be shared between classes that do **not** share a logical parent-child relationship. `Order` and `User` are completely unrelated classes — there is no sensible parent class they could both extend. A Trait lets them share `log()` without forcing a fake relationship.

---

## 2. Namespaces: What Are They and How Do They Prevent Collisions?

**Analogy: Two "Main Street" Roads in Different Cities**

Every country has a city with a "Main Street." If someone just says *"go to Main Street,"* you have no idea which one they mean. But if they say *"go to Main Street, Cairo, Egypt"* — suddenly it is perfectly clear, even though thousands of other cities have a street with the exact same name.

**Namespaces** work the same way. They give each class a fully qualified address so that two classes with the same short name never conflict.

```php
<?php
// File: App/Models/User.php
namespace App\Models;

class User {
    public function getType(): string {
        return "App Model User";
    }
}
?>

<?php
// File: Auth/Providers/User.php
namespace Auth\Providers;

class User {
    public function getType(): string {
        return "Auth Provider User";
    }
}
?>

<?php
// File: index.php
require "App/Models/User.php";
require "Auth/Providers/User.php";

use App\Models\User as AppUser;
use Auth\Providers\User as AuthUser;

$appUser  = new AppUser();
$authUser = new AuthUser();

echo $appUser->getType();  // App Model User
echo $authUser->getType(); // Auth Provider User
?>
```

### How does this prevent naming collisions?

Without namespaces, including both files above would cause a fatal error — PHP would see two classes both named `User` and crash. With namespaces, `App\Models\User` and `Auth\Providers\User` are treated as completely different classes even though their short names are identical. The `use ... as` syntax gives each one a local alias so they can coexist in the same file without any conflict.

---

## 3. Autoloading: What Is It and How Does It Save Time?

**Analogy: A Library with a Smart Librarian**

Imagine a library where, every time you want a book, you have to walk to the shelves yourself and carry every single book you might need to your desk before you start reading — even books you may never open. That is how PHP worked before autoloading: a long list of `require` statements at the top of every file, fetching every class manually.

Now imagine a smart librarian who watches you work. The moment you reach for a book, they silently fetch it for you in an instant. You never have to think about it again. That is **Autoloading**.

```php
<?php
// Without autoloading — manual requires for every single class
require "app/Models/User.php";
require "app/Models/Order.php";
require "app/Services/PaymentService.php";
require "app/Services/EmailService.php";
// ...this list grows forever as the project grows

// -----------------------------------------------

// With a custom autoloader using spl_autoload_register
spl_autoload_register(function (string $className): void {
    // Convert namespace separators to directory separators
    $filePath = str_replace("\\", "/", $className) . ".php";
    if (file_exists($filePath)) {
        require $filePath;
    }
});

// PHP now auto-loads the file the moment this line is reached
$user = new App\Models\User(); // Automatically loads app/Models/User.php ✅
?>

<?php
// With Composer (the modern standard) — just one line in your entry point
require "vendor/autoload.php";

// Composer maps App\Models\User -> src/Models/User.php automatically
$user  = new App\Models\User();
$order = new App\Models\Order();
// No manual requires needed at all ✅
?>
```

### Why does this save time?

Before autoloading, as a project grew, the top of every file became a long, fragile list of manual includes. Forget one file, or move a class to a different folder, and everything broke. Autoloading — especially through **Composer's PSR-4 autoloader** — maps namespaces directly to folder paths, so you define the rule once and PHP handles every class load automatically from that point forward.

---

## 4. Magic Methods (`__get` and `__set`): What Are They For?

**Analogy: A Receptionist Controlling Access**

Imagine a building where some rooms are locked. You cannot just walk in directly. Instead, there is a receptionist at the front desk. When you try to enter a locked room, the receptionist intercepts you — they check if you are allowed, and either let you in or redirect you. When you try to leave something in a locked room, the receptionist checks if that is permitted before accepting it.

`__get` and `__set` are that receptionist for your class properties.

- **`__get($name)`** is triggered automatically when code tries to **read** a property that is inaccessible or does not exist.
- **`__set($name, $value)`** is triggered automatically when code tries to **write** to a property that is inaccessible or does not exist.

```php
<?php
class Product {
    private array $data = [];
    private array $readOnly = ["sku"];

    public function __construct(string $sku) {
        $this->data["sku"] = $sku;
    }

    // Triggered automatically when reading an inaccessible property
    public function __get(string $name): mixed {
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }
        return null;
    }

    // Triggered automatically when writing to an inaccessible property
    public function __set(string $name, mixed $value): void {
        if (in_array($name, $this->readOnly)) {
            echo "Error: '{$name}' is read-only and cannot be changed.\n";
            return;
        }
        $this->data[$name] = $value;
    }
}

$product = new Product("SKU-999");

// Triggers __set — stores the value in the internal $data array
$product->name  = "Wireless Mouse";
$product->price = 49.99;

// Triggers __get — reads the value from the internal $data array
echo $product->name;  // Wireless Mouse
echo $product->sku;   // SKU-999

// Triggers __set — blocked because "sku" is read-only
$product->sku = "HACKED"; // Error: 'sku' is read-only and cannot be changed.
?>
```

### When are they useful?

They are commonly used to add **validation logic** before a value is stored, to create **virtual read-only properties**, or to build **dynamic data containers** where properties are stored internally in an array rather than declared explicitly on the class. The class above has no declared `$name`, `$price`, or `$sku` properties — they all live inside the private `$data` array, yet they behave like normal properties from the outside.

---

## 5. Static Methods and Properties: What Does `static` Mean?

**Analogy: A Scoreboard vs. a Player's Personal Score**

In a football stadium, every player has their own personal stats on their own jersey — those are **instance properties**, unique per object. But the scoreboard at the top of the stadium is shared by everyone. It does not belong to any single player — it belongs to the **game itself**. Every player, every spectator, every camera sees the exact same number.

A `static` property is that scoreboard. It belongs to the **class itself**, not to any individual object instance.

```php
<?php
class MathHelper {
    // Static method — belongs to the class, not an instance
    public static function square(float $number): float {
        return $number * $number;
    }

    public static function percentage(float $value, float $total): float {
        return ($value / $total) * 100;
    }
}

// No object needed — called directly on the class with ::
echo MathHelper::square(6);           // 36
echo MathHelper::percentage(45, 200); // 22.5

// -----------------------------------------------

class DatabaseConnection {
    private static int $connectionCount = 0; // shared scoreboard

    public function __construct() {
        self::$connectionCount++; // every new object updates the shared count
    }

    public static function getConnectionCount(): int {
        return self::$connectionCount;
    }
}

$db1 = new DatabaseConnection();
$db2 = new DatabaseConnection();
$db3 = new DatabaseConnection();

// The static property is shared across all instances
echo DatabaseConnection::getConnectionCount(); // 3
?>
```

### Do you need `new` to access a static method?

**No.** Static members are accessed directly on the class using the `::` (double colon) operator — `ClassName::methodName()` — with no object required. This makes them ideal for **utility or helper methods** that do not depend on object state, and for **shared data** that must be consistent across every instance of a class.

> `self::` is used *inside* the class to refer to its own static members. `ClassName::` is used from *outside* the class. Both use the `::` operator — never `->`.
