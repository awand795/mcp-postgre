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

    public function storeModel(Request $request)
    {
        $request->validate([
            'provider_id' => 'required|exists:ai_providers,id',
            'model_name' => 'required|string|max:255',
            'display_name' => 'required|string|max:255',
        ]);

        AiModel::create($request->all());

        return back()->with('success', 'Model AI berhasil ditambahkan.');
    }

    public function deleteModel(AiModel $model)
    {
        $model->delete();
        return back()->with('success', 'Model AI berhasil dihapus.');
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

    public function storeProvider(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'code'     => 'required|string|max:50|unique:ai_providers,code|regex:/^[a-z0-9_]+$/',
            'base_url' => 'nullable|url|max:500',
        ], [
            'code.unique' => 'Kode provider sudah digunakan.',
            'code.regex'  => 'Kode provider hanya boleh huruf kecil, angka, dan underscore.',
            'base_url.url' => 'Format Base URL tidak valid.',
        ]);

        AiProvider::create([
            'name'     => $request->name,
            'code'     => $request->code,
            'base_url' => $request->base_url ?: null,
            'is_active' => true,
        ]);

        return back()->with('success', 'Provider AI "' . $request->name . '" berhasil ditambahkan.');
    }

    public function deleteProvider(AiProvider $provider)
    {
        // Cegah hapus provider built-in
        $builtIn = ['openai', 'gemini', 'claude', 'mistral'];
        if (in_array($provider->code, $builtIn)) {
            return back()->with('error', 'Provider built-in tidak dapat dihapus.');
        }

        // Cegah hapus jika masih ada API key aktif
        if ($provider->apiKeys()->count() > 0) {
            return back()->with('error', 'Hapus semua API Key provider ini terlebih dahulu sebelum menghapus provider.');
        }

        // Hapus semua model terkait lalu hapus provider
        $provider->models()->delete();
        $provider->delete();

        return back()->with('success', 'Provider "' . $provider->name . '" berhasil dihapus.');
    }
}
