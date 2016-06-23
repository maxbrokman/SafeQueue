## SafeQueue

A Laravel Queue worker that's safe for use with Laravel Doctrine

#### When to use SafeQueue

- [x] You use Laravel 5
- [x] You use Laravel Doctrine
- [x] Devops say the CPU usage of `queue:listen` is unacceptable
- [x] You want to do `php artisan queue:work --daemon` without hitting cascading `EntityManager is closed` exceptions

#### How it Works

SafeQueue overrides a small piece of Laravel functionality to make the queue worker daemon safe for use with Doctrine.
It makes sure that the worker exits if the EntityManager is closed after an exception. For good measure it also clears the EM
before working each job.

#### Installation

Once I've put this on packagist I'll add instructions here, but for now you're on your own! I suggest using composer.

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

PRs welcome.

```
vendor/bin/php-cs-fixer fix
vendor/bin/phpunit
```

#### But.... Why are the Tests so Rubbish???

I've had to write this very quickly for my day job (which is a poor excuse I know). The problem with testing the queue
worker is that it is designed to not exist (it's a daemon). Of course you can make it hit its exit condition, but then it
literally calls `exit` which of course breaks phpunit.

I've decided to leave this uncovered by tests rather than override more code than is necessary just to make it testable.

If it makes you feel better the overridden code isn't tested in Laravel either!

Fell free to PR test coverage improvements if you're smarter than me.

#### Maintenance

I need this for work so it will probably be kept up to date but not making any promises.
