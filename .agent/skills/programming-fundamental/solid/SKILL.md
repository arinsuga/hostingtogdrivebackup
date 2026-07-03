---
name: programming-fundamental-solid
description: A skill for understanding and applying SOLID principles in software development to create maintainable, scalable, and robust codebases.
---

# SOLID Principles Skill

SOLID is a mnemonic acronym for five design principles intended to make software designs more understandable, flexible, and maintainable. These principles are fundamental to object-oriented design and system architecture.

## 1. Single Responsibility Principle (SRP)
> "A class should have only one reason to change."

Every module, class, or function should have responsibility over a single part of the functionality provided by the software, and that responsibility should be entirely encapsulated by the class.

### Real-Life Example
Imagine a baker. A baker's sole responsibility is baking bread. If the baker is also responsible for managing inventory, ordering supplies, and cleaning, their effectiveness in baking decreases. Each of these tasks should be assigned to different roles (e.g., InventoryManager, SupplyOrder, BakeryCleaner).

### Code Example (JavaScript)
```javascript
// Good: SRP followed
class BreadBaker {
  bakeBread() {
    console.log("Baking high-quality bread...");
  }
}

class InventoryManager {
  manageInventory() {
    console.log("Managing inventory...");
  }
}
```

## 2. Open/Closed Principle (OCP)
> "Software entities should be open for extension, but closed for modification."

You should be able to extend a class's behavior without modifying its source code. This is often achieved through inheritance or interfaces.

### Real-Life Example
A payment processor initially only supports Credit Cards. To add PayPal support, instead of modifying the existing `PaymentProcessor` class, you create a new `PayPalPaymentProcessor` that extends the base `PaymentProcessor` interface.

### Code Example (JavaScript)
```javascript
class PaymentProcessor {
  processPayment(amount) {
    throw new Error("Method not implemented");
  }
}

class CreditCardProcessor extends PaymentProcessor {
  processPayment(amount) {
    console.log(`Processing credit card payment: $${amount}`);
  }
}

// Extended without modifying base
class PayPalProcessor extends PaymentProcessor {
  processPayment(amount) {
    console.log(`Processing PayPal payment: $${amount}`);
  }
}
```

## 3. Liskov Substitution Principle (LSP)
> "Derived or child classes must be substitutable for their base or parent classes."

Objects of a superclass should be replaceable with objects of its subclasses without breaking the application or producing unexpected behavior.

### Real-Life Example
A classic violation is the Square-Rectangle problem. If a `Square` inherits from `Rectangle` but overrides `setWidth` to change both width and height, it might break code that expects a `Rectangle` to allow independent width/height changes.

### Code Example (JavaScript)
```javascript
class Rectangle {
  constructor(width, height) {
    this.width = width;
    this.height = height;
  }
  setHeight(h) { this.height = h; }
  setWidth(w) { this.width = w; }
  getArea() { return this.width * this.height; }
}

// Potential LSP violation if not handled carefully
class Square extends Rectangle {
  setWidth(w) {
    this.width = w;
    this.height = w;
  }
}
```

## 4. Interface Segregation Principle (ISP)
> "Clients should not be forced to depend upon interfaces that they do not use."

Instead of one fat interface, many small, client-specific interfaces are better. Each interface should have a specific responsibility.

### Real-Life Example
In a restaurant, a vegetarian customer shouldn't be forced to handle a menu containing non-vegetarian items. The menu should be segregated into `VegetarianMenu`, `NonVegetarianMenu`, and `DrinksMenu`.

### Code Example (JavaScript)
```javascript
// Segregated "Interfaces" (Simulated via classes)
class VegetarianMenu {
  getVegetarianItems() { return ["Salad", "Veg Curry"]; }
}

class NonVegetarianMenu {
  getNonVegetarianItems() { return ["Chicken Curry", "Fish Fry"]; }
}
```

## 5. Dependency Inversion Principle (DIP)
> "High-level modules should not depend on low-level modules. Both should depend on abstractions."

Abstractions should not depend on details. Details should depend on abstractions. This decouples modules and makes the system more flexible.

### Real-Life Example
A development team depends on a generic Version Control System (abstraction) rather than specific internal details of Git. This allows switching tools without changing the team's workflow.

### Code Example (JavaScript)
```javascript
// Abstraction
class IVersionControl {
  commit(message) { throw new Error("Not implemented"); }
}

// Implementation
class GitVersionControl extends IVersionControl {
  commit(message) { console.log(`Git commit: ${message}`); }
}

// High-level module depending on abstraction
class DevelopmentTeam {
  constructor(vcs) {
    this.vcs = vcs;
  }
  saveWork(msg) {
    this.vcs.commit(msg);
  }
}
```

## Benefits of SOLID
- **Maintainability**: Clear responsibilities make code easier to update.
- **Scalability**: New features can be added with minimal impact on existing code.
- **Flexibility**: Decoupling components allows for easier swaps and updates.
- **Growth**: Supports the long-term evolution of complex software systems.
