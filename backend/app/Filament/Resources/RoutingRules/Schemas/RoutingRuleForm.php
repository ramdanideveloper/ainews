<?php

namespace App\Filament\Resources\RoutingRules\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RoutingRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('request_type')->options(array_combine(['detect_news_type', 'generate_news', 'generate_article', 'rewrite', 'seo_refresh', 'social_caption', 'image_generate'], ['Detect News Type', 'Generate News', 'Generate Article', 'Rewrite', 'SEO Refresh', 'Social Caption', 'Image Generate']))->required()->unique(ignoreRecord: true), Select::make('preferred_provider_id')->relationship('preferredProvider', 'name')->searchable()->preload(), Toggle::make('prefer_lowest_cost'), Toggle::make('is_active')->default(true),
            ]);
    }
}
