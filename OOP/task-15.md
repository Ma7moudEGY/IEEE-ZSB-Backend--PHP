# Object-Oriented PHP

---

## 1. Inheritance: What is the Main Benefit?

**Analogy: A Phone Factory with a Shared Blueprint**

Imagine Apple has a master blueprint for a generic device — it defines shared specs like RAM, screen size, storage, and color. Now Sony wants to build their own phone. Instead of designing everything from scratch, Sony takes Apple's blueprint and *extends* it, keeping all the shared specs and adding their own extras on top (like a camera rating unique to Sony).

- **The Parent Class** = Apple's master blueprint
- **The Child Class** = Sony's blueprint, which inherits everything from Apple and adds more

This is exactly how Inheritance works in PHP. A child class uses the `extends` keyword to inherit all properties and methods from a parent class automatically.

```php
// Parent class
class AppleDevice {
    public string $ram;
    public float $screenSize;
    public string $color;

    public function changeSpecs(string $r, float $s, string $c): void {
        $this->ram        = $r;
        $this->screenSize = $s;
        $this->color      = $c;
    }
}

// Child class — inherits everything from AppleDevice
class SonyDevice extends AppleDevice {
    public string $camera; // extra property unique to Sony
}

$phone = new SonyDevice();
$phone->changeSpecs('4GB', 6.1, 'Black'); // inherited from AppleDevice ✅
$phone->camera = '50MP';                  // Sony's own property ✅
```

### Why is this useful?

The biggest benefit of Inheritance is **code reuse and dynamic updating**. If you add a new property or method to the parent class, every child class that inherits from it automatically gains that feature — with zero repetition.

For example, if you add a `$screen = "LCD"` property to `AppleDevice`, every class extending it (SonyDevice, HuaweiDevice, etc.) will instantly have it without touching their code at all.

---

## 2. The `final` Keyword: What Does It Do?

**Analogy: A Sealed Recipe**

Imagine a chef gives you a recipe card and stamps **FINAL** on it. You can cook from it, but you are not allowed to change or adapt it — not even one ingredient. That is exactly what `final` does in PHP.

The `final` keyword can be applied in two ways:

### `final` on a Method

When you mark a method as `final`, no child class can override (rewrite) that method. It must be used exactly as defined in the parent.

```php
class AppleDevice {
    final public function sayHello(): void {
        echo "Welcome to Apple!";
    }
}

class SonyDevice extends AppleDevice {
    // ❌ Fatal Error: Cannot override final method AppleDevice::sayHello()
    public function sayHello(): void {
        echo "Welcome to Sony!";
    }
}
```

### `final` on a Class

When you mark an entire class as `final`, no other class can extend it at all.

```php
final class PaymentProcessor {
    public function charge(float $amount): void {
        // secure, locked payment logic
    }
}

// ❌ Fatal Error: Class cannot extend final class PaymentProcessor
class FakeProcessor extends PaymentProcessor {}
```

### Why would a developer use `final`?

- **Security**: Prevent child classes from bypassing critical logic like authentication or payment handling.
- **Stability**: Protect a core utility class from being accidentally broken by inheritance.
- **Design intent**: Signal clearly to other developers — *"this is complete and intentional, do not extend it."*

---

## 3. Overriding Methods: What Does It Mean?

**Analogy: Customizing a Inherited Recipe**

You inherit a family recipe (the parent method). The dish is great, but you want to add your own twist. You write your own version of the recipe under the same name — that is overriding.

In PHP, overriding means a child class defines its own version of a method that already exists in the parent, using the **exact same method name**. When that method is called on a child object, PHP runs the child's version instead of the parent's.

```php
class AppleDevice {
    public function welcome(): void {
        echo "Welcome to Apple!";
    }
}

class SonyDevice extends AppleDevice {
    // Override the parent's welcome() method
    public function welcome(): void {
        echo "Welcome to Sony!";
    }
}

$apple = new AppleDevice();
$apple->welcome(); // Output: Welcome to Apple!

$sony = new SonyDevice();
$sony->welcome();  // Output: Welcome to Sony!
```

### Calling the Original Parent Method with `parent::`

If you want to keep the parent's logic *and* add to it, you can call the original method from inside the override using `parent::methodName()`.

```php
class SonyDevice extends AppleDevice {
    public function welcome(): void {
        parent::welcome();           // Runs: "Welcome to Apple!"
        echo " — and Sony too!";     // Then adds Sony's message
    }
}

$sony = new SonyDevice();
$sony->welcome();
// Output: Welcome to Apple! — and Sony too!
```

> **Important:** If you want to add new behaviour to an inherited method (like adding a `$camera` parameter), it is better to create a brand new method with a different name rather than breaking the original method's signature. Rewriting an inherited method with a different signature is not true overriding — it causes compatibility errors.

---

## 4. Abstract Class vs. Interface: What is the Difference?

Both abstract classes and interfaces act as **templates** that force other classes to follow a certain structure. However, they differ in important ways.

### Abstract Class

An abstract class is defined with the `abstract` keyword. It is a class you **cannot instantiate directly** — it exists only to be extended by other classes. It can contain:

- Regular methods **with full implementations** (working code inside them)
- Abstract methods **with no body** — just a signature that child classes *must* implement
- Regular properties

```php
abstract class MakeDevice {
    public string $ram; // regular property

    // Regular method — has a full body
    public function sayHello(): void {
        echo "Hello from the device!";
    }

    // Abstract method — NO body, child class MUST implement this
    abstract public function sayBye(): void;
}

class AppleDevice extends MakeDevice {
    // Child class MUST provide a body for sayBye()
    public function sayBye(): void {
        echo "Goodbye from Apple!";
    }
}

// ❌ Cannot instantiate abstract class
$device = new MakeDevice();

// ✅ Can instantiate the child
$apple = new AppleDevice();
$apple->sayBye(); // Output: Goodbye from Apple!
```

The abstract class acts like a professor who sets the rules: *"You must complete this task (method), but how you complete it is up to you."*

### Interface

An interface is a **pure contract**. It defines only method signatures — no implementations, no properties. Any class that `implements` an interface must provide a body for every single method declared in it.

```php
interface Printable {
    public function printOut(): void; // no body — just the signature
}

interface Scannable {
    public function scan(): void;
}
```

### Key Differences

| Feature | Abstract Class | Interface |
|---|---|---|
| Can have implemented methods? | ✅ Yes | ❌ No — signatures only |
| Can have properties? | ✅ Yes | ❌ No (constants only) |
| Keyword used by child | `extends` | `implements` |
| Multiple inheritance | ❌ One parent only | ✅ A class can implement many |
| Can be instantiated? | ❌ No | ❌ No |

### Can a class implement multiple interfaces?

**Yes.** This is one of the biggest advantages interfaces have over abstract classes. A class can only `extend` one parent, but it can `implement` as many interfaces as needed.

```php
interface Printable {
    public function printOut(): void;
}

interface Scannable {
    public function scan(): void;
}

// One class implementing multiple interfaces ✅
class AllInOnePrinter implements Printable, Scannable {
    public function printOut(): void { echo "Printing..."; }
    public function scan(): void    { echo "Scanning...";  }
}
```

---

## 5. Polymorphism: What Is It?

**Analogy: The Word "Cut"**

Shout the word **"cut"** in a room containing a surgeon, a barber, and a film director. All three hear the same word — but each one reacts completely differently:

- The **surgeon** begins making an incision on a patient.
- The **barber** starts trimming someone's hair.
- The **director** stops the camera and ends the scene.

Same method name (`cut`), three completely different behaviours. This is **Polymorphism** — which literally means *"many forms"* (from the Latin *poly* = many, *morphism* = forms).

### The Formal Definition

Polymorphism is an OOP pattern where different classes share the **same method name** (the same interface), but each class implements that method in its own unique way. The calling code does not need to know what type of object it is dealing with — it simply calls the method, and the correct version runs automatically.

```php
// Shared interface — the "cut" command
interface Cuttable {
    public function cut(): void;
}

class Surgeon implements Cuttable {
    public function cut(): void {
        echo "Surgeon: Making an incision.";
    }
}

class Barber implements Cuttable {
    public function cut(): void {
        echo "Barber: Trimming the hair.";
    }
}

class Director implements Cuttable {
    public function cut(): void {
        echo "Director: Scene! Stop filming.";
    }
}

// Polymorphic usage — same call, different behaviour
$people = [new Surgeon(), new Barber(), new Director()];

foreach ($people as $person) {
    $person->cut();
}

// Output:
// Surgeon: Making an incision.
// Barber: Trimming the hair.
// Director: Scene! Stop filming.
```

### Why does this matter?

The loop above does not care what kind of object `$person` is. It only knows that every object has a `cut()` method. This makes the code:

- **Easy to extend**: Add a new class (e.g. `Hairdresser`) and the loop handles it automatically with zero changes.
- **Easy to maintain**: Each class manages its own behaviour independently.
- **Clean and readable**: One interface, many implementations — no messy `if/else` chains to check object types.

> Polymorphism works hand-in-hand with Interfaces and Abstract Classes. The shared interface or abstract method is what guarantees every class has the same method name, making polymorphic behaviour possible.
