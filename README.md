# CoSiler

We love the concept of [*leocavalcante/siler*](https://github.com/leocavalcante/siler), its aiming an API for declarative programming in PHP.
We copy that concept, copy the source code and improve it with our coding style.

## Difference with Siler

- Use PHP8 Feature *null coallesce" instead of using function for getting value from array
- No functional "sugar" added
- The Container support for creating service/factory
- The Container upport loading PHP-configuration with simple value merging
- Internal framework state saved in namespaced container hive
- Remove support for PSR Request Interface and Swoole
- Autostart session when used in code