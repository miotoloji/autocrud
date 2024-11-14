# autocrud


This package allows you to quickly set up your CRUD structures on Laravel.

The first version only supports single schema PostgreSQL database.

Using the package is extremely easy,
- Create your table with ideal column values in your database,
- Define your table's Unique, Foreign keys and indexes

Then enter the command via the console,

php artisan module:create MODULE_NAME --table=TABLE_NAME (default= MODULE_NAME) --created=CREATED_AT_COLUMN_NAME (default= created_at) --updated=UPDATED_AT_COLUMN_NAME (default = updated_at)

For install: composer require miotoloji/autocrud

The structure will be created in your app folder as follows.

Index, show, create, update, delete methods will come in your controller file.

FilterHelper will automatically perform filter requests coming through the API.

Fillable, casts fields will come in your model file as defined in your database table.

The data received from your table and the necessary validations will come in your request file.

routes - You can define module-specific routes in the api.php file.

-MODULE_NAME
	-Controllers
		-{MODULE_NAME}Controller
	-Helpers
		-FilterHelper
	-Models
		-{MODULE_NAME}
	-Providers
		-{MODULE_NAME}ServiceProvider
	-Requests
		-{MODULE_NAME}Request
		-{MODULE_NAME}FilterRequest
	-Resources
		-{MODULE_NAME}Resource
		-{MODULE_NAME}FailResource
	-routes
		-api.php

