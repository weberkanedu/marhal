<?php

namespace App\Actions\Persons;

use App\Enums\Relation;
use App\Models\Person;
use App\Models\PersonRelation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * İki kişi arasında yakınlık kurar. İki yönlü saklanır: "Ayşe, Ahmet'in eşi" girilince
 * "Ahmet, Ayşe'nin eşi" de yazılır (cinsiyete göre: kayınvalide ↔ gelin / damat vb.).
 */
class AddPersonRelation
{
    public function handle(Person $person, Person $related, Relation $relation): void
    {
        if ($person->is($related)) {
            throw ValidationException::withMessages(['related_person_id' => 'Kişi kendisiyle yakın olarak eklenemez.']);
        }

        DB::transaction(function () use ($person, $related, $relation): void {
            PersonRelation::query()->updateOrCreate(
                ['person_id' => $person->id, 'related_person_id' => $related->id],
                ['relation' => $relation],
            );
            PersonRelation::query()->updateOrCreate(
                ['person_id' => $related->id, 'related_person_id' => $person->id],
                ['relation' => $relation->inverse($person->gender)],
            );
        });
    }
}
