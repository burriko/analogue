<?php namespace Analogue\ORM\Relationships;

use Analogue\ORM\EntityCollection;

class MorphMany extends MorphOneOrMany {

	/**
	 * Get the results of the relationship.
	 *
	 * @return mixed
	 */
	public function getResults($relation)
	{
		$results = $this->query->get();

		$this->cacheResults($results);

		return $results;
	}

	/**
	 * Initialize the relation on a set of models.
	 *
	 * @param  array   $entities
	 * @param  string  $relation
	 * @return array
	 */
	public function initRelation(array $entities, $relation)
	{
		foreach ($entities as $entity)
		{
			$entity->setEntityAttribute($relation, $this->relatedMap->newCollection());
		}

		return $entities;
	}

	/**
	 * Match the eagerly loaded results to their parents.
	 *
	 * @param  array   $entities
	 * @param  \Analogue\ORM\EntityCollection  $results
	 * @param  string  $relation
	 * @return array
	 */
	public function match(array $entities, EntityCollection $results, $relation)
	{
		return $this->matchMany($entities, $results, $relation);
	}

}
