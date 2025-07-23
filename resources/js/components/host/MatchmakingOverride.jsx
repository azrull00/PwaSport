import React, { useState, useEffect, useMemo } from 'react';
import { HiUsers, HiRefresh, HiLockClosed, HiLockOpen, HiFilter, HiSearch } from 'react-icons/hi';
import Echo from 'laravel-echo';
import { toast } from 'react-hot-toast';
import { formatDistanceToNow } from 'date-fns';

const MatchmakingOverride = ({ userToken, eventId }) => {
    const [participants, setParticipants] = useState([]);
    const [matches, setMatches] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [selectedPlayers, setSelectedPlayers] = useState([]);
    const [searchQuery, setSearchQuery] = useState('');
    const [sortConfig, setSortConfig] = useState({ key: 'mmr', direction: 'desc' });
    const [filterConfig, setFilterConfig] = useState({ level: 'all' });
    const [lastUpdated, setLastUpdated] = useState(new Date());
    const [retryCount, setRetryCount] = useState(0);

    // WebSocket setup
    useEffect(() => {
        if (eventId) {
            const echo = new Echo({
                broadcaster: 'pusher',
                key: process.env.MIX_PUSHER_APP_KEY,
                cluster: process.env.MIX_PUSHER_APP_CLUSTER,
                forceTLS: true
            });

            echo.private(`event.${eventId}`)
                .listen('MatchmakingUpdated', (e) => {
                    setParticipants(e.data.participants);
                    setMatches(e.data.matches);
                    setLastUpdated(new Date());
                    toast.success('Matchmaking data updated');
                });

            return () => {
                echo.leave(`event.${eventId}`);
            };
        }
    }, [eventId]);

    useEffect(() => {
        loadParticipants();
        loadCurrentMatches();
    }, [eventId]);

    const loadParticipants = async () => {
        try {
            setLoading(true);
            const response = await fetch(`/host/matchmaking/${eventId}/participants`, {
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) throw new Error('Failed to load participants');

            const data = await response.json();
            if (data.status === 'success') {
                setParticipants(data.data.participants);
                setLastUpdated(new Date());
                setRetryCount(0);
            }
        } catch (error) {
            console.error('Error loading participants:', error);
            setError('Failed to load participants');
            
            // Implement retry with exponential backoff
            if (retryCount < 3) {
                const timeout = Math.pow(2, retryCount) * 1000;
                setTimeout(() => {
                    setRetryCount(prev => prev + 1);
                    loadParticipants();
                }, timeout);
            } else {
                toast.error('Failed to load participants after multiple attempts');
            }
        } finally {
            setLoading(false);
        }
    };

    const loadCurrentMatches = async () => {
        try {
            const response = await fetch(`/host/matchmaking/${eventId}/matches`, {
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) throw new Error('Failed to load matches');

            const data = await response.json();
            if (data.status === 'success') {
                setMatches(data.data.matches);
                setLastUpdated(new Date());
            }
        } catch (error) {
            console.error('Error loading matches:', error);
            toast.error('Failed to load matches');
        }
    };

    const handlePlayerSelect = (playerId) => {
        if (selectedPlayers.includes(playerId)) {
            setSelectedPlayers(selectedPlayers.filter(id => id !== playerId));
        } else if (selectedPlayers.length < 2) {
            setSelectedPlayers([...selectedPlayers, playerId]);
        } else {
            toast.error('You can only select two players at a time');
        }
    };

    const calculateCompatibility = (player1, player2) => {
        const mmrDiff = Math.abs(player1.mmr - player2.mmr);
        let score = 100;

        // MMR difference penalty
        if (mmrDiff > 200) score -= 40;
        else if (mmrDiff > 100) score -= 20;
        else if (mmrDiff > 50) score -= 10;

        // Level difference penalty
        if (player1.level !== player2.level) score -= 15;

        return Math.max(0, score);
    };

    const handleCreateMatch = async () => {
        if (selectedPlayers.length !== 2) {
            toast.error('Please select two players');
            return;
        }

        const player1 = participants.find(p => p.id === selectedPlayers[0]);
        const player2 = participants.find(p => p.id === selectedPlayers[1]);
        const compatibility = calculateCompatibility(player1, player2);

        if (compatibility < 60) {
            const proceed = window.confirm(
                `Warning: These players have low compatibility (${compatibility}%). Do you want to proceed?`
            );
            if (!proceed) return;
        }

        try {
            const response = await fetch(`/host/matchmaking/${eventId}/override`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    player1_id: selectedPlayers[0],
                    player2_id: selectedPlayers[1]
                })
            });

            if (!response.ok) throw new Error('Failed to create match');

            const data = await response.json();
            if (data.status === 'success') {
                toast.success('Match created successfully');
                setSelectedPlayers([]);
                loadCurrentMatches();
                loadParticipants();
            }
        } catch (error) {
            console.error('Error creating match:', error);
            toast.error('Failed to create match');
        }
    };

    const handleLockMatch = async (matchId, locked) => {
        if (!window.confirm(`Are you sure you want to ${locked ? 'lock' : 'unlock'} this match?`)) {
            return;
        }

        try {
            const response = await fetch(`/host/matchmaking/${eventId}/matches/${matchId}/lock`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ locked })
            });

            if (!response.ok) throw new Error('Failed to update match lock status');

            toast.success(`Match ${locked ? 'locked' : 'unlocked'} successfully`);
            loadCurrentMatches();
        } catch (error) {
            console.error('Error updating match lock:', error);
            toast.error('Failed to update match lock status');
        }
    };

    const getSkillLevelColor = (mmr) => {
        if (mmr >= 2000) return 'bg-purple-100 text-purple-800'; // Professional
        if (mmr >= 1600) return 'bg-blue-100 text-blue-800';    // Expert
        if (mmr >= 1200) return 'bg-green-100 text-green-800';  // Advanced
        if (mmr >= 800) return 'bg-yellow-100 text-yellow-800'; // Intermediate
        return 'bg-gray-100 text-gray-800';                     // Beginner
    };

    const getSkillLevel = (mmr) => {
        if (mmr >= 2000) return 'Professional';
        if (mmr >= 1600) return 'Expert';
        if (mmr >= 1200) return 'Advanced';
        if (mmr >= 800) return 'Intermediate';
        return 'Beginner';
    };

    // Filtered and sorted participants
    const filteredParticipants = useMemo(() => {
        return participants
            .filter(player => {
                const matchesSearch = player.name.toLowerCase().includes(searchQuery.toLowerCase());
                const matchesFilter = filterConfig.level === 'all' || getSkillLevel(player.mmr).toLowerCase() === filterConfig.level;
                return matchesSearch && matchesFilter;
            })
            .sort((a, b) => {
                if (sortConfig.key === 'mmr') {
                    return sortConfig.direction === 'asc' ? a.mmr - b.mmr : b.mmr - a.mmr;
                }
                if (sortConfig.key === 'name') {
                    return sortConfig.direction === 'asc' 
                        ? a.name.localeCompare(b.name)
                        : b.name.localeCompare(a.name);
                }
                return 0;
            });
    }, [participants, searchQuery, filterConfig, sortConfig]);

    const PlayerCard = ({ player, selected, onClick }) => (
        <div
            onClick={onClick}
            className={`p-4 bg-white rounded-lg shadow-sm cursor-pointer transition-all transform hover:scale-102 ${
                selected ? 'border-2 border-blue-600 ring-2 ring-blue-200' : 'border border-gray-200'
            }`}
        >
            <div className="flex items-center space-x-3">
                <div className="flex-shrink-0">
                    <div className="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center text-gray-600 font-medium">
                        {player.name.charAt(0)}
                    </div>
                </div>
                <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium text-gray-900 truncate">
                        {player.name}
                    </p>
                    <div className="flex items-center space-x-2">
                        <span className={`px-2 py-0.5 text-xs rounded-full ${getSkillLevelColor(player.mmr)}`}>
                            {getSkillLevel(player.mmr)}
                        </span>
                        <span className="text-sm text-gray-500">
                            MMR: {player.mmr}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    );

    const MatchCard = ({ match }) => (
        <div className="p-4 bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow">
            <div className="flex items-center justify-between">
                <div className="flex-1">
                    <div className="flex items-center justify-between mb-2">
                        <p className="text-sm font-medium text-gray-900">
                            {match.player1.name} vs {match.player2.name}
                        </p>
                        <button
                            onClick={() => handleLockMatch(match.id, !match.locked)}
                            className={`p-2 rounded-full transition-colors ${
                                match.locked ? 'text-red-600 hover:text-red-700' : 'text-green-600 hover:text-green-700'
                            }`}
                        >
                            {match.locked ? <HiLockClosed /> : <HiLockOpen />}
                        </button>
                    </div>
                    <div className="text-sm text-gray-500 space-y-1">
                        <p>Court: {match.court_number || 'Not assigned'}</p>
                        <p>Status: {match.status}</p>
                        <p>Created: {formatDistanceToNow(new Date(match.created_at), { addSuffix: true })}</p>
                    </div>
                </div>
            </div>
        </div>
    );

    if (loading && !participants.length) {
        return (
            <div className="flex items-center justify-center min-h-screen">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-gray-50 pb-20">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-2xl font-semibold text-gray-900">Matchmaking Override</h1>
                        <p className="text-sm text-gray-500">
                            Last updated: {formatDistanceToNow(lastUpdated, { addSuffix: true })}
                        </p>
                    </div>
                    <button
                        onClick={() => {
                            loadParticipants();
                            loadCurrentMatches();
                        }}
                        className="p-2 text-gray-600 hover:text-gray-900 transition-colors"
                        title="Refresh data"
                    >
                        <HiRefresh className="w-5 h-5" />
                    </button>
                </div>

                {error && (
                    <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center justify-between">
                        <span>{error}</span>
                        <button 
                            onClick={() => {
                                setError(null);
                                loadParticipants();
                                loadCurrentMatches();
                            }}
                            className="text-sm underline hover:no-underline"
                        >
                            Retry
                        </button>
                    </div>
                )}

                {/* Current Matches */}
                <div className="mb-8">
                    <h2 className="text-lg font-semibold text-gray-900 mb-4">Current Matches</h2>
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {matches.map(match => (
                            <MatchCard key={match.id} match={match} />
                        ))}
                        {matches.length === 0 && (
                            <div className="col-span-full text-center text-gray-500 py-8 bg-white rounded-lg">
                                No active matches
                            </div>
                        )}
                    </div>
                </div>

                {/* Available Players */}
                <div>
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
                        <h2 className="text-lg font-semibold text-gray-900">Available Players</h2>
                        <div className="flex items-center gap-4">
                            {/* Search */}
                            <div className="relative">
                                <input
                                    type="text"
                                    placeholder="Search players..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    className="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                                />
                                <HiSearch className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" />
                            </div>
                            
                            {/* Sort */}
                            <select
                                value={`${sortConfig.key}-${sortConfig.direction}`}
                                onChange={(e) => {
                                    const [key, direction] = e.target.value.split('-');
                                    setSortConfig({ key, direction });
                                }}
                                className="border border-gray-300 rounded-lg py-2 px-3 focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option value="mmr-desc">MMR (High to Low)</option>
                                <option value="mmr-asc">MMR (Low to High)</option>
                                <option value="name-asc">Name (A-Z)</option>
                                <option value="name-desc">Name (Z-A)</option>
                            </select>

                            {/* Filter */}
                            <select
                                value={filterConfig.level}
                                onChange={(e) => setFilterConfig({ ...filterConfig, level: e.target.value })}
                                className="border border-gray-300 rounded-lg py-2 px-3 focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option value="all">All Levels</option>
                                <option value="beginner">Beginner</option>
                                <option value="intermediate">Intermediate</option>
                                <option value="advanced">Advanced</option>
                                <option value="expert">Expert</option>
                                <option value="professional">Professional</option>
                            </select>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {filteredParticipants.map(player => (
                            <PlayerCard
                                key={player.id}
                                player={player}
                                selected={selectedPlayers.includes(player.id)}
                                onClick={() => handlePlayerSelect(player.id)}
                            />
                        ))}
                        {filteredParticipants.length === 0 && (
                            <div className="col-span-full text-center text-gray-500 py-8 bg-white rounded-lg">
                                No players found
                            </div>
                        )}
                    </div>
                </div>

                {/* Match Preview and Create Button */}
                {selectedPlayers.length > 0 && (
                    <div className="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 p-4">
                        <div className="max-w-7xl mx-auto">
                            {selectedPlayers.length === 2 && (
                                <div className="mb-4">
                                    <h3 className="text-sm font-medium text-gray-700 mb-2">Match Preview</h3>
                                    <div className="bg-gray-50 p-4 rounded-lg">
                                        {(() => {
                                            const player1 = participants.find(p => p.id === selectedPlayers[0]);
                                            const player2 = participants.find(p => p.id === selectedPlayers[1]);
                                            const compatibility = calculateCompatibility(player1, player2);
                                            return (
                                                <div className="space-y-2">
                                                    <div className="flex items-center justify-between">
                                                        <div>
                                                            <p className="font-medium">{player1.name}</p>
                                                            <p className="text-sm text-gray-500">
                                                                {getSkillLevel(player1.mmr)} - MMR: {player1.mmr}
                                                            </p>
                                                        </div>
                                                        <div className="text-center">
                                                            <div className={`px-3 py-1 rounded-full text-sm font-medium ${
                                                                compatibility >= 80 ? 'bg-green-100 text-green-800' :
                                                                compatibility >= 60 ? 'bg-yellow-100 text-yellow-800' :
                                                                'bg-red-100 text-red-800'
                                                            }`}>
                                                                {compatibility}% Compatible
                                                            </div>
                                                        </div>
                                                        <div className="text-right">
                                                            <p className="font-medium">{player2.name}</p>
                                                            <p className="text-sm text-gray-500">
                                                                {getSkillLevel(player2.mmr)} - MMR: {player2.mmr}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        })()}
                                    </div>
                                </div>
                            )}
                            <button
                                onClick={handleCreateMatch}
                                disabled={selectedPlayers.length !== 2}
                                className={`w-full py-3 px-4 rounded-lg font-medium transition-colors ${
                                    selectedPlayers.length === 2
                                        ? 'bg-blue-600 text-white hover:bg-blue-700'
                                        : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                                }`}
                            >
                                {selectedPlayers.length === 2
                                    ? 'Create Match'
                                    : `Select ${2 - selectedPlayers.length} more player${selectedPlayers.length === 1 ? '' : 's'}`}
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};

export default MatchmakingOverride;