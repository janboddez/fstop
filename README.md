# F-Stop
Simple blogware.

## Installation
F-Stop is built on top of Laravel 11. It requires PHP 8.2 or higher and either a MariaDB 10.3+, MySQL 5.7+, or PostgreSQL 9.5+ database. Thumbnail generation for image uploads requires either the GD or ImageMagick extension.

To install, first download the source:
```
git clone https://github.com/janboddez/fstop
cd fstop
git submodule update --recursive
```

Then create a config file.
```
cp .env.example .env
```

Edit `.env` like you normally would; fill out app name, database details, etc.


You don't have to, but you may want to [set up Supervisor](https://laravel.com/docs/11.x/queues#supervisor-configuration) to keep a (Redis or database) "queue worker" running.
Or simply keep `QUEUE_CONNECTION` set to `sync` (which may slow down the _back end_, but only slightly) and forget about queuing entirely.

If you _do_ choose to install Redis (or similar in-memory storage), you may want to use it as your session driver, too.

Let's not forget to actually install all dependencies, and generate an application key.
```
composer install --no-dev
php artisan key:generate
```

By default, media uploads are stored in `storage/app/public`. To make them publicly available, create a symbolic link in `public`.
```
php artisan storage:link
```

You absolutely should set up a cron job that runs every minute, so that scheduled background jobs can, well, run.
```
* * * * * cd ~/fstop && php artisan schedule:run > /dev/null 2>&1
```

You'll also need to create a user. You could use your database client of choice, or Laravel Tinker:
```
php artisan tinker
```

Then when the prompt appears:
```
DB::table('users')->insert([
    'name' => 'Alice',
    'email' => 'alice@example.org',
    'password' => Hash::make('<my-super-secret-password>'),
    'url' => 'https://example.org/',
]);
```
Note the URL field, which is required if you're planning to use IndieAuth.

You'll also want to set `public` as your web server's "document root."
You should now able to head over to https://example.org/admin and log in.
