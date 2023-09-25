# laravel-data-scramble
Support for OpenAPI schema conversion of [Spatie's Laravel Data DTOs](https://spatie.be/docs/laravel-data/v3/) for Scramble

> 🚨 Mostly uploaded as a code sample; I am not planning to make a package out of it since it's small and very purpose-specific, but I thought it would be an example of what you can do with [Scramble's extension API](https://scramble.dedoc.co/developers/extensions).

If you are someone who uses Laravel Data as a way of creating very type-safe code, though, feel free to grab a sample!

## How it works
You must first register the extensions with Scramble by adding their FQDNs to the `scramble.extensions` config:

```php
// scramble.php
return [
    'extensions' => [
        LaravelDataRequestExtension::class,
        LaravelDataToSchema::class,
    ]
];
```

Then, you can use Laravel Data in the form of request or response schemas through type-hinting on your controller method signatures (_as a parameter or return type_).

### How it (internally) works
The `CastsDtoToSchema` trait features the code that converts the DTO into a `Schema` instance Scramble can understand. We can easily do this by leveraging Scramble's `RulesToParameters` helper and Laravel Data's validation capabilities.

We use these capabilities to create an `OperationExtension` that allows us to detect DTOs and register them as possible _"request bodies"_ of an OpenAPI operation. Then, we register a `TypeToSchemaExtension` to further indicate how to handle controller methods that return DTOs to Scramble. Both handlers execute the same logic.

Here is a step-by-step breakdown of how it works:

- We use the Reflection API to get the FQDN of the DTO and pass it to the `schemaFromDto` method
- Then, we use the Reflection API to create an instance of the DTO without constructor arguments to avoid validation issues
- We tell Laravel Data to get us a list of validation rules that Laravel understands
- ![image](https://github.com/CristianHG2/laravel-data-scramble/assets/4695165/0660fcbe-b6e6-4d86-accc-f082ac89f903)
- We then pass these resulting rules to Scramble's `RulesToParameters` service, which in turn returns an array of Scramble `Parameter`s
- ![image](https://github.com/CristianHG2/laravel-data-scramble/assets/4695165/a0178152-7eee-47af-85d9-829c59aec99c)
- Lastly, we use the `Schema::createFromParameters` method to generate the request body `Schema` that we then add to our `Operation`
- ![image](https://github.com/CristianHG2/laravel-data-scramble/assets/4695165/4acfd2e1-3f49-4bf8-8cfb-e20c084a9f0c)

A slightly different process is done for requests that accept query parameters. Instead of converting our `Parameter`s into a `Schema`, we simply add them to the request using the `addParameters` method of the `Operation` object.

### DTOs with `DataCollection`s
Laravel Data will not return a traditional validation rule payload for all levels of the DTO if you have any `DataCollection` props. Instead, it will return `NestedRule` instances, which Scramble's `RulesToParameters` helper cannot understand. 

Using the reflection API, I wrote a small recursion program to analyze the `NestedRule`. `NestedRule` executes a `Closure` fed to it when the validation rule payload is being created, and it invokes that `Closure` when it's time to validate the property with the `NestedRule`.

You can statically analyze the `Closure` by using reflections to get the value of the protected `NestedRule` `closure` property and then using `ReflectionClosure` to get a reflection of the `Closure` itself.

Then, we grab the context of the `Closure` by accessing its **use variables** through `getUseVariables.` This will give us access to the `DataProperty` that represents our `DataCollection`, which we can then use to get its registered attributes, namely `#[DataCollectionOf(...)]`. We can then grab the first parameter passed to `DataCollectionOf`, which is the FQDN of the DTO that will be inside the `DataCollection`.

![image](https://github.com/CristianHG2/laravel-data-scramble/assets/4695165/0afee4a1-dfb7-4acd-b8c9-e9df2ad53c42)


With an instance of our DTO now available, we repeat the initial rule conversion process described earlier in this file, with additional logic necessary to correct the key names.

Then, we replace our `NestedRule` with these values, and re-run the logic recursively until no `NestedRule` instances are left.

![image](https://github.com/CristianHG2/laravel-data-scramble/assets/4695165/602944c8-dd8a-4b24-ad25-c7e4bc375cc4)
