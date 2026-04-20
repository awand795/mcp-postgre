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
                    ['model_name' => 'gpt-5.4', 'display_name' => 'GPT-5.4'],
                    ['model_name' => 'gpt-5.4-mini', 'display_name' => 'GPT-5.4 Mini'],
                    ['model_name' => 'gpt-5.4-nano', 'display_name' => 'GPT-5.4 Nano'],
                    ['model_name' => 'gpt-5.4-pro', 'display_name' => 'GPT-5.4 Pro'],
                    ['model_name' => 'gpt-4o', 'display_name' => 'GPT-4o'],
                    ['model_name' => 'gpt-4-turbo', 'display_name' => 'GPT-4 Turbo'],
                    ['model_name' => 'gpt-3.5-turbo', 'display_name' => 'GPT-3.5 Turbo'],
                ]
            ],
            [
                'name' => 'Google Gemini',
                'code' => 'gemini',
                'models' => [
                    ['model_name' => 'gemini-2.0-flash-exp', 'display_name' => 'Gemini 2.0 Flash (Exp)'],
                    ['model_name' => 'gemini-2.0-pro-exp', 'display_name' => 'Gemini 2.0 Pro (Exp)'],
                    ['model_name' => 'gemini-2.0-flash', 'display_name' => 'Gemini 2.0 Flash'],
                    ['model_name' => 'gemini-2.0-pro', 'display_name' => 'Gemini 2.0 Pro'],
                    ['model_name' => 'gemini-2.0-flash-lite', 'display_name' => 'Gemini 2.0 Flash Lite'],
                    ['model_name' => 'gemini-2.5-pro', 'display_name' => 'Gemini 2.5 Pro'],
                    ['model_name' => 'gemini-2.5-flash', 'display_name' => 'Gemini 2.5 Flash'],
                    ['model_name' => 'gemini-1.5-pro', 'display_name' => 'Gemini 1.5 Pro'],
                    ['model_name' => 'gemini-1.5-flash', 'display_name' => 'Gemini 1.5 Flash'],
                    ['model_name' => 'gemini-3.0-pro', 'display_name' => 'Gemini 3.0 Pro'],
                    ['model_name' => 'gemini-3.1-pro', 'display_name' => 'Gemini 3.1 Pro'],
                ]
            ],
            [
                'name' => 'Anthropic Claude',
                'code' => 'claude',
                'models' => [
                    ['model_name' => 'claude-3-5-sonnet-20240620', 'display_name' => 'Claude 3.5 Sonnet'],
                    ['model_name' => 'claude-3-opus-20240229', 'display_name' => 'Claude 3 Opus'],
                    ['model_name' => 'claude-4-opus', 'display_name' => 'Claude 4 Opus'],
                    ['model_name' => 'claude-4-sonnet', 'display_name' => 'Claude 4 Sonnet'],
                    ['model_name' => 'claude-4-haiku', 'display_name' => 'Claude 4 Haiku'],
                    ['model_name' => 'claude-4.5-opus', 'display_name' => 'Claude 4.5 Opus'],
                    ['model_name' => 'claude-4.5-sonnet', 'display_name' => 'Claude 4.5 Sonnet'],
                    ['model_name' => 'claude-4.6-opus', 'display_name' => 'Claude 4.6 Opus'],
                ]
            ],
            [
                'name' => 'Mistral AI',
                'code' => 'mistral',
                'models' => [
                    ['model_name' => 'mistral-large-latest', 'display_name' => 'Mistral Large'],
                    ['model_name' => 'mistral-medium-latest', 'display_name' => 'Mistral Medium'],
                    ['model_name' => 'mistral-small-latest', 'display_name' => 'Mistral Small'],
                    ['model_name' => 'open-mistral-7b', 'display_name' => 'Mistral Tiny (7B)'],
                    ['model_name' => 'pixtral-12b-2409', 'display_name' => 'Pixtral'],
                    ['model_name' => 'codestral-latest', 'display_name' => 'Codestral'],
                ]
            ],
        ];

        foreach ($providers as $pData) {
            $provider = AiProvider::updateOrCreate(
                ['code' => $pData['code']],
                ['name' => $pData['name']]
            );

            foreach ($pData['models'] as $mData) {
                AiModel::updateOrCreate(
                    [
                        'provider_id' => $provider->id,
                        'model_name' => $mData['model_name']
                    ],
                    ['display_name' => $mData['display_name']]
                );
            }
        }
    }
}
