# Bonsai :deciduous_tree:

[![Version](https://img.shields.io/packagist/v/baril/bonsai?label=stable)](https://packagist.org/packages/baril/bonsai)
[![License](https://img.shields.io/packagist/l/baril/bonsai)](https://packagist.org/packages/baril/bonsai)
[![Downloads](https://img.shields.io/packagist/dt/baril/bonsai)](https://packagist.org/packages/baril/bonsai/stats)
[![Tests](https://img.shields.io/github/actions/workflow/status/michaelbaril/bonsai/run-tests.yml?branch=master&label=tests)](https://github.com/michaelbaril/bonsai/actions/workflows/run-tests.yml)
[![Coverage](https://img.shields.io/endpoint?url=https%3A%2F%2Fmichaelbaril.github.io%2Fbonsai%2Fcoverage%2Fbadge.json)](https://michaelbaril.github.io/bonsai/coverage/)

This package is an implementation of the
["Closure Table" design pattern](https://dirtsimple.org/2010/11/simplest-way-to-do-tree-based-queries.html)
for Laravel Eloquent. This pattern allows for fast querying of tree-like
structures stored in a relational database. It is an alternative to nested sets.

You can find the full API documentation [here](https://michaelbaril.github.io/bonsai/api/).

## Version compatibility

 Laravel  | Bonsai
:---------|:----------
 12.x     | 3.3+
 11.x     | 3.2+
 10.x     | 3.1+
 9.x      | 3.x
 8.x      | 2.x / 3.x
 7.x      | 1.x
 6.x      | 1.x

:warning: Up until version 3.2, only MySQL is supported. Starting with version 3.3,
all DBMSs supported by Eloquent are supported by this package.

## Setup

First, your main table needs a `parent_id` column (the name can be customized).
This column is the one that holds the canonical data: the closures are merely a
duplication of that information.

Then, your model must implement the `Baril\Bonsai\Concerns\BelongsToTree` trait.

You can use the following properties to specify the table and column names:

* `$parentForeignKey`: name of the self-referencing foreign key in the main
table (defaults to `parent_id`),
* `$closureTable`: name of the closure table (defaults to the snake-cased model
name suffixed with `_tree`, e.g. `tag_tree`).

```php
use Baril\Bonsai\Concerns\BelongsToTree;

class Tag extends Model
{
    use BelongsToTree;

    protected $parentForeignKey = 'parent_tag_id';
    protected $closureTable = 'tag_closures';
}
```

Once your model is ready, you have to run the `bonsai:grow` command (described below).

## Artisan commands

### bonsai:grow

The `bonsai:grow` command will generate the migration file to create the closure table
for your model:

```bash
php artisan bonsai:grow "App\\Models\\Tag"
php artisan migrate
```

### bonsai:fix

If your `tag` table already contains data, you have to run another command
to create the closures for the existing data:

```bash
php artisan bonsai:fix "App\\Models\\Tag"
```

This command is also useful at any time if your closures get corrupt somehow,
as it will truncate the closure table and fill it again based on the data
found in the main table's `parent_id` column.

### bonsai:show

The `bonsai:show` command provides a quick-and-easy way to output the
content of the tree. It takes a `label` parameter that defines which column
(or accessor) to use as label. Optionally you can also specify a max depth.

```bash
php artisan bonsai:show "App\\Models\\Tag" --label=name --depth=3
```

## Updating the tree

Just fill the model's `parent_id` and save the model: the closure table will
be updated accordingly.

```php
$tag->parent()->associate($parentTag); // or just: $tag->parent_id = $parentTagId;
$tag->save();
```

The `save` method will throw a `\Baril\Bonsai\TreeException` in case of a
redundancy error (i.e. if the `parent_id` corresponds to the model itself
or one of its descendants).

You can also change the parent by using the `graft` and `graftOnto` methods:

```php
$newParentTag->graft($childTag);
// and:
$childTag->graftOnto($newParentTag);
// are both equivalent to:
$childTag->parent()->associate($newParentTag);
$childTag->save();
```

The `cut` method turns the model into a root (with its descendants preserved):

```php
$tag->cut();
// is equivalent to:
$tag->parent()->dissociate();
$tag->save();
```

When you delete a model, its closures will be deleted automatically. If the
model has descendants, the `delete` method will throw a `TreeException`. If you
want to delete the model and all its descendants, use the `deleteTree` method instead:

```php
try {
    $tag->delete();
} catch (\Baril\Bonsai\TreeException $e) {
    // some specific treatment
    // ...
    $tag->deleteTree();
}
```

## Relationships

The `BelongsToTree` trait provides the following relationships:

* `parent`: `BelongsTo` relation to the parent,
* `children`: `HasMany` relation to the children,
* `siblings`: `HasMany` relation to the children of the same parent.
* `ancestors`: `BelongsToMany` relation to the ancestors,
* `descendants`: `BelongsToMany` relation to the descendants.

### Siblings

:bulb: The `siblings` relation is a many-to-many relation, but under the hood,
it extends `HasMany`.

The `siblings` relation has the following scopes:

* `withSelf()`: will include the item itself in the results of the relation.
* `withOrphans()`: by default, the relation doesn't consider "orphans" (i.e. the roots of the tree)
  as siblings. Thus, it won't return any result when called on roots. Using this scope changes
  this behavior: calling the relation on a root will now return all other roots.

### Ancestors and descendants

:warning: The `ancestors` and `descendants` relations are read-only. Using the `attach` or `detach`
methods on these relations will throw an exception.

The `ancestors` and `descendants` relations have the following scopes:

* `withSelf()`: will include the item itself in the results of the relation.
* `orderByDepth($direction = 'asc')`: order the results by "depth", ie. distance from the referencing node.
* `maxDepth($depth)`: will retrieve ancestors/descendants up to (and including) the provided `$depth`.

Loading or eager-loading the `descendants` relation will automatically load the
`children` relation (with no additional query). Furthermore, it will load the
`children` relation recursively for all the eager-loaded descendants:

```php
$tags = Tag::with('descendants')->get();

// The following code won't execute any new query:
foreach ($tags as $tag) {
    dump($tag->name);
    foreach ($tag->children as $child) {
        dump('-' . $child->name);
        foreach ($child->children as $grandchild) {
            dump('--' . $grandchild->name);
        }
    }
}
```

Similarly, loading the `ancestors` relation will load the `parent` relation recursively.

## Methods

The `BelongsToTree` trait provides the following methods:

* `isRoot()`: returns `true` if the item has no parent.
* `isLeaf()`: returns `true` if the item has no child.
* `hasChildren()`
* `isChildOf($item)` (`$item` can be either a model or a model key)
* `isParentOf($item)`
* `isDescendantOf($item)`
* `isAncestorOf($item)`
* `isSiblingOf($item)`
* `findCommonAncestorWith($item)`: returns the first common ancestor between 2 items,
   or `null` if they don't have a common ancestor (which can happen if there are
   multiple roots).
* `getDistanceTo($item)`: returns the "distance" between 2 items (throws a `TreeException` if there's no common ancestor).
* `getDepth()`: returns the "depth" of the item in the tree (the root's depth being 0).
* `getHeight()`: returns the "height" of the subtree of which the item is the root (0 if the item is a leaf).

## Query scopes

The `BelongsToTree` trait provides the following query scopes:

* `onlyRoots()`
* `withoutRoots()`
* `onlyLeaves()`
* `withoutLeaves()`
* `hasChildren($bool = true)`: similar to either `onlyLeaves()` or `withoutLeaves()`,
depending on the value of `$bool`.
* `descendantsOf($ancestor, $maxDepth = null, $withSelf = false)`:
only return the descendants of `$ancestor`, with an optional
`$maxDepth`. The `$ancestor` parameter can be either a model or a model key.
If the `$withSelf` parameter is set to `true`, the ancestor will be included
in the query results too.
* `ancestorsOf($descendant, $maxDepth = null, $withSelf = false)`
* `withDepth($as = 'depth')`: will add a `depth` column (or whatever alias
you provided) to your resulting models.
* `withHeight($as = 'height')`: will add a `height` column (or whatever alias
you provided) to your resulting models (will work only with Laravel 10+).

## Special trees

### Soft deleting tree

To implement soft delete on your model, use the `Baril\Bonsai\Concerns\SoftDeletes`
trait instead of `Illuminate\Database\Eloquent\SoftDeletes`:

```php
use Baril\Bonsai\Concerns\BelongsToTree;
use Baril\Bonsai\Concerns\SoftDeletes;

class Tag extends Model
{
    use BelongsToTree;
    use SoftDeletes;
}
```

The trait defines the `forceDeleteTree` method (which is similar to `deleteTree` for hard delete)
and the `restoreTree` method. The latter method restores the model and all its soft-deleted descendants.

When you restore a model (either with `restore` or `restoreTree`), it will be restored under its
original parent, assuming it still exists. If the parent has been deleted (either soft or hard) in the meantime,
trying to restore the child will throw a `TreeException`. In this case, you may want to "graft" or "cut" the model
before you restore it:

```php
try {
    $tag->restore();
} catch (\Baril\Bonsai\TreeException $e) {
    $tag->cut()->restore(); // will restore $tag as a root
}
```

### Ordered tree

If you need each level of your tree to be explicitly ordered, install
[the Orderly package](https://github.com/michaelbaril/orderly) in addition to Bonsai:

```bash
composer require baril/orderly
```

You will need a `position` column in your main table (the name of the column
can be customized using the `$orderColumn` property).

Your model must use either the `Baril\Bonsai\Concerns\Orderable` trait
or the `Baril\Bonsai\Concerns\Ordered` trait.

```php
use Baril\Bonsai\Concerns\BelongsToTree;
use Baril\Bonsai\Concerns\Orderable;

class Tag extends Model
{
    use BelongsToTree;
    use Orderable;

    protected $orderColumn = 'order';
}
```

If you're using `Orderable`, you can order the `children` relation like this:

```php
$children = $this->children()->ordered()->get();
```

If you're using `Ordered`, the `children` relation is automatically ordered.

Check out the [documentation of the Orderly package](https://github.com/michaelbaril/orderly)
to see all available methods.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.
