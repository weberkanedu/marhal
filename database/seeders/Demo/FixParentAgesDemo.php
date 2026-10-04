<?php

namespace Database\Seeders\Demo;

use App\Actions\Persons\AddPersonRelation;
use App\Actions\Persons\RemovePersonRelation;
use App\Enums\Relation;
use App\Models\PersonRelation;
use App\Models\Tenant;

/**
 * Düzeltme paketi: ilk oda planı demo paketi anne-çocuk yakınlığını rastgele yaşlarla kurmuştu
 * (ör. 20 yaşında "anne"). Ebeveyni çocuğundan genç görünen anne / baba yakınlıkları ters çevrilir.
 */
class FixParentAgesDemo
{
    public function __construct(
        private readonly AddPersonRelation $add,
        private readonly RemovePersonRelation $remove,
    ) {}

    public function run(Tenant $tenant): string
    {
        $fixed = 0;

        PersonRelation::query()
            ->whereIn('relation', [Relation::Mother, Relation::Father])
            ->with(['person', 'relatedPerson'])
            ->get()
            ->each(function (PersonRelation $relation) use (&$fixed): void {
                $child = $relation->person;
                $parent = $relation->relatedPerson;

                // Ebeveyn, çocuktan sonra doğmuşsa ters kurulmuştur.
                if ($child->birth_date === null || $parent->birth_date === null || $parent->birth_date <= $child->birth_date) {
                    return;
                }

                $this->remove->handle($relation);
                // Ters çevir: yaşlı olan ebeveyn, genç olan onun çocuğu; anne / baba karşı yönde cinsiyete göre yazılır.
                $this->add->handle($child, $parent, Relation::Child);
                $fixed++;
            });

        return "{$fixed} anne / baba yakınlığı yaşa göre düzeltildi.";
    }
}
