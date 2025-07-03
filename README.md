# F-Stop
Simple blogware.

## Installation
F-Stop is built on top of Laravel 12. It requires PHP 8.2 or higher and either a MariaDB 10.3+, MySQL 5.7+, or PostgreSQL 10.0+ database. Image support requires either the GD or ImageMagick extension.

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
Or simply keep `QUEUE_CONNECTION` set to `sync` (which may slow down the _back end_, but only slightly) and forget about workers entirely.

If you _do_ choose to install Redis (or similar in-memory storage), you may want to use it as your session driver, too.

Let's not forget to actually install all dependencies, generate an application key, and run the database migrations.
```
composer install --no-dev
php artisan key:generate
php artisan migrate
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
DB::table('users')->insert(['name' => 'Alice', 'email' => 'alice@example.net', 'login' => 'alice', 'password' => Hash::make('<my-super-secret-password>'), 'url' => 'https://example.org/']);
```
All fields are required. `login` determines your "Fediverse handle." E.g., in Alice's case (as per the example above), Mastodon and Pixelfed users should be able to find her using the `@alice@example.org` handle.

You'll also want to set `public` as your web server's "document root."
You should now able to head over to https://example.org/admin and log in.

## ActivityPub
If your site can be found at https://example.org/, Fedizens should be able to find your profile by searching for the `<my-login>@example.org` handle, or the `https://example.org/@<my-login>` URL. And follow it in order to have new posts sent to their server.

## Webmention
When a new post is first published, F-Stop will attempt to notify linked sites. The default theme supports microformats, too, for "more meaningful" site-to-site conversations.

## IndieAuth
You should be able to use your site's URL to log in to IndieAuth-compatible services.

## Micropub
F-Stop comes with partial Micropub support. E.g., creating new posts is supported.

## Short-Form Entry Types
Are supported by means of a "plugin."
