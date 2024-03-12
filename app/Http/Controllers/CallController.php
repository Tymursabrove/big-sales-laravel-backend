<?php

namespace App\Http\Controllers;

use App\Http\Requests\Call\CreateRequest;
use App\Http\Resources\CallResource;
use App\Models\Call;
use App\Models\Caller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class CallController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $calls = Auth::user()->calls()->latest()->paginate(20);

        return CallResource::collection($calls);
    }

    public function store(CreateRequest $request)
    {
        $call = Call::create([
            ...$request->only(['title', 'first_name', 'last_name', 'requirement', 'phone_number']),

            'caller_id' => $request->caller_id ?? Caller::getRandom()->id,
            'user_id' => Auth::user()->id,
        ]);

        return new CallResource($call);
    }

    /**
     * Display the specified resource.
     */
    public function show(Call $call): CallResource
    {
        $call->load('meta');
        $call->load('caller');

        return new CallResource($call);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Call $call)
    {
        //
    }
}
