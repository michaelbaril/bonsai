# Bonsai :potted_plant:

![Version](https://img.shields.io/packagist/v/baril/bonsai?label=version)
![License](https://img.shields.io/packagist/l/baril/bonsai)
![Downloads](https://img.shields.io/packagist/dt/baril/bonsai)
![Tests](https://img.shields.io/github/actions/workflow/status/michaelbaril/bonsai/run-tests.yml?branch=master&label=tests)

This package is an implementation of the "Closure Table" design pattern for
Laravel and MySQL. This pattern allows for faster querying of tree-like
structures stored in a relational database. It is an alternative to nested sets.

## Version compatibility

 Laravel  | Bonsai
:---------|:----------
 11.x     | 3.2+
 10.x     | 3.1+
 9.x      | 3.x
 8.x      | 2.x / 3.x
 7.x      | 1.x
 6.x      | 1.x
 
## Closure Table pattern

Let's say you have a `tags` table that contains a hierarchical list of tags.
You probably have a self-referencing foreign key called `parent_id` or
something similar.

The Closure Table pattern says you will create a secondary table (let's call it
`tag_tree`) with the following columns:

* `ancestor_id`: foreign key to your main table,
* `descendant_id`: foreign key to your main table,
* `depth`: unsigned integer.

The table contains all possible combinations of an ancestor and a descendant,
with the corresponding depth (ie. distance between the ancestor and the descendant).

For example, the following tree:

```
1
├─ 2
│ ├─ 3
│ └─ 4
└─ 5
```

will produce the following closures:

|ancestor_id|descendant_id|depth|
|---|---|---|
|1|1|0|
|1|2|1|
|1|3|2|
|1|4|2|
|1|5|1|
|2|2|0|
|2|3|1|
|2|4|1|
|3|3|0|
|4|4|0|
|5|5|0|

## Setup

### New install

First, your main table needs a `parent_id` column (the name can be customized).
This column is the one that holds the canonical data: the closures are merely a
duplication of that information.

Then, have your model implement the `Baril\Bonsai\Concerns\BelongsToTree` trait.

You can use the following properties to specify the table and column names:

* `$parentForeignKey`: name of the self-referencing foreign key in the main
table (defaults to `parent_id`),
* `$closureTable`: name of the closure table (defaults to the snake-cased model
name suffixed with `_tree`, eg. `tag_tree`).

```php

use Baril\Bonsai\Concerns\BelongsToTree;

class Tag extends Model
{
    use BelongsToTree;

    protected $parentForeignKey = 'parent_tag';
    protected $closureTable = 'tag_closures';
}
```

The `bonsai:grow` command will generate the migration file to create the closure table
for your model:

```bash
php artisan bonsai:grow "App\\Models\\Tag"
```

If you use the `--migrate` option, the command will also run the migration.
If your main table already contains data, it will also insert the closures for
the existing data.

```bash
php artisan bonsai:grow "App\\Models\\Tag" --migrate
```

:warning: If you use the `--migrate` option, any other pending migrations
will be applied as well.

There are some additional options: use `--help` to learn more.

## Artisan commands

In addition to the `bonsai:grow` command described above, this package
provides the following commands:

### bonsai:fix

In case your data gets corrupt somehow, the `bonsai:fix` command will truncate
the closure table and fill it again (based on the data found in the main table's
`parent_id` column):

```bash
php artisan bonsai:fix "App\\Models\\Tag"
```

### bonsai:show

The `bonsai:show` command provides a quick-and-easy way to output the
content of the tree. It takes a `label` parameter that defines which column
(or accessor) to use as label. Optionally you can also specify a max depth.

```bash
php artisan bonsai:show "App\\Models\\Tag" --label=name --depth=3
```

## Basic usage

Just fill the model's `parent_id` and save the model: the closure table will
be updated accordingly.

```php
$tag = Tag::find($tagId);
$tag->parent_id = $parentTagId; // or: $tag->parent()->associate($parentTag);
$tag->save();
```

The `save` method will throw a `\Baril\Bonsai\TreeException` in
case of a redundancy error (ie. if the `parent_id` corresponds to the model
itself or one of its descendants).

When you delete a model, its closures will be automatically deleted. If the
model has descendants, the `delete` method will throw a `TreeException`. You
need to use one of these 2 methods instead:
* `deleteTree` will delete the node and all its descendants,
* `deleteNode` will remove the node from the tree, ie. attach its children
to its parent (or make them roots if the node being deleted is a root), and then
delete the node.

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

The trait defines the following relationships:

* `parent`: `BelongsTo` relation to the parent,
* `children`: `HasMany` relation to the children,
* `ancestors`: `BelongsToMany` relation to the ancestors,
* `descendants`: `BelongsToMany` relation to the descendants,
* `siblings`: `HasMany` relation to the other children of the same parent
(requires to install the package `baril/octopus`).

:warning: The `ancestors` and `descendants` relations
are read-only! Trying to use the `attach` or `detach` method on them will throw
an exception.

The `ancestors` and `descendants` relations have the following methods:

* `includingSelf()`: will include the item itself in the results of the relation,
* `orderByDepth($direction = 'asc')`,
* `upToDepth($depth)`: will retrieve ancestors/descendants up to (and including) the provided `$depth`.

Loading or eager-loading the `descendants` relation will automatically load the
`children` relation (with no additional query). Furthermore, it will load the
`children` relation recursively for all the eager-loaded descendants:

```php
$tags = Tag::with('descendants')->limit(10)->get();

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

Of course, same goes with the `ancestors` and `parent` relations.

## Methods

The trait defines the following methods:

* `isRoot()`: returns `true` if the item's `parent_id` is `null`,
* `isLeaf()`: checks if the item is a leaf (ie. has no children),
* `hasChildren()`: `$tag->hasChildren()` is similar to `!$tag->isLeaf()`,
albeit more readable,
* `isChildOf($item)`,
* `isParentOf($item)`,
* `isDescendantOf($item)`,
* `isAncestorOf($item)`,
* `isSiblingOf($item)`,
* `findCommonAncestorWith($item)`: returns the first common ancestor between 2 items,
or `null` if they don't have a common ancestor (which can happen if the tree has
multiple roots),
* `getDistanceTo($item)`: returns the "distance" between 2 items,
* `getDepth()`: returns the depth of the item in the tree (the root element's depth is 0),
* `getSubtreeDepth()`: returns the depth of the subtree of which the item is the root (0 if the item is a leaf).

Also, the `getTree` static method can be used to retrieve the whole tree:

```php
$tags = Tag::getTree();
```

It will return a collection of the root elements, with the `children` relation
eager-loaded on every element up to the leafs.

## Query scopes

* `withAncestors($depth = null, $constraints = null)`: shortcut to
`with('ancestors')`, with the added ability to specify a `$depth` limit
(eg. `$query->withAncestors(1)` will only load the direct parent). Optionally,
you can pass additional `$constraints`.
* `withDescendants($depth = null, $constraints = null)`.
* `withDepth($as = 'depth')`: will add a `depth` column (or whatever alias
you provided) on your resulting models.
* `whereIsRoot($bool = true)`: limits the query to the items with no parent (the
behavior of the scope can be reversed by setting the `$bool` argument to
`false`).
* `whereIsLeaf($bool = true)`.
* `whereHasChildren($bool = true)`: is just the opposite of `whereIsLeaf`.
* `whereIsDescendantOf($ancestor, $maxDepth = null, $includingSelf = false)`:
limits the query to the descendants of `$ancestor`, with an optional
`$maxDepth`. If the `$includingSelf` parameter is set to `true`, the ancestor
will be included in the query results too. (The `$ancestor` parameter can be
either the id or the Model itself.)
* `whereIsAncestorOf($descendant, $maxDepth = null, $includingSelf = false)`.

## Ordered tree

In case you need each level of the tree to be explicitely ordered, you can use
the `Baril\Bonsai\Concerns\BelongsToOrderedTree` trait (instead of
`BelongsToTree`). In order to use this, you need the Orderly package in
addition to Bonsai:

```bash
composer require baril/orderly
```

You will need a `position` column in your main table (the name of the column
can be configured with the `$orderColumn` property).

```php
use Baril\Bonsai\Concerns\BelongsToOrderedTree;

class Tag extends Model
{
    use BelongsToOrderedTree;

    protected $orderColumn = 'order';
}
```

The `children` relation will now be ordered. In case you need to order it by
some other field, you need to use the `unordered` scope first:

```php
$children = $this->children()->unordered()->orderBy('name');
```

Also, all methods defined by the `Orderable` trait described
[in the Orderly package documentation](https://github.com/michaelbaril/orderly)
will now be available:

```php
$lastChild->moveToPosition(1);
```
