# Stack::StaticFile

This is a PHP port of the Rack::Static middleware.

The middleware intercepts requests for static files (javascript files, images, stylesheets, etc) based on the url prefixes or route mappings passed in the options.

## Usage

Wrap your HttpKernelInterface app in an instance of `Hampus\Stack\StaticFile` or add it to your middleware stack.

With [stack/builder](https://github.com/stackphp/builder):

```php
<?php

$options = [];

$app = (new Stack\Builder)
    ->push('Hampus\Stack\StaticFile', $options)
    ->resolve($app);
```

Without the builder:

```php
$app = new Hampus\Stack\StaticFile($app, $options);
```