# Object-Oriented PHP

---

## 1. Class vs. Object: What's the Difference?

**Analogy: Apple's Blueprint and the iPhone**

Think of **Apple's design team** creating a detailed blueprint for a new iPhone. That blueprint specifies everything about what an iPhone *is* — it has a screen size, RAM, storage capacity, and a color. The blueprint itself is not a physical product you can hold or use. It is simply the master plan.

- **The Class** = Apple's blueprint/design
- **The Object** = The actual iPhone manufactured from that blueprint

When China's factories receive the blueprint, they produce *many individual iPhones* from it. Each iPhone is a separate, real device with its own specific values (e.g., one might be 256GB gold, another 128GB silver). All of them were created from the *same* blueprint, but each is its own independent product.

In PHP, a **class** is the template or plan you define in code. An **object** is a concrete instance you create *from* that class using the `new` keyword:

```php
class AppleDevice {
    public $ram;
    public $screenSize;
    public $color;
}

$iPhone6Plus = new AppleDevice(); // Object 1
$iPhone7Plus = new AppleDevice(); // Object 2
```

Both objects share the same structure (defined by the class), but their property values can differ independently.

---

## 2. `$this` vs. `self::` — When to Use Each

These two keywords both reference things *inside* a class, but they point to different things:

### `$this`
- Refers to the **current object** (instance) that is being used at runtime.
- Used to access **properties** and **methods** that belong to a specific object.
- Its value changes depending on which object called the method.
- Uses the `->` (object operator) syntax.

```php
public function getSpecs() {
    echo "RAM: " . $this->ram;
}
```

Here, `$this->ram` refers to the `ram` property of whichever specific iPhone object called `getSpecs()`.

### `self::`
- Refers to the **class itself**, not any particular object.
- Used to access **constants** and **static members** that belong to the class as a whole.
- Its value never changes — it always refers to the class where it is written.
- Uses the `::` (scope resolution operator) syntax.
- Does **not** use a `$` dollar sign prefix.

```php
const MIN_NAME_LENGTH = 5;

public function setOwnerName($name) {
    if (strlen($name) < self::MIN_NAME_LENGTH) {
        echo "Name too short!";
    }
}
```

**Summary:** Use `$this` when you need the value of a *specific object's* property (something that varies per object). Use `self::` when you need a *class-level constant or static value* that is fixed and shared across all objects.

---

## 3. Access Modifiers (Encapsulation): `public`, `protected`, `private`

Access modifiers (also called **visibility markers**) control where a property or method can be accessed from. There are three in PHP:

| Modifier    | Accessible From                                      |
|-------------|------------------------------------------------------|
| `public`    | Anywhere — inside the class, outside, and in child classes |
| `protected` | Inside the class and in child (subclass) classes only |
| `private`   | Inside the class only — nowhere else                |

### Why make a property `private`?

Imagine a user registration system. A user's **password** should never be readable or changeable directly from outside the class. Making it `private` enforces this:

```php
class User {
    public $username;
    private $password;

    public function setPassword($newPassword) {
        // We can add validation or hashing here
        $this->password = password_hash($newPassword, PASSWORD_DEFAULT);
    }
}

$user = new User();
$user->username = "ahmed";   // ✅ Works — public
$user->password = "1234";    // ❌ Error — private, cannot be set directly
$user->setPassword("1234");  // ✅ Works — controlled through a method
```

This is the core idea of **encapsulation**: hiding sensitive internal data and only exposing controlled ways to interact with it. It prevents accidental or malicious modification and lets you add logic (like hashing) in one place.

---

## 4. Typed Properties in PHP

**Typed properties** allow you to declare what *data type* a class property must hold. Without a type, a property can accidentally receive the wrong kind of value.

### Without typed properties (risky):
```php
class AppleDevice {
    public $ram;         // Could be a string, integer, array — anything
    public $screenSize;
}

$phone = new AppleDevice();
$phone->ram = "three";  // No error, but causes bugs later
```

### With typed properties (safe):
```php
class AppleDevice {
    public int $ram;
    public float $screenSize;
    public string $color;
}

$phone = new AppleDevice();
$phone->ram = "three";  // ❌ Fatal error: must be int
$phone->ram = 3;        // ✅ Correct
```

### How they help prevent bugs:
- PHP will throw a **fatal error** immediately if you assign the wrong type, rather than letting a bad value silently propagate through your application.
- They make your code **self-documenting** — anyone reading the class instantly knows what type each property expects.
- They reduce the need for manual type-checking inside methods.

Typed properties were introduced in **PHP 7.4** and are considered best practice in modern PHP development.

---

## 5. The `__construct()` Method

### What is it?

`__construct()` is a special **magic method** that PHP automatically calls the moment you create a new object with `new`. It is the class's *constructor* — a setup function that runs immediately upon object creation.

### Basic syntax:
```php
class AppleDevice {
    public string $ram;
    public float $screenSize;
    public string $color;

    public function __construct(string $ram, float $screenSize, string $color) {
        $this->ram    = $ram;
        $this->screenSize = $screenSize;
        $this->color  = $color;
    }
}
```

### Why is it useful to pass arguments into the constructor?

Without a constructor, you'd have to set every property manually after creating each object:

```php
// Without constructor — repetitive and error-prone
$iphone6 = new AppleDevice();
$iphone6->ram = "2GB";
$iphone6->screenSize = 5.5;
$iphone6->color = "Gold";
```

With a constructor, you set everything in one clean line at the moment of creation:

```php
// With constructor — concise and safe
$iphone6 = new AppleDevice("2GB", 5.5, "Gold");
$iphone7 = new AppleDevice("4GB", 5.8, "Silver");
```

### Key benefits:
- **Required setup**: You can ensure that an object is never created without the data it needs — the properties are initialized immediately.
- **Cleaner code**: Object creation becomes a single, readable statement.
- **Validation**: You can add logic inside `__construct()` to validate the arguments before assigning them, guaranteeing the object starts in a valid state.
- **Consistency**: All objects of the class are guaranteed to have their core properties set from the start.

---
