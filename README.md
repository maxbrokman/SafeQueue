## SafeQueue

[![Latest Version on Packagist](https://img.shields.io/packagist/v/maxbrokman/safe-queue.svg)](https://packagist.org/packages/maxbrokman/safe-queue)
[![Build Status](https://travis-ci.org/maxbrokman/SafeQueue.svg?branch=0.2)](https://travis-ci.org/maxbrokman/SafeQueue)
[![Coverage Status](https://coveralls.io/repos/github/maxbrokman/SafeQueue/badge.svg?branch=0.2)](https://coveralls.io/github/maxbrokman/SafeQueue?branch=0.2)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE)

A Laravel Queue worker that's safe for use with Laravel Doctrine

## This package is Abandoned

Laravel since at least `5.3.15` has effectively removed the option to run a daemonised queue worker. While the code 
still exists it is deprecated and will be removed in the future.

It appears this decision is due to numerous issues with long running php processes, not limited just to those 
encountered by doctrine being forced to work with shared state.

**This package is tested up to Laravel `5.3.9` and will not work on later releases.**

#### When to use SafeQueue

- [x] You use Laravel 5
- [x] You use Laravel Doctrine
- [x] Devops say the CPU usage of `queue:listen` is unacceptable
- [x] You want to do `php artisan queue:work --daemon` without hitting cascading `EntityManager is closed` exceptions

#### Compatibility

Version | Supported Laravel Versions 
------- | -------------------------- 
0.1.* | 5.1, 5.2 
0.2.* | 5.3 

#### How it Works

SafeQueue overrides a small piece of Laravel functionality to make the queue worker daemon safe for use with Doctrine.
It makes sure that the worker exits if the EntityManager is closed after an exception. For good measure it also clears the EM
before working each job.

#### Installation

Install using composer

```
composer require maxbrokman/safe-queue
```

Once you've got the codez add the following to your service providers in `app.php`

```
MaxBrokman\SafeQueue\DoctrineQueueProvider::class
```

#### Usage

```
php artisan doctrine:queue:work  connection --daemon -sleep=3 --tries=3 ...
```

All options are identical to Laravel's own `queue:work` method.

#### Contributing

PRs welcome. Please send PRs to the relevant branch (`0.1, 0.2`) depending on which version of Laravel you are targeting.

Run tests and style fixer.

```
vendor/bin/php-cs-fixer fix
vendor/bin/phpunit
```

#### Maintenance

I maintain this as part of my day job, please open any issues on Github
