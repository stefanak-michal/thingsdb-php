# ThingsDB PHP client

PHP library for communication with [ThingsDB](https://www.thingsdb.io/) over TCP socket.

[![ko-fi](https://ko-fi.com/img/githubbutton_sm.svg)](https://ko-fi.com/Z8Z5ABMLW)

## :white_check_mark: Requirements

- ThingsDB [v1](https://docs.thingsdb.io/v1/)
- PHP ^8.2
- [rybakit/msgpack](https://github.com/rybakit/msgpack.php)
- [mbstring](https://www.php.net/manual/en/book.mbstring.php)
- [openssl](https://www.php.net/manual/en/book.openssl.php) - Required only for connection with enabled SSL

## :floppy_disk: Installation - Composer

Run the following command in your project to install the latest applicable version of the package:

`composer require stefanak-michal/thingsdb-php`

[Packagist](https://packagist.org/packages/stefanak-michal/thingsdb-php)

## :desktop_computer: Usage

Class `\ThingsDB\ThingsDB` provide all functionality related to socket connection with ThingsDB. It contains set of method which are based on documentation.
Every method has comment (annotation) with required information and link to documentation.

### Available methods

| Method      | Description                                                 |
|-------------|-------------------------------------------------------------|
| __construct | ThingsDB constructor - immediately connect to provided uri. |
| ping        | Ping, useful as keep-alive                                  |
| auth        | Authorization with username and password                    |
| authToken   | Authorization with token                                    |
| query       | Query ThingsDB                                              |
| run         | Run a procedure                                             |
| join        | Join one or more room(s)                                    |
| leave       | Leave one or more room(s)                                   |
| emit        | Emit an event to a room                                     |
| listening   | Listen for incoming packages                                |

### Listening

Listening is specific state in which you wait for emitted packages from ThingsDB. You can read more about it in [docs](https://docs.thingsdb.io/v1/listening/). PHP has max_execution_time and it is not allowed to set higher value than this. With max_execution_time=0 you can of course wait indefinitely.

`join`, `emit`, `leave` also emit package towards the one who did it. Therefore, don't be surprised when first package received with calling `listening` will be `ON_JOIN|ON_LEAVE|ON_EMIT` event type.

### Example

```php
use ThingsDB\ThingsDB;

$thingsDB = new ThingsDB();
$result = $thingsDB->auth(); // returns true on success
$message = $thingsDB->query('@:stuff', '"Hello World!";'); // returns "Hello World!" 
```

## :lock: SSL

To make connection with enabled SSL you can use third parameter of constructor which is context. This context is provided to [stream_context_create](https://www.php.net/manual/en/function.stream-context-create.php) when creating connection. `verify_peer` is the bare minimum to enable SSL communication.

```php
use ThingsDB\ThingsDB;
$thingsDB = new ThingsDB('localhost:9200', 15, [
    'socket' => ['tcp_nodelay' => true],
    'ssl' => ['verify_peer' => true]
]);
```

## :stopwatch: Timeout

Class constructor contains `$timeout` argument. This timeout is for established socket connection. To set up
timeout for establishing socket connection itself you have to set ini directive `default_socket_timeout`.

_Setting up ini directive isn't part of connection class because function `ini_set` can be disabled on production
environments for security reasons._

## Error

### \ThingsDB\error\ConnectException

This exception class is used for any exception related to connection on client side. 

### \ThingsDB\error\PackageException

This exception class is used when error occurs in ThingsDB. [list of error types](https://docs.thingsdb.io/v1/errors/)
