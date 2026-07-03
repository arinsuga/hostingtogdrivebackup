---
name: programming-fundamental-oop
description: A skill for understanding and applying Object-Oriented Programming (OOP) concepts such as classes, inheritance, encapsulation, and polymorphism, with a focus on how they translate to JavaScript.
---

# Object-Oriented Programming (OOP) Skill

Object-oriented programming (OOP) is a programming paradigm based on the concept of "objects", which can contain data (properties) and code (methods). It is a fundamental paradigm for building complex, modular, and maintainable systems.

## 1. Classes and Instances
Classes serve as templates or "blueprints" for creating objects. An instance is a specific object created from a class template.

- **Class**: Defines the data and methods that every instance of that class will have.
- **Instance**: A concrete object created using a constructor.
- **Constructor**: A special function used to initialize the internal state of a new instance.

### Code Example (Pseudocode)
```javascript
class Professor {
  properties: name, teaches
  constructor(name, teaches)
  methods: grade(paper), introduceSelf()
}

// Creating an instance
walsh = new Professor("Walsh", "Psychology");
```

## 2. Inheritance
Inheritance allows a class (subclass) to derive properties and methods from another class (superclass). This promotes code reuse and helps model "is-a" relationships.

- **Superclass (Parent)**: The base class being inherited from.
- **Subclass (Child)**: The class that extends the superclass.

### Code Example (Pseudocode)
```javascript
class Person {
  properties: name
  methods: introduceSelf()
}

class Professor extends Person {
  properties: teaches
  methods: grade(paper)
}

class Student extends Person {
  properties: year
}
```

## 3. Polymorphism
Polymorphism is the ability of different classes to provide their own implementation of a method that has the same name.

- **Method Overriding**: When a subclass provides a specific implementation for a method already defined in its superclass.

### Real-Life Example
A `Professor` and a `Student` are both `Person` instances, but they introduce themselves differently. When you call `.introduceSelf()`, the system uses the implementation specific to the object's class.

## 4. Encapsulation
Encapsulation is the practice of keeping an object's internal state private and only exposing a controlled public interface.

- **Private State**: Data that cannot be accessed directly from outside the object.
- **Public Interface**: Methods provided to interact with the object's state in a safe way.

### Benefits
- **Implementation Hiding**: You can change how an object works internally without breaking the code that uses it.
- **Data Protection**: Prevents external code from putting an object into an invalid state.

## OOP in JavaScript: Prototypes vs Classes
While JavaScript has a `class` syntax, its underlying mechanism is different from "classical" OOP languages like Java or C++.

- **Prototypes**: JavaScript uses a prototype chain. Every object has a hidden property (often `[[Prototype]]`) that points to another object.
- **Delegation**: Instead of "copying" properties during instantiation (like in classical OOP), JavaScript objects "delegate" to their prototype when a property or method isn't found on the object itself.
- **Classes as "Sugar"**: The `class` keyword in JavaScript is primarily "syntactic sugar" over the existing prototype-based inheritance mechanism.

## Benefits of OOP
- **Modularity**: Systems are broken down into self-contained objects.
- **Reusability**: Inheritance and composition allow code to be reused effectively.
- **Maintainability**: Encapsulation makes it easier to update individual components without side effects.
