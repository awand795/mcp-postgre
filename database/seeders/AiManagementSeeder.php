<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AiProvider;
use App\Models\AiModel;

class AiManagementSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            [
                'name' => 'OpenAI',
                'code' => 'openai',
                'models' => [
                    ['model_name' => 'gpt-4o', 'display_name' => 'GPT-4o'],
                    ['model_name' => 'gpt-4-turbo', 'display_name' => 'GPT-4 Turbo'],
                    ['model_name' => 'gpt-3.5-turbo', 'display_name' => 'GPT-3.5 Turbo'],
                ]
            ],
            [
                'name' => 'Google Gemini',
                'code' => 'gemini',
                'models' => [
                    ['model_name' => 'gemini-1.5-pro', 'display_name' => 'Gemini 1.5 Pro'],
                    ['model_name' => 'gemini-1.5-flash', 'display_name' => 'Gemini 1.5 Flash'],
                    ['model_name' => 'gemini-1.0-pro', 'display_name' => 'Gemini 1.0 Pro'],
                ]
            ],
            [
                'name' => 'Anthropic Claude',
                'code' => 'claude',
                'models' => [
                    ['model_name' => 'claude-3-5-sonnet-20240620', 'display_name' => 'Claude 3.5 Sonnet'],
                    ['model_name' => 'claude-3-opus-20240229', 'display_name' => 'Claude 3 Opus'],
                    ['model_name' => 'claude-3-haiku-20240307', 'display_name' => 'Claude 3 Haiku'],
                ]
            ],
        ];

        foreach ($providers as $pData) {
            $provider = AiProvider::create([
                'name' => $pData['name'],
                'code' => $pData['code'],
            ]);

            foreach ($pData['models'] as $mData) {
                AiModel::create([
                    'provider_id' => $provider->id,
                    'model_name' => $mData['model_name'],
                    'display_name' => $mData['display_name'],
                ]);
            }
        }
    }
}
