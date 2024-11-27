# F-Stop
Simple blogware.

## Installation
F-Stop is built on top of Laravel 11. It requires PHP 8.2 or higher and either a MariaDB 10.3+, MySQL 5.7+, or PostgreSQL 9.5+ database. Thumbnail generation for image uploads requires ImageMagick and Fileinfo extensions, too.

To install, first download the source:
```
git clone https://github.com/janboddez/fstop
cd fstop
git submodule update --recursive
cp .env.example .env
```

Edit `.env` like you normally would; fill out app name, database details, etc.

You may want to [set up Supervisor](https://laravel.com/docs/11.x/queues#supervisor-configuration) to keep a (Redis or database) queue worker running.
Or simply keep `QUEUE_CONNECTION` set to `sync` (which may slow down the _back end_, but only slightly).

Let's not forget to actually install all dependencies, and generate an application key.
```
composer install --no-dev
php artisan key:generate
```

By default, media uploads are stored in `storage/app/public`. To make them publicly available, create a symbolic link in `public`.
```
php artisan storage:link
```

You'll also need to create a user. You could use your database client of choice, or Laravel Tinker:
```
php artisan tinker
```

Then when the prompt appears:
```
DB::table('users')->insert(['name' => 'alice', 'email' => 'alice@example.org', 'password' => Hash::make('<my-super-secret-password>'), 'url' => 'https://example.org/']);
```

You'll also want to set `fstop/public` as your web server's "document root."
You should now able to head over to https://example.org/admin and log in.
