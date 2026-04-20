<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiProvider;
use App\Models\AiModel;
use App\Models\AiApiKey;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function index()
    {
        $providers = AiProvider::with(['models', 'apiKeys'])->get();
        return view('admin.ai_management', compact('providers'));
    }

    public function storeKey(Request $request)
    {
        $request->validate([
            'provider_id' => 'required|exists:ai_providers,id',
            'key_name' => 'required|string|max:255',
            'api_key' => 'required|string',
        ]);

        AiApiKey::create($request->all());

        return back()->with('success', 'API Key berhasil ditambahkan.');
    }

    public function updateKey(Request $request, AiApiKey $key)
    {
        $request->validate([
            'key_name' => 'required|string|max:255',
            'api_key' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $data = $request->only(['key_name', 'is_active']);
        if ($request->filled('api_key')) {
            $data['api_key'] = $request->api_key;
        }
        
        $key->update($data);

        return back()->with('success', 'API Key berhasil diperbarui.');
    }

    public function deleteKey(AiApiKey $key)
    {
        $key->delete();
        return back()->with('success', 'API Key berhasil dihapus.');
    }

    public function toggleModel(AiModel $model)
    {
        $model->update(['is_active' => !$model->is_active]);
        return response()->json(['success' => true, 'is_active' => $model->is_active]);
    }

    public function toggleProvider(AiProvider $provider)
    {
        $provider->update(['is_active' => !$provider->is_active]);
        return response()->json(['success' => true, 'is_active' => $provider->is_active]);
    }

    public function resetLimit(AiApiKey $key)
    {
        $key->update(['limit_reached' => false]);
        return back()->with('success', 'Limit API Key berhasil di-reset.');
    }
}
