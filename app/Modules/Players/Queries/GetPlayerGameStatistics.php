<?php

namespace App\Modules\Players\Queries;

use App\Models\User;
use App\Modules\Pets\Enums\GameEventDiscipline;
use App\Modules\Players\DTO\PlayerGameStatisticsData;
use App\Modules\Players\Enums\AchievementMetric;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class GetPlayerGameStatistics
{
    /**
     * Lifetime ownership follows the saved receipt, including dogs and puppies later transferred.
     *
     * @param  list<AchievementMetric>|null  $metrics  Null returns all public statistics.
     */
    public function handle(User $user, ?array $metrics = null): PlayerGameStatisticsData
    {
        $statistics = (new PlayerGameStatisticsData)->toArray();
        $statistics['competitionWins'] = (int) $user->competition_wins;
        $statistics['exhibitionWins'] = (int) $user->exhibition_wins;

        if ($this->requested($metrics, [AchievementMetric::CompetitionStarts, AchievementMetric::CompetitionPodiums,
            AchievementMetric::CompetitionWins, AchievementMetric::AgilityWins, AchievementMetric::NoseworkWins,
            AchievementMetric::CanicrossWins, AchievementMetric::ExhibitionStarts, AchievementMetric::ExhibitionPodiums,
            AchievementMetric::ExhibitionWins, AchievementMetric::WeeklyEventWins, AchievementMetric::MonthlyEventWins,
            AchievementMetric::ProgenyStarts])) {
            $successful = "entries.result->>'eliminated' = 'false'";
            $results = $this->completedEntries()->where('entries.user_id', $user->id)
                ->select(['events.discipline', 'events.frequency'])
                ->selectRaw('count(*) AS starts')
                ->selectRaw("count(CASE WHEN entries.rank BETWEEN 1 AND 3 AND {$successful} THEN 1 END) AS podiums")
                ->selectRaw("count(CASE WHEN entries.rank = 1 AND {$successful} THEN 1 END) AS wins")
                ->selectRaw("COALESCE(sum(CASE WHEN {$successful} THEN entries.prize ELSE 0 END), 0) AS prize_coins")
                ->selectRaw('COALESCE(sum(entries.fee), 0) AS fee_coins')
                ->groupBy('events.discipline', 'events.frequency')->get();
            $competitionWins = 0;
            $exhibitionWins = 0;
            foreach ($results as $result) {
                $discipline = GameEventDiscipline::from($result->discipline);
                $group = $discipline->isExhibition() ? 'exhibition' : 'competition';
                $statistics[$group.'Starts'] += (int) $result->starts;
                $statistics[$group.'Podiums'] += (int) $result->podiums;
                if ($discipline->isExhibition()) {
                    $exhibitionWins += (int) $result->wins;
                } else {
                    $competitionWins += (int) $result->wins;
                }
                $statistics[$discipline->value.'Wins'] += (int) $result->wins;
                if ($discipline === GameEventDiscipline::Progeny) {
                    $statistics['progenyStarts'] += (int) $result->starts;
                }
                if (in_array($result->frequency, ['weekly', 'monthly'], true)) {
                    $statistics[$result->frequency.'EventWins'] += (int) $result->wins;
                }
                $statistics['eventPrizeCoins'] += (int) $result->prize_coins;
                $statistics['eventFeesCoins'] += (int) $result->fee_coins;
            }
            $statistics['competitionWins'] = max($statistics['competitionWins'], $competitionWins);
            $statistics['exhibitionWins'] = max($statistics['exhibitionWins'], $exhibitionWins);
        }
        if ($metrics === null) {
            $titles = $this->completedEntries()->where('entries.user_id', $user->id)
                ->where('entries.rank', 1)->whereRaw("entries.result->>'eliminated' = 'false'")
                ->join('pet_titles AS titles', 'titles.game_event_entry_id', '=', 'entries.id')
                ->selectRaw('count(*) AS titles_count, count(DISTINCT titles.pet_id) AS dogs_count')->first();
            $statistics['titlesCount'] = (int) $titles->titles_count;
            $statistics['titledDogsCount'] = (int) $titles->dogs_count;
        }
        if ($this->requested($metrics, [AchievementMetric::LittersBorn])) {
            $litters = DB::table('breeding_litters')->where('initiator_id', $user->id)
                ->selectRaw('count(*) AS started, count(delivered_at) AS born')->first();
            $statistics['littersStarted'] = (int) $litters->started;
            $statistics['littersBorn'] = (int) $litters->born;
        }
        if ($this->requested($metrics, [AchievementMetric::PuppiesBorn])) {
            $statistics['puppiesBorn'] = DB::table('puppies')->join('breeding_litters AS litters', 'litters.id', '=', 'puppies.litter_id')
                ->where('litters.initiator_id', $user->id)->whereNotNull('litters.delivered_at')->count();
        }
        if ($this->requested($metrics, [AchievementMetric::PuppiesKept, AchievementMetric::PuppiesSold])) {
            $placements = DB::table('puppy_placements')->whereIn('kind', ['keep', 'purchase']);
            $placements->where(fn (Builder $query): Builder => $query->where('user_id', $user->id)->orWhere('seller_id', $user->id));
            $totals = $placements
                ->selectRaw("count(CASE WHEN kind = 'keep' AND user_id = ? THEN 1 END) AS kept", [$user->id])
                ->selectRaw("count(CASE WHEN kind = 'purchase' AND user_id = ? THEN 1 END) AS purchased", [$user->id])
                ->selectRaw("count(CASE WHEN kind = 'purchase' AND seller_id = ? AND user_id IS NOT NULL AND user_id <> seller_id THEN 1 END) AS sold", [$user->id])
                ->selectRaw("COALESCE(sum(CASE WHEN kind = 'purchase' AND seller_id = ? AND user_id IS NOT NULL AND user_id <> seller_id THEN price ELSE 0 END), 0) AS sale_coins", [$user->id])->first();
            $statistics['puppiesKept'] = (int) $totals->kept;
            $statistics['puppiesPurchased'] = (int) $totals->purchased;
            $statistics['puppiesSold'] = (int) $totals->sold;
            $statistics['puppySalesCoins'] = (int) $totals->sale_coins;
        }
        if ($this->requested($metrics, [AchievementMetric::TitledOffspring])) {
            $statistics['titledOffspring'] = DB::table('puppies')
                ->join('breeding_litters AS litters', 'litters.id', '=', 'puppies.litter_id')
                ->where('litters.initiator_id', $user->id)->whereNotNull('litters.delivered_at')
                ->whereExists(function (Builder $query): void {
                    $query->selectRaw('1')->from('pet_titles AS titles')
                        ->join('game_event_entries AS entries', 'entries.id', '=', 'titles.game_event_entry_id')
                        ->join('game_events AS events', 'events.id', '=', 'entries.game_event_id')
                        ->whereColumn('titles.pet_id', 'puppies.pet_id')
                        ->where('entries.is_npc', false)->whereNotNull('entries.user_id')
                        ->where('entries.status', 'completed')->whereNotNull('entries.completed_at')
                        ->where('events.status', '<>', 'cancelled')->where('entries.rank', 1)
                        ->whereRaw("entries.result->>'eliminated' = 'false'");
                })->count();
        }
        if ($this->requested($metrics, [AchievementMetric::AmmunitionPurchases])) {
            $statistics['ammunitionPurchases'] = DB::table('item_purchases')->where('user_id', $user->id)
                ->whereRaw("json_typeof(item_snapshot->'characteristics'->'competition') = 'object'")->count();
        }

        return new PlayerGameStatisticsData(...$statistics);
    }

    private function completedEntries(): Builder
    {
        return DB::table('game_event_entries AS entries')->join('game_events AS events', 'events.id', '=', 'entries.game_event_id')
            ->where('entries.is_npc', false)->whereNotNull('entries.user_id')
            ->where('entries.status', 'completed')->whereNotNull('entries.completed_at')
            ->whereNotNull('entries.result')->where('entries.rank', '>', 0)
            ->where('events.status', '<>', 'cancelled');
    }

    /**
     * @param  list<AchievementMetric>|null  $metrics
     * @param  list<AchievementMetric>  $group
     */
    private function requested(?array $metrics, array $group): bool
    {
        if ($metrics === null) {
            return true;
        }
        foreach ($group as $metric) {
            if (in_array($metric, $metrics, true)) {
                return true;
            }
        }

        return false;
    }
}
