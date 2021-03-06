# Analogue ORM 

**Analogue** is a decoupled port of the laravel's *Eloquent ORM*, implemented 'a la' Data Mapper. It aims to provide a flexible, extendable and easy to use ORM for large application designs. 

The project started as an attempt to solve the 'Repository pattern dilemna' trying to decouple Eloquent in a Repository/Entity fashion, and evolved into a fully functionnal ORM build to work nicely over the Laravel Database Abstraction layer.

It's not 'strictly' a data mapper, as the Domain objects extend an Entity class. It's a voluntary tradeoff, with the sole intention to reduce the boilerplate required in 'classic' datamappers, like getters & setters, array/json conversion, etc... 


##Main Features :

* Map SQL Database to database agnostic objects.
* Store Entity & Relationships in one method call. 
* Make queries using the Fluent Query Builder
* Lazy loading of relationships via Proxy objects.
* Eager Loading for solving the N+1 problem
* Timestamps support
* Soft Deletes
* Polymorphic Relationships
* Dynamic Relationships
* Embeddable Value Objects
* Flexible event system
* Out of the box Authentication Driver for Laravel.
* Easily Extendable


## Installation

Add this line to your composer.json file : 

```
"Analogue/ORM": "~1.0"
```

Then run : 

```
composer update
```

Add the Service Provider to config/app.php :

```
'Analogue\ORM\AnalogueServiceProvider',
```

If you're using facades, add this line to the aliases :
```
'Analogue' => 'Analogue\ORM\AnalogueFacade',
```

## Creating and using Entity objects

### Creating a custom Entity class 

First let's create a simple User model :

```php
namespace Acme\Users;

use Analogue\ORM\Entity;

class User extends Entity {

	public function __construct($email, $password)
	{
		$this->email = $email;
		$this->password = \Hash::make($password);
	}

}
```

Analogue's Entity objects are the equivalent of Eloquent objects, with the main difference that they have no interaction with the database at all. DB Queries takes place inside of a repository object. 

## Using the Analogue built-in repository

To interact with the database, you can either create your own repository classes by *extending the Analogue\ORM\Repository class* (see 05), or use a factory method to build a repository on the fly. Let's use the latter for now :

```php

$user = new Acme\Users\User('bob@example.com', 'MySecr3tP4ssword^&!');

$userRepository = Analogue::repository('Acme\Users\User');

$userRepository->store($user);

```

## Running queries 

Analogue repositories embed a custom query builder, allowing you to build complex queries that will seamlessly map to your Entity classes. Results that contains many rows are returned as an EntityCollection object.

```

$users = $userRepository->all();

$user = $userRepository->find(45);

$user = $userRepository->where('email', '=', 'bob@example.com')->first();

$users = $userRepository->paginate(50);

...
```

## Creating & Registering Maps

When you instantiate a repository for an Entity class, Analogue automatically create an EntityMap object that contains the configuration for Analogue to map objects to the database. 

However, if you need a more complex mapping, like adding relationships to other entities, defining a custom table name, using timestamps... you need to create an EntityMap class.

Let's create a Post Mapping with timestamps support and add a relationship definition with a Comment entity :

PostMap.php
```php

namespace Acme\Posts;

use Analogue\ORM\EntityMap;

class PostMap extends EntityMap
{
	protected $table = 'posts';

	protected $timestamps = true;

	// Note that you always have to pass the entity as a parameter
	public function comments($entity)
	{
		return $this->hasMany($entity, 'Acme\Comments\Comment');
	}

}

```

In fact, EntityMap classes have a lot in common with Eloquent model classes, with the major distinction that they don't actually contain data and they're not used to run query against the database. 

They're 'configuration' objects that indicate *Analogue* how to map your tables & relationships to objects.

Now, Analogue needs to know which Entity associates with which EntityMap. For that purpose, we need to register it.

```php

Analogue::register('Acme\Posts\Post', 'Acme\Posts\PostMap');

```
> Note, that you are free to associate multiple Entities to the same EntityMap, making Single Table Inheritance a breeze to implement. 

## Working with relationships

Once you have mapped relationships inside of an EntityMap object, working with relationships is really straightforward, as Analogue deal with the complex database work for you. 

Inside your domain logic, you work with Entities and Collections, and let the mapper makes the transformations both when querying & writing to the database.

As an example, let's create a Post entity and a related Comment entity :

Post.php :
```php
namespace Acme\Posts;

use Analogue\ORM\Entity;

class Post extends Entity {
	
	public function __construct($title, $content)
	{
		$this->title = $title;
		$this->content = $content;
		
		// Initialize comments as an empty collection object
		$this->comments = new Collection;
	}

	public function addComment(Comment $comment)
	{
		$this->comments->add($comment);
	}
}

```

*Analogue* expects as attribute an Entity object if the relation is 'single', and a Collection if the relation is 'many'. As our Post object may be related to many comments, we initialize an empty collection in the constructor to handle this.

Now let's create a simple Comment entity :

Comment.php :
```php
namespace Acme\Comments;

use Analogue\ORM\Entity;

class Comment extends Entity {
	
	public function __construct($message)
	{
		$this->message = $message;
	}

}
```

Last, let's use our freshly crafted code to store a post and a related comment :

```php

$post = new Post('Our first Post', 'Lorem Ipsum...');

$comment = new Comment('Our first Comment');

$post->addComment($comment);

$postRepository = Analogue::repository('Acme\Posts\Post');

$postRepository->store($post);

echo $post->id; // '1'
```

And that's all. Nice isn't it ? 

*Analogue* repository acts as an Aggregate for your entities & relationships. That means in the previous example, during the call to the store method, both the Post and the Comment were created in the database.


## Creating custom Repositories

*Analogue* provide all the boilerplate to implements the Repository Pattern inside your own, custom repository classes that fits your Application's logic.
Extending the Repository class allows you to embed complex query logic 

```php

use Analogue\ORM\Repository;

class PostRepository extends Repository {
	
}

```

Then, provide the constructor with the mapper instance corresponding to your EntityMap. This is easily accomplished in your application's service provider :

```php

public function register()
{

	$this->app->bind('PostRepository', function ($app) {
	
		$mapper = $app->make('analogue')->mapper('Acme\Posts\Post');
	
		return new PostRepository($mapper);

	});

}

```

### Creating custom queries.

Repositories use magic methods to access the embeded query builder, just like eloquent does. Let's implement a function that retrieve all of our active users.


```php

class PostRepository extends Repository {
	
	public function getLastPosts($number = 10)
	{
		return $this->orderBy('created_at','desc')->take($number)->get();
	}
}

```

### Creating custom queries on relationships

You can also create custom queries on your entity's relationships. This can be useful in many situations, eg to return the comment count on a single blog post. 

For that purpose you need to call the relationship method on the EntityMap which is embedded in the repository object :

```php

	public function getCommentCount(Post $post)
	{
		return $this->entityMap->comments($post)->count();
	}

```

### Eager Loading 

To enable eager loading on a query, simply use the with() method :

```php

	public function postWithComments()
	{
		return $this->with('comments')->get();
	}
```

### Lazy loading

When *Analogue* hydrate entities from a query, it will create a *Proxy* object for each relationship that is not eager loaded. This proxies will load the given relation only if they are accessed. Example :

```php

$posts = $postRepository->getLastPosts();  

// Access the first Entity in the collection

$post = $posts->first();

// This call on the proxy collection will run a query and populate the collection for you.
$comments = $post->comments->all();

```

## Full Documentation

(coming soon)

## Licence

	This package is licensed under the MIT License.

