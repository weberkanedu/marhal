<?php

namespace App\Actions\Persons;

use App\Models\PersonRelation;

/**
 * Yakınlığı iki yönüyle birlikte siler.
 */
class RemovePersonRelation
{
    public function handle(PersonRelation $relation): void
    {
        PersonRelation::query()
            ->where(fn ($q) => $q->where('person_id', $relation->person_id)->where('related_person_id', $relation->related_person_id))
            ->orWhere(fn ($q) => $q->where('person_id', $relation->related_person_id)->where('related_person_id', $relation->person_id))
            ->delete();
    }
}
