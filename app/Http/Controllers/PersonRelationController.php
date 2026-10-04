<?php

namespace App\Http\Controllers;

use App\Actions\Persons\AddPersonRelation;
use App\Actions\Persons\RemovePersonRelation;
use App\Enums\Relation;
use App\Models\Person;
use App\Models\PersonRelation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Yolcunun yakınları (aile odası kuralı için). Ekranı yolcu detay sayfasıdır.
 */
class PersonRelationController extends Controller
{
    public function store(Request $request, Person $person, AddPersonRelation $add): RedirectResponse
    {
        Gate::authorize('update', $person);

        $data = $request->validate([
            'related_person_id' => ['required', 'uuid', Rule::exists('persons', 'id')->where('tenant_id', $person->tenant_id)->whereNull('deleted_at')],
            'relation' => ['required', Rule::enum(Relation::class)],
        ], [], ['related_person_id' => 'yakın', 'relation' => 'yakınlık']);

        $related = Person::query()->whereKey($data['related_person_id'])->firstOrFail();
        $add->handle($person, $related, Relation::from($data['relation']));

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$related->full_name} yakın olarak eklendi."]);

        return back();
    }

    public function destroy(PersonRelation $relation, RemovePersonRelation $remove): RedirectResponse
    {
        Gate::authorize('update', $relation->person);

        $remove->handle($relation);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Yakınlık kaldırıldı.']);

        return back();
    }
}
