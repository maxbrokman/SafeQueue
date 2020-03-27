## SafeQueue

A Laravel Queue worker that's safe for use with Laravel Doctrine

#### When to use SafeQueue

- [x] You use Laravel
- [x] You use Laravel Doctrine
- [x] Devops say the CPU usage of `queue:listen` is unacceptable
- [x] You want to do `php artisan queue:work --daemon` without hitting cascading `EntityManager is closed` exceptions

#### Compatibility

Version | Supported Laravel Versions 
------- | -------------------------- 
0.1.* | 5.1, 5.2 
0.2.* | ^5.3.16 
0.3.* | \> 5.4 & <=5.8.*

#### How it Works

SafeQueue overrides a small piece of Laravel functionality to make the queue worker daemon safe for use with Doctrine.
It makes sure that the worker exits if the EntityManager is closed after an exception. For good measure it also clears the EM
before working each job.

#### Installation

Install using composer

```
composer require digbang/safe-queue
```

Once you've got the codez add the following to your service providers in `app.php`

```
Digbang\SafeQueue\DoctrineQueueProvider::class
```
##### Lumen

Create the config file `config/safequeue.php` and load it: `$app->configure('safequeue');`
```
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Worker Command Name
    |--------------------------------------------------------------------------
    |
    | Configure the signature / name of the Work Command here. The default
    | is to rename the command to 'doctrine:queue:work', however you can
    | rename it to whatever you want by changing this value.
    |
    | To override the Laravel 'queue:work' command name just set this
    | to a false value or 'queue:work'.
    |
    */
    'command_name' => 'doctrine:queue:work',

];
```

#### Usage

```
php artisan doctrine:queue:work  connection --daemon --sleep=3 --tries=3 ...
```

All options are identical to Laravel's own `queue:work` method.
