<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserStats;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    /**
     * Get authenticated user's stats.
     */
    public function myStats(Request $request)
    {
        $user = $request->user();
        $stats = $user->getOrCreateStats();

        return response()->json([
            'stats' => [
                'games' => [
                    'played' => $stats->games_played,
                    'won' => $stats->games_won,
                    'lost' => $stats->games_lost,
                    'abandoned' => $stats->games_abandoned,
                    'win_rate' => $stats->win_rate . '%',
                ],
                'rounds' => [
                    'played' => $stats->rounds_played,
                    'won' => $stats->rounds_won,
                    'win_rate' => $stats->rounds_played > 0 ?
                        round(($stats->rounds_won / $stats->rounds_played) * 100, 2) . '%' : '0%',
                ],
                'money' => [
                    'total_bet' => $stats->total_bet,
                    'total_won' => $stats->total_won,
                    'total_lost' => $stats->total_lost,
                    'profit' => $stats->profit,
                    'roi' => $stats->roi . '%',
                    'average_bet' => $stats->average_bet,
                    'biggest_win' => $stats->biggest_win,
                ],
                'streaks' => [
                    'current' => $stats->current_streak,
                    'best' => $stats->best_streak,
                ],
                'ranking' => [
                    'position' => $stats->getRank(),
                    'total_players' => UserStats::count(),
                ],
                'achievements' => [
                    'unlocked' => $stats->getUnlockedAchievements(),
                    'next' => $stats->getNextAchievements(),
                    'total_unlocked' => count($stats->achievements ?? []),
                    'total_available' => count(UserStats::ACHIEVEMENTS),
                ],
            ]
        ]);
    }

    /**
     * Get leaderboard.
     */
    public function leaderboard(Request $request)
    {
        $validated = $request->validate([
            'type' => 'sometimes|string|in:wins,money,streak,games',
            'period' => 'sometimes|string|in:all,month,week,today',
            'limit' => 'sometimes|integer|min:10|max:100',
        ]);

        $type = $validated['type'] ?? 'money';
        $period = $validated['period'] ?? 'all';
        $limit = $validated['limit'] ?? 20;

        $query = UserStats::query();

        // Filtrer par période
        if ($period !== 'all') {
            $startDate = match ($period) {
                'month' => now()->startOfMonth(),
                'week' => now()->startOfWeek(),
                'today' => now()->startOfDay(),
            };

            $query->where('updated_at', '>=', $startDate);
        }

        // Trier selon le type
        switch ($type) {
            case 'wins':
                $query->orderByDesc('games_won');
                break;
            case 'money':
                $query->orderByDesc('total_won');
                break;
            case 'streak':
                $query->orderByDesc('best_streak');
                break;
            case 'games':
                $query->orderByDesc('games_played');
                break;
        }

        $leaderboard = $query->with('user:id,pseudo,avatar')
            ->limit($limit)
            ->get()
            ->map(function ($stats, $index) use ($type) {
                $data = [
                    'rank' => $index + 1,
                    'user' => [
                        'id' => $stats->user->id,
                        'pseudo' => $stats->user->pseudo,
                        'avatar' => $stats->user->avatar,
                    ],
                    'games_played' => $stats->games_played,
                    'games_won' => $stats->games_won,
                    'win_rate' => $stats->win_rate . '%',
                ];

                // Ajouter la métrique principale selon le type
                switch ($type) {
                    case 'wins':
                        $data['highlight'] = $stats->games_won;
                        $data['highlight_label'] = 'Victoires';
                        break;
                    case 'money':
                        $data['highlight'] = $stats->total_won;
                        $data['highlight_label'] = 'Gains totaux';
                        break;
                    case 'streak':
                        $data['highlight'] = $stats->best_streak;
                        $data['highlight_label'] = 'Meilleure série';
                        break;
                    case 'games':
                        $data['highlight'] = $stats->games_played;
                        $data['highlight_label'] = 'Parties jouées';
                        break;
                }

                return $data;
            });

        // Position de l'utilisateur actuel
        $userRank = null;
        if ($request->user()) {
            $userStats = $request->user()->getOrCreateStats();
            $userValue = match ($type) {
                'wins' => $userStats->games_won,
                'money' => $userStats->total_won,
                'streak' => $userStats->best_streak,
                'games' => $userStats->games_played,
            };

            $userRank = UserStats::where(match ($type) {
                'wins' => 'games_won',
                'money' => 'total_won',
                'streak' => 'best_streak',
                'games' => 'games_played',
            }, '>', $userValue)->count() + 1;
        }

        return response()->json([
            'leaderboard' => $leaderboard,
            'type' => $type,
            'period' => $period,
            'user_rank' => $userRank,
        ]);
    }

    /**
     * Get all achievements.
     */
    public function achievements(Request $request)
    {
        $user = $request->user();
        $stats = $user->getOrCreateStats();
        $unlocked = $stats->achievements ?? [];

        $achievements = collect(UserStats::ACHIEVEMENTS)->map(function ($achievement, $key) use ($unlocked) {
            return array_merge($achievement, [
                'key' => $key,
                'unlocked' => isset($unlocked[$key]),
                'unlocked_at' => $unlocked[$key] ?? null,
            ]);
        });

        // Grouper par catégories
        $grouped = [
            'victories' => $achievements->filter(function ($a) {
                return in_array($a['key'], ['first_win', 'streak_3', 'streak_5', 'streak_10']);
            })->values(),
            'experience' => $achievements->filter(function ($a) {
                return in_array($a['key'], ['games_10', 'games_50', 'games_100']);
            })->values(),
            'money' => $achievements->filter(function ($a) {
                return in_array($a['key'], ['big_winner', 'whale']);
            })->values(),
            'special' => $achievements->filter(function ($a) {
                return in_array($a['key'], ['perfect_game']);
            })->values(),
        ];

        return response()->json([
            'achievements' => $grouped,
            'stats' => [
                'total_unlocked' => count($unlocked),
                'total_available' => count(UserStats::ACHIEVEMENTS),
                'completion_percentage' => round((count($unlocked) / count(UserStats::ACHIEVEMENTS)) * 100, 2),
            ],
            'next_achievements' => $stats->getNextAchievements(),
        ]);
    }

    /**
     * Get specific user stats.
     */
    public function userStats(Request $request, $userId)
    {
        $user = User::with('stats')->findOrFail($userId);
        $stats = $user->getOrCreateStats();

        // Ne pas montrer toutes les infos financières aux autres
        $isOwnProfile = $request->user()->id === $user->id;

        $data = [
            'user' => [
                'id' => $user->id,
                'pseudo' => $user->pseudo,
                'avatar' => $user->avatar,
                'created_at' => $user->created_at,
                'is_online' => $user->isOnline(),
                'last_seen_at' => $user->last_seen_at,
            ],
            'stats' => [
                'games' => [
                    'played' => $stats->games_played,
                    'won' => $stats->games_won,
                    'lost' => $stats->games_lost,
                    'win_rate' => $stats->win_rate . '%',
                ],
                'streaks' => [
                    'current' => $stats->current_streak,
                    'best' => $stats->best_streak,
                ],
                'ranking' => [
                    'position' => $stats->getRank(),
                ],
                'achievements' => [
                    'unlocked' => $stats->getUnlockedAchievements(),
                    'count' => count($stats->achievements ?? []),
                ],
            ]
        ];

        // Ajouter les infos financières seulement pour le propriétaire
        if ($isOwnProfile) {
            $data['stats']['money'] = [
                'total_won' => $stats->total_won,
                'biggest_win' => $stats->biggest_win,
                'profit' => $stats->profit,
            ];
        }

        return response()->json($data);
    }
}
